<?php
/**
 * Prüfungen vor der Freigabe (statischer Teil, Entwurf §8): gebaut wie der Bauplan sagt, sauberes HTML, keine
 * Fremdquellen, Links nur auf erlaubte Ziele, Termine, Formular, Bilder, Sperrliste und Platzhalter.
 * Sperrend = keine Freigabe; Hinweise sind zum Gegenlesen. Die Durchsicht des Kunden bleibt die Freigabe.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Pruefer
{
    /** Häufige ASCII-Ersatzschreibungen für Umlaute (Hinweis, nicht sperrend). */
    public const ASCII = '/\b\w*(fuer|ueber|koennen|moecht|waehl|zurueck|hinzufueg|aender|oeffn|groess|pruef|aerzt|maerz|schoen|taeglich|spaeter|naechst|koerper|gemaess|gebuehr|muess|wuerd|haeufig|frueh|loesung|uebersicht|moeglich|oesterreich)\w*/iu';
    /**
     * Wörter und Wendungen für „kostenlos“ — auch als Teil zusammengesetzter Wörter („Gratisabend“, „Kostenfreie Teilnahme“),
     * weil eine KI genau so ausweicht. „nicht umsonst“ (= mit gutem Grund) zählt nicht. Gesucht wird im GEFALTETEN Text (TextFaltung):
     * klein, ü = ue, ß = ss, ohne Verwechslungszeichen und Unsichtbares — darum „gebuehr…“ statt „gebühr…“.
     */
    private const KOSTENLOS = '/\b(kosten-?(?:los|frei)\w*|gratis\w*|gebuehrenfrei\w*|unentgeltlich\w*|(?<!nicht )umsonst\b|nulltarif\w*'
        . '|ohne (?:kosten|gebuehr(?:en)?)\b|eintritt frei\b|freie[mnrs]? eintritt\b)/u';
    private const PLATZHALTER = '/\{\{|\blorem ipsum\b|\bTODO\b/i';
    private const MAX_BILD = 400 * 1024;
    private const SEITEN = ['index.html', 'impressum.html', 'datenschutz.html'];

    /** @var list<Befund> */
    private array $befunde = [];
    /** @var array<string,true> Felder, deren Text sich nicht prüfen ließ (ein Befund je Feld, auch wenn Sperrliste und Preis-Prüfung es beide merken) */
    private array $unpruefbar = [];

    private function __construct(private readonly string $seite, private readonly Profil $profil, private readonly array $plan)
    {
    }

    /** @return list<Befund> */
    public static function pruefen(string $seite, ?string $heute = null, ?string $endo = null): array
    {
        $bau = Bauer::bauen($seite, false, $endo);
        if (Befund::sperrt($bau->befunde)) {
            return $bau->befunde;
        }
        $p = new self($seite, Profil::laden("$seite/profil"), json_decode((string) file_get_contents("$seite/seite.json"), true));
        $p->befunde = $bau->befunde;
        $p->nachbau($bau->dateien);
        $p->ordner($bau->dateien);
        $p->texte();
        $p->preis();
        $p->person();
        $p->bildrechte();
        $p->vorschau();
        $p->rechtstexte();
        foreach (self::SEITEN as $datei) {
            $p->html($datei);
        }
        $p->css();
        $p->termine($heute ?? (new \DateTimeImmutable('now', new \DateTimeZone(Text::ZONE)))->format('Y-m-d\TH:i'));
        $p->formular();
        if (($p->profil->daten['vorschlag'] ?? false) === true) {
            $p->hinweis('profil.vorschlag', 'Profil ist als vorschlag markiert', 'Kundenprofil: Farben und Schriften sind noch ein Vorschlag und noch nicht bestätigt.');
        }
        return $p->befunde;
    }

    /** Die Dateien im Ordner müssen genau dem entsprechen, was der Bauplan ergibt. */
    private function nachbau(array $dateien): void
    {
        $abweichend = [];
        foreach ($dateien as $pfad => $inhalt) {
            if (!is_file("$this->seite/$pfad") || file_get_contents("$this->seite/$pfad") !== $inhalt) {
                $abweichend[] = $pfad;
            }
        }
        $it = is_dir("$this->seite/es") ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$this->seite/es", \FilesystemIterator::SKIP_DOTS)) : [];
        foreach ($it as $f) {
            $pfad = substr($f->getPathname(), strlen($this->seite) + 1);
            if (!isset($dateien[$pfad])) {
                $abweichend[] = $pfad;
            }
        }
        if ($abweichend) {
            sort($abweichend);
            $this->sperrend('', 'gebaute Dateien passen nicht zu seite.json: ' . implode(', ', $abweichend),
                'Die Seite ist nicht auf dem Stand ihres Bauplans oder wurde von Hand verändert — bitte neu bauen.');
        }
    }

    /**
     * Im Seitenordner liegt nur, was dorthin gehört: Bauplan, Marke, Profilkopie, das Gebaute, Git und die Dateien des
     * Livegangs; in img/ nur Bilder, auf die der Bauplan verweist. Alles andere würde mit ausgeliefert.
     */
    private function ordner(array $dateien): void
    {
        $erlaubt = ['seite.json', 'ENDOSKELETON', 'profil', 'es', 'img', '.git', '.gitignore', 'VERSION', 'CHANGELOG.md'];
        foreach (array_diff(scandir($this->seite) ?: [], ['.', '..']) as $e) {
            if (!in_array($e, $erlaubt, true) && !isset($dateien[$e])) {
                $this->sperrend($e, "$e gehört nicht in den Seitenordner (würde ausgeliefert)", 'Im Ordner der Seite liegt eine fremde Datei — bitte entfernen.');
            }
        }
        if (!is_dir("$this->seite/img")) {
            return;
        }
        $genutzt = [];
        $plan = $this->plan;
        array_walk_recursive($plan, static function ($w) use (&$genutzt): void {
            if (is_string($w) && str_starts_with($w, 'img/')) {
                $genutzt[$w] = true;
            }
        });
        foreach (array_diff(scandir("$this->seite/img") ?: [], ['.', '..']) as $e) {
            if (!isset($genutzt["img/$e"])) {
                $this->sperrend("img/$e", "img/$e wird im Bauplan nicht verwendet", 'Im Bilderordner der Seite liegt eine Datei, die die Seite nicht verwendet — bitte entfernen.');
            }
        }
    }

    /** Verwendete Bilder aus dem Bestand: ohne Freigabe sperrend, mit eingebranntem Text zum Gegenlesen. */
    private function bildrechte(): void
    {
        $bilder = [];
        if (is_string($this->plan['meta']['vorschaubild'] ?? null)) {
            $bilder['meta.vorschaubild'] = $this->plan['meta']['vorschaubild'];
        }
        foreach ($this->plan['bausteine'] as $i => $b) {
            foreach (['bild', 'vorschau'] as $f) {
                if (is_string($b[$f]['datei'] ?? null)) {
                    $bilder["bausteine.$i.$f"] = $b[$f]['datei'];
                }
            }
        }
        foreach ($bilder as $pfad => $datei) {
            if (!str_starts_with($datei, 'bestand/')) {
                continue;
            }
            $eintrag = $this->profil->bild(substr($datei, 8)) ?? [];
            if (($eintrag['freigegeben'] ?? false) !== true) {
                $this->sperrend($pfad, "$datei ist im Bildbestand nicht freigegeben", $this->wo($pfad) . ': Für dieses Bild fehlt noch die Freigabe (Bildrechte).');
            }
            if (($eintrag['eingebrannter_text'] ?? false) === true) {
                $this->hinweis($pfad, "$datei hat eingebrannten Text", $this->wo($pfad) . ': Im Bild steht Text — bitte gegenlesen (keine Heil- oder Wirkversprechen).');
            }
        }
    }

    /** Sperrliste, Grenzwörter, Platzhalter und ASCII-Umlaute in allen Textfeldern des Bauplans und in genutzten Alt-Texten. */
    private function texte(): void
    {
        $felder = $this->textfelder();
        foreach ($this->plan['bausteine'] ?? [] as $i => $b) {
            foreach (['bild', 'vorschau'] as $f) {
                if (isset($b[$f]['datei']) && !isset($b[$f]['alt']) && str_starts_with($b[$f]['datei'], 'bestand/')) {
                    $felder["bausteine.$i.$f.alt"] = (string) ($this->profil->bild(substr($b[$f]['datei'], 8))['alt'] ?? '');
                }
            }
        }
        $sperr = $this->liste('sperrliste');
        $grenz = $this->liste('grenzwoerter');
        foreach ($felder as $pfad => $text) {
            $wo = $this->wo($pfad);
            // Verglichen wird, was der Leser sieht (Endo\Lp\Sperrliste, Endo\Lp\TextFaltung): nicht „ob die Zeichen gleich sind“.
            $gesperrt = Sperrliste::treffer($text, $sperr);
            $grenzen = $gesperrt === null ? null : Sperrliste::treffer($text, $grenz);
            if ($gesperrt === null || $grenzen === null) {
                $this->nichtPruefbar($pfad);   // nie still frei
            }
            foreach ($gesperrt ?? [] as $w) {
                $this->sperrend($pfad, "Sperrliste: „{$w}“", "$wo: „{$w}“ darf auf der Seite nicht stehen (Sperrliste).");
            }
            foreach ($grenzen ?? [] as $w) {
                $this->hinweis($pfad, "Grenzwort: „{$w}“", "$wo: „{$w}“ bitte gegenlesen — keine Heil- oder Wirkversprechen.");
            }
            if (preg_match(self::PLATZHALTER, $text) || (trim($text) !== '' && preg_match('/^[\s.…]+$/u', $text))) {
                $this->sperrend($pfad, 'Platzhalter im Text', "$wo: Hier steht noch ein Platzhalter statt echtem Text.");
            }
            if (preg_match(self::ASCII, $text, $m)) {
                $this->hinweis($pfad, "ASCII-Umlaut? „{$m[0]}“", "$wo: „{$m[0]}“ — bitte mit echten Umlauten schreiben (ä, ö, ü, ß).");
            }
        }
    }

    /**
     * Die Einträge einer Liste des Profils (Sperrliste, Grenzwörter), nur Texte. Ein Eintrag, der sich nicht prüfen lässt (über 64 KB, kein gültiges UTF-8), ist ein sperrender Befund am Profil
     * und wird übersprungen — die übrigen Einträge prüfen weiter. Ein Eintrag, der gefaltet nichts übrig lässt (nur „*“), zählt nicht (Sperrliste::treffer).
     *
     * @return list<string>
     */
    private function liste(string $schluessel): array
    {
        [$name, $label] = $schluessel === 'sperrliste' ? ['Sperrliste', 'der Sperrliste'] : ['Grenzwort', 'der Liste der Grenzwörter'];
        $liste = [];
        foreach (array_filter((array) ($this->profil->daten[$schluessel] ?? []), 'is_string') as $w) {
            if (Sperrliste::eintraege($w) === null) {
                $this->sperrend("profil.$schluessel", "$name: Eintrag nicht prüfbar (" . strlen($w) . ' Bytes)',
                    "Kundenprofil: Ein Eintrag $label lässt sich nicht prüfen (zu lang oder mit ungültigen Zeichen) — bitte kürzen oder ändern.");
                continue;
            }
            $liste[] = $w;
        }
        return $liste;
    }

    /** Ein Text, der sich nicht mit den Listen vergleichen lässt (zu lang, ungültige oder umkehrende Zeichen), sperrt — einmal je Feld. */
    private function nichtPruefbar(string $pfad): void
    {
        if (isset($this->unpruefbar[$pfad])) {
            return;
        }
        $this->unpruefbar[$pfad] = true;
        $this->sperrend($pfad, 'Sperrliste: Text nicht prüfbar', $this->wo($pfad) . ': Dieser Text lässt sich nicht auf unerlaubte Wörter prüfen — er ist zu lang oder enthält ungewöhnliche Schriftzeichen. Bitte kürzen und gewöhnliche Buchstaben verwenden.');
    }

    /** Alle Textfelder des Bauplans (Pfad => Text), ohne technische Schlüssel. */
    private function textfelder(): array
    {
        $felder = [];
        $sammeln = static function (mixed $w, string $pfad) use (&$sammeln, &$felder): void {
            if (is_string($w)) {
                $felder[$pfad] = $w;
            } elseif (is_array($w)) {
                foreach ($w as $s => $x) {
                    if (!in_array($s, ['typ', 'variante', 'anker', 'datei', 'ziel', 'quelle', 'kennung', 'anbieter', 'liste', 'art', 'beginn'], true)) {
                        $sammeln($x, $pfad === '' ? (string) $s : "$pfad.$s");
                    }
                }
            }
        };
        $sammeln($this->plan['meta'] ?? [], 'meta');
        $sammeln($this->plan['bausteine'] ?? [], 'bausteine');
        return $felder;
    }

    /** Hat ein Termin einen Preis ab 1 Euro (bei 0 = ausdrücklich kostenlos passen die Texte), darf kein Text „kostenlos“ oder „gratis“ versprechen (Entwurf 2026-10-06, Abschnitt 4). */
    private function preis(): void
    {
        $mitPreis = array_filter($this->plan['bausteine'] ?? [], static fn ($b) => ($b['typ'] ?? null) === 'termin' && is_int($b['preis'] ?? null) && $b['preis'] >= 1);
        if ($mitPreis === []) {
            return;
        }
        foreach ($this->textfelder() as $pfad => $text) {
            $lesarten = Sperrliste::lesarten($text, true);   // mit Leerzeichen um Satzzeichen: „Kosten – frei wählbar“ ist nicht „kosten-frei“
            if ($lesarten === null) {
                $this->nichtPruefbar($pfad);   // ob der Text „kostenlos“ verspricht, lässt sich nicht sagen
                continue;
            }
            foreach ($lesarten as $gefaltet) {
                $gefunden = preg_match(self::KOSTENLOS, $gefaltet, $m);
                if ($gefunden === false) {
                    $this->nichtPruefbar($pfad);   // ein gescheiterter Regex heißt nie „kein Treffer“
                    break;
                }
                if ($gefunden === 1) {
                    $wort = strtr($m[1], ['gebuehr' => 'gebühr']);   // die Faltung schreibt ü als ue
                    $this->sperrend($pfad, "„{$wort}“ trotz Preis", $this->wo($pfad) . ": „{$wort}“ passt nicht zu einem Vortrag mit Preis — bitte umformulieren.");
                    break;
                }
            }
        }
    }

    /** Eine Person ohne Namen zeigt die Seite nicht — stehen Rolle, Text oder Bild schon da, ist das wohl ein Versehen. */
    private function person(): void
    {
        foreach ($this->plan['bausteine'] ?? [] as $i => $b) {
            if (($b['typ'] ?? null) !== 'person' || trim((string) ($b['name'] ?? '')) !== '') {
                continue;
            }
            if (trim((string) ($b['rolle'] ?? '')) !== '' || trim((string) ($b['text'] ?? '')) !== '' || isset($b['bild'])) {
                $this->hinweis("bausteine.$i.name", 'Person ohne Namen — der Abschnitt wird nicht gezeigt',
                    $this->wo("bausteine.$i") . ': Ohne Namen zeigt die Seite diesen Abschnitt nicht — bitte den Namen ergänzen.');
            }
        }
    }

    private function rechtstexte(): void
    {
        foreach (['impressum' => 'Das Impressum', 'datenschutz' => 'Die Datenschutzerklärung'] as $name => $label) {
            if (Rechtstext::platzhalter($this->profil->rechtstext($name))) {
                $this->sperrend("profil.rechtliches.$name", "rechtliches/$name.txt enthält Platzhalter", "$label ist noch ein Platzhalter — der endgültige Text fehlt noch.");
            }
        }
    }

    /** Aufbau und Quellen einer gebauten Seite, so wie sie im Ordner liegt. */
    private function html(string $datei): void
    {
        $html = (string) @file_get_contents("$this->seite/$datei");
        if ($html === '') {
            return;   // fehlt: meldet schon der Nachbau-Vergleich
        }
        $x = new \DOMXPath(self::dom($html));
        if ($x->query('//h1')->length !== 1) {
            $this->sperrend($datei, "$datei: genau eine H1 erwartet, " . $x->query('//h1')->length . ' gefunden', 'Die Seite braucht genau eine Hauptüberschrift.');
        }
        if ($x->query('//@style|//style')->length > 0) {
            $this->sperrend($datei, "$datei: style-Attribut oder <style>", 'Die Seite enthält Gestaltung außerhalb des Profils.');
        }
        if ($x->query('//@*[starts-with(translate(name(), "ON", "on"), "on")]')->length > 0) {
            $this->sperrend($datei, "$datei: on*-Attribut", 'Die Seite enthält eingebettete Skripte.');
        }
        $vorige = 0;
        foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
            $stufe = (int) substr($h->nodeName, 1);
            if ($stufe > $vorige + 1) {
                $this->hinweis($datei, "$datei: Überschrift springt von H$vorige auf H$stufe („" . trim($h->textContent) . '“)', 'Eine Überschrift überspringt eine Ebene — für Screenreader bitte ohne Sprung gliedern.');
            }
            $vorige = $stufe;
        }
        foreach ($x->query('//script') as $s) {
            $src = $s->getAttribute('src');
            if ($s->getAttribute('type') !== 'application/ld+json' && !preg_match('#^es/lp\.js\?v=[0-9a-f]{10}$#', $src)) {
                $this->sperrend($datei, "$datei: Skript nicht erlaubt (" . ($src ?: 'inline') . ')', 'Die Seite enthält ein fremdes oder eingebettetes Skript.');
            }
        }
        if ($x->query('//iframe|//object|//embed')->length > 0) {
            $this->sperrend($datei, "$datei: iframe/object/embed im HTML (Einbettungen erst nach Klick)", 'Die Seite lädt eingebettete Inhalte schon beim Öffnen.');
        }
        $ids = [];
        foreach ($x->query('//*[@id]') as $e) {
            $id = $e->getAttribute('id');
            if (isset($ids[$id])) {
                $this->sperrend($datei, "$datei: id „{$id}“ doppelt", 'Eine Sprungmarke kommt doppelt vor — Links und Formularbeschriftungen gehen dann ins Leere.');
            }
            $ids[$id] = true;
        }
        $hosts = array_merge($this->profil->hosts(), ['www.youtube.com']);
        foreach ($x->query('//a[@href]') as $a) {
            $h = $a->getAttribute('href');
            if (str_starts_with($h, '#')) {
                if (!isset($ids[substr($h, 1)])) {
                    $this->sperrend($datei, "$datei: Anker $h gibt es nicht", 'Ein Link auf der Seite führt ins Leere.');
                }
            } elseif (str_starts_with($h, 'mailto:')) {
                continue;
            } elseif (str_contains($h, ':') || str_starts_with($h, '//')) {
                if (!Text::https($h, $hosts)) {
                    $this->sperrend($datei, "$datei: Link auf nicht erlaubtes Ziel $h", 'Ein Link führt auf eine nicht freigegebene Website.');
                }
            } elseif (!in_array(strtok($h, '?#'), self::SEITEN, true) || !is_file("$this->seite/" . strtok($h, '?#'))) {
                $this->sperrend($datei, "$datei: interner Link $h führt ins Leere", 'Ein Link auf der Seite führt ins Leere.');
            }
        }
        foreach ($x->query('//*[@src]|//*[@srcset]|//link[@href]') as $e) {
            foreach (['src', 'srcset', 'href'] as $attr) {
                $w = $e->getAttribute($attr);
                if ($w !== '' && (str_contains($w, '//') || preg_match('/^[a-z]+:/i', $w))) {
                    $this->sperrend($datei, "$datei: fremde Quelle $w", 'Die Seite lädt Inhalte von fremden Servern (Datenschutz).');
                }
            }
        }
        foreach ($x->query('//img') as $img) {
            $this->bild($datei, $img);
        }
    }

    /**
     * Die Vorschaukarte beim Teilen, so wie sie in index.html steht (Endo\Lp\Vorschau): og:url und og:image nur auf der eigenen
     * Adresse (kein fremder Host — ein fremdes og:url nähme der Seite ihre Geteilt-Zähler und Karten), und die genannte Vorschaudatei
     * muss da sein, ein JPEG von 1200×630 und höchstens 300 KB (WhatsApp zeigt größere oft nicht).
     */
    private function vorschau(): void
    {
        $x = new \DOMXPath(self::dom((string) @file_get_contents("$this->seite/index.html")));
        $meta = [];
        foreach ($x->query('//meta[@property="og:url" or @property="og:image"]') as $m) {
            $meta[$m->getAttribute('property')] = $m->getAttribute('content');
        }
        $eigen = is_string($this->plan['meta']['adresse'] ?? null) ? parse_url($this->plan['meta']['adresse']) : false;
        foreach ($meta as $name => $url) {
            $u = parse_url($url);
            if ($eigen === false || !is_array($u) || ($u['scheme'] ?? '') !== 'https' || ($u['host'] ?? '') !== ($eigen['host'] ?? null) || isset($u['port']) || isset($u['user'])
                || !str_starts_with($u['path'] ?? '', $eigen['path'] ?? '/')) {
                $this->sperrend('index.html', "index.html: $name zeigt auf einen fremden Host oder eine fremde Adresse ($url)", 'Die Vorschau beim Teilen des Links zeigt auf eine fremde Adresse.');
            }
        }
        if (!isset($meta['og:image'])) {
            return;
        }
        $mass = is_file("$this->seite/" . Vorschau::DATEI) ? @getimagesize("$this->seite/" . Vorschau::DATEI) : null;
        $kunde = 'Das Vorschaubild für das Teilen des Links ist nicht in Ordnung — bitte neu bauen.';
        if (!is_file("$this->seite/" . Vorschau::DATEI)) {
            $this->sperrend(Vorschau::DATEI, Vorschau::DATEI . ' fehlt, obwohl index.html og:image nennt', 'Das Vorschaubild für das Teilen des Links fehlt — bitte neu bauen.');
        } elseif ($mass === false || $mass[2] !== IMAGETYPE_JPEG) {
            $this->sperrend(Vorschau::DATEI, Vorschau::DATEI . ' ist kein JPEG', $kunde);
        } elseif ($mass[0] !== Vorschau::BREITE || $mass[1] !== Vorschau::HOEHE) {
            $this->sperrend(Vorschau::DATEI, sprintf('%s ist %d×%d, nicht %d×%d', Vorschau::DATEI, $mass[0], $mass[1], Vorschau::BREITE, Vorschau::HOEHE), $kunde);
        } elseif (filesize("$this->seite/" . Vorschau::DATEI) > Vorschau::MAX_BYTES) {
            $this->sperrend(Vorschau::DATEI, sprintf('%s ist zu groß: %d KB (erlaubt %d KB)', Vorschau::DATEI, (int) (filesize("$this->seite/" . Vorschau::DATEI) / 1000), Vorschau::MAX_BYTES / 1000),
                'Das Vorschaubild für das Teilen des Links ist zu groß — WhatsApp zeigt es dann nicht.');
        }
    }

    private static function dom(string $html): \DOMDocument
    {
        $d = new \DOMDocument();
        libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        return $d;
    }

    private function bild(string $datei, \DOMElement $img): void
    {
        $src = $img->getAttribute('src');
        foreach (['width', 'height', 'alt'] as $a) {
            if (!$img->hasAttribute($a)) {
                $this->sperrend($datei, "$datei: <img src=\"$src\"> ohne $a", 'Einem Bild fehlen Angaben (Maße oder Beschreibung).');
            }
        }
        $pfad = "$this->seite/" . strtok($src, '?');
        if (str_contains($src, '//') || str_contains($src, '/../') || str_starts_with($src, '../') || !is_file($pfad)) {
            return;   // Fremdquelle oder fehlend: melden Quellenprüfung bzw. Nachbau-Vergleich
        }
        $logo = 'es/' . $this->profil->daten['logo']['datei'];
        if ($src !== $logo && (@getimagesize($pfad)[2] ?? null) !== IMAGETYPE_WEBP) {
            $this->sperrend($datei, "$datei: $src ist kein WebP", 'Ein Bild ist nicht im platzsparenden WebP-Format.');
        }
        if (filesize($pfad) > self::MAX_BILD) {
            $this->sperrend($datei, sprintf('%s: %s ist %d KB groß (erlaubt 400 KB)', $datei, $src, (int) (filesize($pfad) / 1024)), 'Ein Bild ist zu groß und macht die Seite langsam.');
        }
    }

    private function css(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) @file_get_contents("$this->seite/es/lp.css"));
        if (stripos($css, '@import') !== false || preg_match('#url\(\s*[\'"]?\s*([a-z]+:|//)#i', $css)) {
            $this->sperrend('es/lp.css', 'es/lp.css lädt fremde Dateien (@import oder url mit Adresse)', 'Die Gestaltung lädt Dateien von fremden Servern (Datenschutz).');
        }
    }

    private function termine(string $heute): void
    {
        $jetzt = strtotime(Text::iso($heute));
        foreach ($this->plan['bausteine'] as $i => $b) {
            if ($b['typ'] !== 'termin') {
                continue;
            }
            $wo = $this->wo("bausteine.$i");
            $offen = 0;
            foreach ($b['termine'] as $j => $t) {
                $beginn = strtotime(Text::iso($t['beginn']));
                $ende = strtotime(Text::iso($t['beginn'], (int) ($t['dauer_min'] ?? 0)));
                if ($ende < $jetzt) {
                    $this->hinweis("bausteine.$i.termine.$j", "Termin {$t['beginn']} ist vergangen", "$wo: Der Termin am " . Text::datum($t['beginn'], $this->profil->sprache()) . ' ist vorbei und wird nicht mehr angezeigt.');
                    continue;
                }
                $offen++;
                if ($beginn > $jetzt && $beginn - $jetzt < 86400) {
                    $this->hinweis("bausteine.$i.termine.$j", "Termin {$t['beginn']} beginnt in weniger als 24 Stunden", "$wo: Der Termin beginnt in weniger als 24 Stunden.");
                }
            }
            if ($offen === 0) {
                $this->sperrend("bausteine.$i.termine", 'alle Termine vergangen', "$wo: Alle Termine liegen in der Vergangenheit — bitte einen neuen Termin eintragen.");
            }
        }
    }

    private function formular(): void
    {
        $typen = array_column($this->plan['bausteine'], 'typ');
        if (!in_array('anmeldung', $typen, true)) {
            return;
        }
        if ((string) ($this->profil->daten['formularziel'] ?? '') === '') {
            $this->sperrend('profil.formularziel', 'formularziel leer — Anmeldung nicht angebunden (Teil 5)',
                'Das Anmeldeformular ist noch nicht angebunden — die Seite kann noch nicht veröffentlicht werden.');
        }
        // Die Weiterleitung springt immer auf #anmeldung zurück; ohne diese Sprungmarke landet man am Seitenanfang.
        foreach ($this->plan['bausteine'] as $i => $b) {
            if (($b['typ'] ?? null) === 'anmeldung' && ($b['anker'] ?? null) !== 'anmeldung') {
                $this->sperrend("bausteine.$i.anker", 'Anmeldung ohne anker "anmeldung" — der Rücksprung ?anmeldung=…#anmeldung ginge an den Seitenanfang',
                    $this->wo("bausteine.$i") . ': Die Sprungmarke muss „anmeldung“ heißen, damit man nach dem Absenden wieder beim Formular landet.');
            }
        }
        $html = (string) @file_get_contents("$this->seite/index.html");
        if (!preg_match('/<input[^>]*name="einwilligung"[^>]*>/', $html, $m) || !str_contains($m[0], ' required')) {
            $this->sperrend('index.html', 'Einwilligung fehlt im Formular', 'Im Anmeldeformular fehlt die Einwilligung.');
        } elseif (preg_match('/\schecked\b/', $m[0])) {
            $this->sperrend('index.html', 'Einwilligung vorangehakt', 'Die Einwilligung im Formular ist schon angehakt — das ist nicht erlaubt.');
        }
        if (!preg_match('/<div class="form-hp"[^>]*>\s*<label[^>]*>[^<]*<\/label>\s*<input[^>]*tabindex="-1"/', $html)) {
            $this->sperrend('index.html', 'Honigtopf fehlt', 'Dem Formular fehlt der Schutz gegen automatische Anmeldungen.');
        }
    }

    /** „Abschnitt 2 „Themen“, Eintrag 3“ aus einem technischen Pfad. */
    private function wo(string $pfad): string
    {
        $t = explode('.', $pfad);
        if ($t[0] !== 'bausteine' || !isset($t[1])) {
            return 'Seiteneinstellungen';
        }
        $typ = (string) ($this->plan['bausteine'][(int) $t[1]]['typ'] ?? '');
        $name = Katalog::laden()->baustein($typ)['name'] ?? $typ;
        $wo = 'Abschnitt ' . ((int) $t[1] + 1) . " „{$name}“";
        return isset($t[3]) && ctype_digit($t[3]) ? "$wo, Eintrag " . ((int) $t[3] + 1) : $wo;
    }

    private function sperrend(string $pfad, string $technik, string $kunde): void
    {
        $this->befunde[] = Befund::sperrend($pfad, $technik, $kunde);
    }

    private function hinweis(string $pfad, string $technik, string $kunde): void
    {
        $this->befunde[] = Befund::hinweis($pfad, $technik, $kunde);
    }
}
