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
    private const PLATZHALTER = '/\{\{|\blorem ipsum\b|\bTODO\b/i';
    private const MAX_BILD = 400 * 1024;
    private const SEITEN = ['index.html', 'impressum.html', 'datenschutz.html'];

    /** @var list<Befund> */
    private array $befunde = [];

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
        $p->texte();
        $p->rechtstexte();
        foreach (self::SEITEN as $datei) {
            $p->html($datei);
        }
        $p->css();
        $p->termine($heute ?? (new \DateTimeImmutable('now', new \DateTimeZone(Text::ZONE)))->format('Y-m-d\TH:i'));
        $p->formular();
        if (($p->plan['meta']['vorschaubild'] ?? '') === '') {
            $p->hinweis('meta.vorschaubild', 'kein eigenes Vorschaubild', 'Seiteneinstellungen: Es gibt kein eigenes Vorschaubild für das Teilen in sozialen Netzwerken.');
        }
        if (($p->profil->daten['vorschlag'] ?? false) === true) {
            $p->hinweis('profil.vorschlag', 'Profil ist als vorschlag markiert', 'Kundenprofil: Farben und Schriften sind noch ein Vorschlag und nicht vom Kunden bestätigt.');
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

    /** Sperrliste, Grenzwörter, Platzhalter und ASCII-Umlaute in allen Textfeldern des Bauplans und in genutzten Alt-Texten. */
    private function texte(): void
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
        foreach ($this->plan['bausteine'] ?? [] as $i => $b) {
            foreach (['bild', 'vorschau'] as $f) {
                if (isset($b[$f]['datei']) && !isset($b[$f]['alt']) && str_starts_with($b[$f]['datei'], 'bestand/')) {
                    $felder["bausteine.$i.$f.alt"] = (string) ($this->profil->bild(substr($b[$f]['datei'], 8))['alt'] ?? '');
                }
            }
        }
        $sperr = array_filter((array) ($this->profil->daten['sperrliste'] ?? []), 'is_string');
        $grenz = array_filter((array) ($this->profil->daten['grenzwoerter'] ?? []), 'is_string');
        foreach ($felder as $pfad => $text) {
            $wo = $this->wo($pfad);
            foreach ($sperr as $w) {
                if ($w !== '' && mb_stripos($text, $w) !== false) {
                    $this->sperrend($pfad, "Sperrliste: „{$w}\"", "$wo: „{$w}\" darf auf der Seite nicht stehen (Sperrliste des Kunden).");
                }
            }
            foreach ($grenz as $w) {
                if ($w !== '' && mb_stripos($text, $w) !== false) {
                    $this->hinweis($pfad, "Grenzwort: „{$w}\"", "$wo: „{$w}\" bitte gegenlesen — keine Heil- oder Wirkversprechen.");
                }
            }
            if (preg_match(self::PLATZHALTER, $text) || (trim($text) !== '' && preg_match('/^[\s.…]+$/u', $text))) {
                $this->sperrend($pfad, 'Platzhalter im Text', "$wo: Hier steht noch ein Platzhalter statt echtem Text.");
            }
            if (preg_match(self::ASCII, $text, $m)) {
                $this->hinweis($pfad, "ASCII-Umlaut? „{$m[0]}\"", "$wo: „{$m[0]}\" — bitte mit echten Umlauten schreiben (ä, ö, ü, ß).");
            }
        }
    }

    private function rechtstexte(): void
    {
        foreach (['impressum' => 'Das Impressum', 'datenschutz' => 'Die Datenschutzerklärung'] as $name => $label) {
            if (Rechtstext::platzhalter($this->profil->rechtstext($name))) {
                $this->sperrend("profil.rechtliches.$name", "rechtliches/$name.txt enthält Platzhalter", "$label ist noch ein Platzhalter — der Text des Kunden fehlt.");
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
        $d = new \DOMDocument();
        libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        $x = new \DOMXPath($d);
        if ($x->query('//h1')->length !== 1) {
            $this->sperrend($datei, "$datei: genau eine H1 erwartet, " . $x->query('//h1')->length . ' gefunden', 'Die Seite braucht genau eine Hauptüberschrift.');
        }
        if (preg_match('/<[^>]+\sstyle\s*=|<style\b/i', $html)) {
            $this->sperrend($datei, "$datei: style-Attribut oder <style>", 'Die Seite enthält Gestaltung außerhalb des Profils.');
        }
        if (preg_match('/<[^>]+\son[a-z]+\s*=/i', $html)) {
            $this->sperrend($datei, "$datei: on*-Attribut", 'Die Seite enthält eingebettete Skripte.');
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
            $ids[$e->getAttribute('id')] = true;
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

    private function bild(string $datei, \DOMElement $img): void
    {
        $src = $img->getAttribute('src');
        foreach (['width', 'height', 'alt'] as $a) {
            if (!$img->hasAttribute($a)) {
                $this->sperrend($datei, "$datei: <img src=\"$src\"> ohne $a", 'Einem Bild fehlen Angaben (Maße oder Beschreibung).');
            }
        }
        $pfad = "$this->seite/" . strtok($src, '?');
        if (str_contains($src, '//') || str_contains($src, '..') || !is_file($pfad)) {
            return;   // Fremdquelle oder fehlend: melden Quellenprüfung bzw. Nachbau-Vergleich
        }
        $logo = 'es/' . $this->profil->daten['logo']['datei'];
        if ($src !== $logo && !str_ends_with(strtolower($src), '.webp')) {
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

    /** „Abschnitt 2 „Themen", Eintrag 3" aus einem technischen Pfad. */
    private function wo(string $pfad): string
    {
        $t = explode('.', $pfad);
        if ($t[0] !== 'bausteine' || !isset($t[1])) {
            return 'Seiteneinstellungen';
        }
        $typ = (string) ($this->plan['bausteine'][(int) $t[1]]['typ'] ?? '');
        $name = Katalog::laden()->baustein($typ)['name'] ?? $typ;
        $wo = 'Abschnitt ' . ((int) $t[1] + 1) . " „{$name}\"";
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
