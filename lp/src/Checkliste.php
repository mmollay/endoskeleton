<?php
/**
 * Checkliste einer gebauten Landingpage — Barrierefreiheit und Formular als eigene Punkte (Pruefpunkt), getrennt von der Freigabe-Prüfung
 * (Pruefer). Gelesen wird nur, was gebaut im Ordner liegt (index.html, impressum.html, datenschutz.html, es/lp.css), und der Bauplan
 * seite.json; die Checkliste baut nicht und schreibt nichts. So ändert sie die Befunde von lp/pruefen.php nie — das Panel hebt Entwürfe nur
 * dann auf eine neue Version, wenn der Probebau dort keinen neuen Befund bringt.
 *
 * „mangel“ = statisch sicher festzustellen und ein Verstoß gegen WCAG 2.1 (A/AA) oder eine Pflicht des Datenschutzes: Bild ohne
 * Beschreibung, auch ein Dateiname oder „Foto“ als Beschreibung (1.1.1, F30), Link oder Knopf ohne Namen (4.1.2), keine Sprache (3.1.1),
 * nicht genau eine H1, tabindex über 0 (2.4.3: das Element käme noch vor dem Sprunglink dran), Fokusrahmen weg ohne Ersatz (2.4.7, F78),
 * Feld ohne Beschriftung, Pflicht nicht ausgezeichnet, vorangehakte Einwilligung, kein Datenschutzhinweis, kein Spam-Schutz, Ziel ohne https.
 * „hinweis“ = zum Gegenlesen, weil es auf den Zusammenhang ankommt: nichtssagender Linktext (2.4.4 lässt den Satz drumherum gelten),
 * übersprungene Überschriftenebene, sehr lange Bildbeschreibung. Den Kontrast prüft schon der Bau (Kontrast.php, sperrend) — hier nicht.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Checkliste
{
    private const SEITEN = ['index.html', 'impressum.html', 'datenschutz.html'];
    /** Länger ist eine Bildbeschreibung vorgelesen kaum noch anzuhören (Hinweis). */
    private const ALT_LANG = 150;
    /** Ein Dateiname statt einer Beschreibung (F30): „foto1.webp“, „IMG_1234“, „DSC01234“, „Foto 3“, nur Ziffern. */
    private const DATEINAME = '/\.(webp|jpe?g|png|gif|svg|avif|bmp|tiff?|heic)$|^((img|dsc[nf]?|pxl|pict?|photo|foto|bild|image|scan|screenshot|bildschirmfoto)[\s_-]*)?\d[\d\s_.:-]*$/iu';
    /** Ein Wort, das nur sagt, dass da ein Bild ist (F30). */
    private const NUR_BILD = '/^((das|ein) )?(bild|foto|fotografie|grafik|abbildung|illustration|image|img|photo|picture|pic|logo|icon|symbol|platzhalter|placeholder|alt|alt-?text|bildbeschreibung)$/iu';
    /** Link- und Knopftexte, die allein nicht sagen, wohin sie führen. */
    private const NICHTSSAGEND = '/^(hier|hier klicken|bitte hier klicken|(klicken sie|klick|klicke) hier|mehr|mehr (lesen|erfahren|dazu|infos?|informationen)|weiter|weiterlesen|link|details|click here|here|more|read more|learn more)$/iu';
    /** Eine Deklaration, die den Fokusrahmen wegnimmt (der ganze Wert ist none oder 0). */
    private const OHNE_RAHMEN = '/(?:^|;)\s*outline(?:-style|-width)?\s*:\s*(?:none|0(?:px|rem|em)?)\s*(?:!important)?\s*(?:;|$)/i';
    /** Was den Fokus auch ohne Rahmen sichtbar macht: Schatten, Rand, Fläche, Farbe, Unterstreichung, Einblenden (Sprunglink). */
    private const ERSATZ = '/(?:^|;)\s*(?:box-shadow|border(?!-(?:radius|collapse|spacing|image))[a-z-]*|background(?:-color|-image)?|color|text-decoration[a-z-]*|filter|transform|top|right|bottom|left|inset|clip|clip-path)\s*:\s*(?!none\b)[^;\s]/i';
    private const KLASSE = 'contains(concat(" ", normalize-space(@class), " "), " %s ")';

    /** @var list<Pruefpunkt> */
    private array $punkte = [];
    /** @var array<string, true> derselbe Punkt nur einmal (das Logo steht auf allen drei Seiten) */
    private array $gesehen = [];
    private ?Katalog $katalog = null;

    private function __construct(private readonly array $plan)
    {
    }

    /** @return list<Pruefpunkt> */
    public static function pruefen(string $seite): array
    {
        $plan = json_decode(self::lesen($seite, 'seite.json'), true, 64, JSON_THROW_ON_ERROR);
        $c = new self(is_array($plan) ? $plan : throw new \RuntimeException('seite.json ist kein Bauplan'));
        foreach (self::SEITEN as $datei) {
            $x = new \DOMXPath(self::dom(self::lesen($seite, $datei)));
            $c->sprache($datei, $x);
            $c->bilder($datei, $x);
            $c->ueberschriften($datei, $x);
            $c->bedienelemente($datei, $x);
            $c->tabindex($datei, $x);
            $c->formulare($datei, $x);
        }
        $c->fokus(self::lesen($seite, 'es/lp.css'));
        return $c->punkte;
    }

    /** Sprache (WCAG 3.1.1): <html lang> mit einem Sprachkürzel — sonst liest der Bildschirmleser womöglich mit falscher Aussprache vor. */
    private function sprache(string $datei, \DOMXPath $x): void
    {
        $lang = trim((string) $x->document->documentElement?->getAttribute('lang'));
        if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $lang)) {
            $this->mangel('barrierefreiheit', $datei, "$datei: <html lang> fehlt oder ist kein Sprachkürzel („{$lang}“)",
                $this->wo($datei) . ': Die Sprache der Seite ist nicht angegeben — Bildschirmleser lesen den Text dann womöglich mit falscher Aussprache vor.');
        }
    }

    /**
     * Bildbeschreibungen (WCAG 1.1.1): jedes <img> hat alt. Leer darf es nur bei Schmuck sein — im Bauplan „dekorativ“, ein Bild, das der Bauplan
     * nicht kennt (alt="" ist dann die Angabe des Autors), oder ein Bild in einem Link oder Knopf, der schon einen Namen hat (Startbild des Videos).
     * Das Logo ist der Inhalt des Links zur Startseite und braucht immer eine Beschreibung. Ein Dateiname oder „Foto“ beschreibt nichts (F30).
     */
    private function bilder(string $datei, \DOMXPath $x): void
    {
        foreach ($x->query('//img') as $img) {
            $src = $img->getAttribute('src');
            $logo = $x->query('ancestor::a[' . sprintf(self::KLASSE, 'lp-logo') . ']', $img)->length > 0;
            $i = self::baustein($img);
            $imPlan = $i === null ? null : $this->suche($i, static fn (mixed $w): bool => is_array($w) && self::src($w['datei'] ?? null) === $src);
            $pfad = $logo ? 'profil.logo.alt' : ($imPlan !== null ? "$imPlan[0].alt" : ($i === null ? $datei : "bausteine.$i"));
            [$wo, $das, $des] = $logo ? ['Seitenkopf', 'Logo', 'des Logos'] : [$this->wo($pfad), 'Bild', 'des Bildes'];
            $tag = "$datei: <img src=\"$src\">";
            if (!$img->hasAttribute('alt')) {
                $this->mangel('barrierefreiheit', $pfad, "$tag ohne alt", "$wo: Dem $das fehlt die Beschreibung für Menschen, die es nicht sehen (Alternativtext).");
                continue;
            }
            $alt = self::eng($img->getAttribute('alt'));
            if ($alt === '') {
                $bedienung = $x->query('ancestor::a[@href]|ancestor::button', $img)->item(0);
                $schmuck = $bedienung instanceof \DOMElement ? self::name($x, $bedienung) !== ''
                    : $imPlan === null || ($imPlan[1]['dekorativ'] ?? false) === true;
                if (!$schmuck) {
                    $this->mangel('barrierefreiheit', $pfad, "$tag mit leerem alt, aber nicht als Schmuck gekennzeichnet",
                        "$wo: Dem $das fehlt die Beschreibung für Menschen, die es nicht sehen (Alternativtext).");
                }
                continue;
            }
            $kern = self::ohneRand($alt);
            if (preg_match(self::DATEINAME, $kern)) {
                $this->mangel('barrierefreiheit', $pfad, "$tag: alt ist ein Dateiname („{$alt}“)",
                    "$wo: Als Beschreibung $des steht nur ein Dateiname („{$alt}“) — bitte in Worten sagen, was zu sehen ist.");
            } elseif (preg_match(self::NUR_BILD, $kern)) {
                $this->mangel('barrierefreiheit', $pfad, "$tag: alt sagt nichts („{$alt}“)",
                    "$wo: Die Beschreibung „{$alt}“ sagt nicht, was zu sehen ist — bitte in Worten beschreiben.");
            } elseif (mb_strlen($alt) > self::ALT_LANG) {
                $this->hinweis('barrierefreiheit', $pfad, "$tag: alt hat " . mb_strlen($alt) . ' Zeichen (mehr als ' . self::ALT_LANG . ')',
                    "$wo: Die Beschreibung $des ist sehr lang — vorgelesen ermüdet sie; ein bis zwei kurze Sätze genügen.");
            }
        }
    }

    /** Überschriften: genau eine H1 (Mangel, wie im Prüfer), keine übersprungene Ebene (Hinweis, wie im Prüfer). */
    private function ueberschriften(string $datei, \DOMXPath $x): void
    {
        if (($h1 = $x->query('//h1')->length) !== 1) {
            $this->mangel('barrierefreiheit', $datei, "$datei: genau eine H1 erwartet, $h1 gefunden", $this->wo($datei) . ": Die Seite braucht genau eine Hauptüberschrift (gefunden: $h1).");
        }
        $vorige = 0;
        foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
            $stufe = (int) substr($h->nodeName, 1);
            if ($stufe > $vorige + 1) {
                $text = self::eng($h->textContent);
                $pfad = ($i = self::baustein($h)) === null ? $datei : $this->feld($i, $text);
                $this->hinweis('barrierefreiheit', $pfad, "$datei: Überschrift springt von H$vorige auf H$stufe („{$text}“)",
                    $this->wo($pfad) . ": Die Überschrift „{$text}“ überspringt eine Ebene — für Bildschirmleser bitte ohne Sprung gliedern.");
            }
            $vorige = $stufe;
        }
    }

    /** Links und Knöpfe: jeder hat einen Namen (WCAG 4.1.2, Mangel); „hier“ oder „mehr“ sagt allein nicht, wohin er führt (2.4.4, Hinweis). */
    private function bedienelemente(string $datei, \DOMXPath $x): void
    {
        foreach ($x->query('//a[@href]|//button') as $e) {
            $was = $e->nodeName === 'a' ? 'Link' : 'Knopf';
            $i = self::baustein($e);
            $name = self::name($x, $e);
            if ($name === '' && $x->query('.//img', $e)->length === 0) {   // mit Bild: meldet die Prüfung der Bildbeschreibungen
                $pfad = $i === null ? $datei : "bausteine.$i";
                $this->mangel('barrierefreiheit', $pfad, "$datei: $was ohne zugänglichen Namen (" . self::kurz($e) . ')',
                    $this->wo($pfad) . ": Ein $was hat keine Beschriftung — Bildschirmleser können nicht sagen, wozu er dient.");
            } elseif ($name !== '' && preg_match(self::NICHTSSAGEND, self::ohneRand($name))) {
                $pfad = $i === null ? $datei : $this->feld($i, self::eng($e->textContent));
                $this->hinweis('barrierefreiheit', $pfad, "$datei: nichtssagender $was-Text „{$name}“ (" . self::kurz($e) . ')',
                    $this->wo($pfad) . ": Der $was „{$name}“ sagt allein nicht, wohin er führt — bitte das Ziel nennen (z. B. „Zur Anmeldung“).");
            }
        }
    }

    /** Tastatur (WCAG 2.4.3): kein tabindex über 0 — so ein Element käme vor allem anderen dran, auch vor dem Sprunglink „Zum Inhalt springen“. */
    private function tabindex(string $datei, \DOMXPath $x): void
    {
        foreach ($x->query('//*[@tabindex]') as $e) {
            $wert = trim($e->getAttribute('tabindex'));
            if (preg_match('/^\+?\d+$/', $wert) && (int) $wert > 0) {
                $pfad = ($i = self::baustein($e)) === null ? $datei : "bausteine.$i";
                $this->mangel('barrierefreiheit', $pfad, "$datei: tabindex=\"$wert\" (" . self::kurz($e) . ')',
                    $this->wo($pfad) . ': Ein Element ändert die Reihenfolge beim Bedienen mit der Tastatur — es käme vor allem anderen dran.');
            }
        }
    }

    /**
     * Fokus sichtbar (WCAG 2.4.7, F78), einfache statische Prüfung von es/lp.css: Eine Regel für :focus, :focus-visible oder :focus-within, die den
     * Fokusrahmen wegnimmt (outline: none | 0), braucht Ersatz — in derselben Regel (ERSATZ) oder in einer Regel für denselben Selektor mit
     * :focus-visible statt :focus. Ausgenommen ist :focus:not(:focus-visible): Das nimmt den Rahmen nur bei der Maus weg (base.css).
     */
    private function fokus(string $css): void
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', (string) preg_replace('#/\*.*?\*/#s', '', $css), $m, PREG_SET_ORDER);
        $regeln = [];
        foreach ($m as [, $selektoren, $deklarationen]) {
            foreach (explode(',', $selektoren) as $s) {
                $regeln[] = [self::eng($s), $deklarationen];
            }
        }
        foreach ($regeln as [$s, $d]) {
            if (!str_contains($s, ':focus') || str_contains($s, ':not(:focus-visible)') || !preg_match(self::OHNE_RAHMEN, $d) || preg_match(self::ERSATZ, $d)) {
                continue;
            }
            $sichtbar = preg_replace('/:focus(?![\w-])/', ':focus-visible', $s);
            foreach ($regeln as [$s2, $d2]) {
                if ($s2 === $sichtbar && $s2 !== $s && (preg_match(self::ERSATZ, $d2) || (preg_match('/(?:^|;)\s*outline\s*:/i', $d2) && !preg_match(self::OHNE_RAHMEN, $d2)))) {
                    continue 2;
                }
            }
            $this->mangel('barrierefreiheit', 'es/lp.css', "es/lp.css: „{$s}“ nimmt den Fokusrahmen weg (outline: none oder 0), ohne Ersatz",
                'Gestaltung: Wer die Seite mit der Tastatur bedient, sieht an einer Stelle nicht, wo er gerade ist (der Fokusrahmen fehlt).');
        }
    }

    /**
     * Formulare (form.lp-formular): Ziel mit https, Link auf die Datenschutzerklärung im Formular oder in seinem Abschnitt, eine Pflicht-Einwilligung
     * als Häkchen, dazu die Felder (felder) und der Spam-Schutz (honigtopf).
     */
    private function formulare(string $datei, \DOMXPath $x): void
    {
        foreach ($x->query('//form[' . sprintf(self::KLASSE, 'lp-formular') . ']') as $form) {
            $pfad = ($i = self::baustein($form)) === null ? $datei : "bausteine.$i";
            $wo = $this->wo($pfad);
            $ziel = trim($form->getAttribute('action'));
            if (!str_starts_with(strtolower($ziel), 'https://')) {
                $this->mangel('formular', 'profil.formularziel', "$datei: Formularziel ist nicht https („{$ziel}“)", "$wo: " . ($ziel === ''
                    ? 'Das Formular hat noch kein Ziel — Anmeldungen kämen nirgends an.'
                    : 'Das Formular schickt die Angaben ohne Verschlüsselung — das Ziel muss eine sichere Adresse (https) sein.'));
            }
            if ($x->query('.//a[starts-with(@href, "datenschutz.html")]', self::abschnitt($form))->length === 0) {
                $this->mangel('formular', $pfad, "$datei: kein Link auf datenschutz.html im Formular oder in seinem Abschnitt",
                    "$wo: Beim Formular fehlt der Hinweis auf die Datenschutzerklärung (Link) — wer sich anmeldet, muss sie dort finden.");
            }
            $einwilligung = $x->query('.//input[@name="einwilligung"]', $form)->item(0);
            if (!$einwilligung instanceof \DOMElement) {
                $this->mangel('formular', $pfad, "$datei: Pflicht-Einwilligung (name=\"einwilligung\") fehlt", "$wo: Im Formular fehlt das Häkchen für die Einwilligung.");
            } elseif (strtolower($einwilligung->getAttribute('type')) !== 'checkbox') {
                $this->mangel('formular', $pfad, "$datei: Einwilligung ist kein Häkchen (type=\"" . $einwilligung->getAttribute('type') . '")',
                    "$wo: Die Einwilligung muss ein Häkchen sein, das man selbst setzt.");
            }
            $this->felder($datei, $x, $form, $pfad, $wo);
            $this->honigtopf($datei, $x, $form, $pfad, $wo);
        }
    }

    /**
     * Felder (ohne versteckte und den Honigtopf): jedes beschriftet (<label for> oder umschließendes <label>); was nicht „freiwillig“ heißt, ist
     * Pflicht und trägt required (sonst sagt der Bildschirmleser es nicht an), was „freiwillig“ heißt, nie; kein Häkchen vorangehakt.
     */
    private function felder(string $datei, \DOMXPath $x, \DOMElement $form, string $pfad, string $wo): void
    {
        $labels = [];
        foreach ($x->query('.//label[@for]', $form) as $l) {
            $labels[$l->getAttribute('for')] ??= $l;
        }
        foreach ($x->query('.//input[not(@type="hidden" or @type="submit" or @type="button" or @type="reset" or @type="image")]|.//select|.//textarea', $form) as $f) {
            if (self::imHonigtopf($x, $f)) {
                continue;
            }
            $name = $f->getAttribute('name') ?: $f->getAttribute('id');
            if (in_array(strtolower($f->getAttribute('type')), ['checkbox', 'radio'], true) && $f->hasAttribute('checked')) {
                $this->mangel('formular', $pfad, "$datei: Feld „{$name}“ ist vorangehakt (checked)", "$wo: Ein Häkchen im Formular ist schon gesetzt — Einwilligungen setzt nur, wer sich anmeldet.");
            }
            $label = ($f->getAttribute('id') !== '' ? $labels[$f->getAttribute('id')] ?? null : null) ?? $x->query('ancestor::label', $f)->item(0);
            $text = $label instanceof \DOMElement ? self::eng(self::text($label)) : '';
            if ($text === '') {
                $this->mangel('formular', $pfad, "$datei: Feld „{$name}“ ohne Beschriftung (<label for>)", "$wo: Ein Eingabefeld hat keine Beschriftung — Bildschirmleser können nicht sagen, was hineingehört.");
                continue;
            }
            $freiwillig = mb_stripos($text, 'freiwillig') !== false;
            $pflicht = $f->hasAttribute('required') || $f->getAttribute('aria-required') === 'true';
            $kurz = mb_strimwidth($text, 0, 60, '…');
            if (!$freiwillig && !$pflicht) {
                $this->mangel('formular', $pfad, "$datei: Pflichtfeld „{$name}“ ohne required (Beschriftung ohne „freiwillig“)",
                    "$wo: Das Feld „{$kurz}“ ist Pflicht, aber nicht so ausgezeichnet — Bildschirmleser sagen es nicht an. Ist es freiwillig, gehört „(freiwillig)“ in die Beschriftung.");
            } elseif ($freiwillig && $pflicht) {
                $this->mangel('formular', $pfad, "$datei: Feld „{$name}“ heißt freiwillig, ist aber required", "$wo: Das Feld „{$kurz}“ heißt freiwillig, lässt sich aber nicht leer lassen.");
            }
        }
    }

    /** Spam-Schutz: ein Honigtopf (.form-hp, name="website"), versteckt vor Bildschirmlesern (aria-hidden) und der Tastatur (tabindex="-1"). */
    private function honigtopf(string $datei, \DOMXPath $x, \DOMElement $form, string $pfad, string $wo): void
    {
        $feld = $x->query('.//*[' . sprintf(self::KLASSE, 'form-hp') . ']//input|.//input[@name="website"]', $form)->item(0);
        if (!$feld instanceof \DOMElement) {
            $this->mangel('formular', $pfad, "$datei: Honigtopf (.form-hp, name=\"website\") fehlt", "$wo: Dem Formular fehlt der Schutz gegen automatische Anmeldungen.");
        } elseif ($x->query('ancestor-or-self::*[@aria-hidden="true"]', $feld)->length === 0 || trim($feld->getAttribute('tabindex')) !== '-1') {
            $this->mangel('formular', $pfad, "$datei: Honigtopf für Tastatur oder Bildschirmleser erreichbar (nötig: aria-hidden=\"true\" und tabindex=\"-1\")",
                "$wo: Das versteckte Schutzfeld gegen automatische Anmeldungen ist mit Tastatur oder Bildschirmleser erreichbar — wer es ausfüllt, dessen Anmeldung gilt als Spam.");
        }
    }

    private static function imHonigtopf(\DOMXPath $x, \DOMElement $f): bool
    {
        return $f->getAttribute('name') === 'website' || $x->query('ancestor::*[' . sprintf(self::KLASSE, 'form-hp') . ']', $f)->length > 0;
    }

    /** Wo der Punkt für den Kunden steht: „Abschnitt 2 „Themen“, Eintrag 1“, die Rechtsseiten, das Kundenprofil, die Gestaltung. */
    private function wo(string $pfad): string
    {
        $t = explode('.', $pfad);
        if ($t[0] === 'bausteine' && isset($t[1]) && ctype_digit($t[1])) {
            $typ = (string) ($this->plan['bausteine'][(int) $t[1]]['typ'] ?? '');
            $this->katalog ??= Katalog::laden();
            $wo = 'Abschnitt ' . ((int) $t[1] + 1) . ' „' . ($this->katalog->baustein($typ)['name'] ?? $typ) . '“';
            return isset($t[3]) && ctype_digit($t[3]) ? "$wo, Eintrag " . ((int) $t[3] + 1) : $wo;
        }
        return match ($pfad) {
            'impressum.html' => 'Impressum',
            'datenschutz.html' => 'Datenschutzerklärung',
            default => str_starts_with($pfad, 'profil.') ? 'Kundenprofil' : 'Seite',
        };
    }

    /** Index des Bausteins, in dessen Abschnitt das Element steht (<section aria-labelledby="lp-aN-titel">, Kontext::oeffnen), sonst null. */
    private static function baustein(\DOMElement $e): ?int
    {
        $s = self::abschnitt($e);
        return $s->nodeName === 'section' && preg_match('/^lp-a(\d+)-titel$/', $s->getAttribute('aria-labelledby'), $m) ? (int) $m[1] - 1 : null;
    }

    /** Der Abschnitt (<section>) um das Element, ohne einen das Element selbst. */
    private static function abschnitt(\DOMElement $e): \DOMElement
    {
        for ($n = $e; $n instanceof \DOMElement; $n = $n->parentNode) {
            if ($n->nodeName === 'section') {
                return $n;
            }
        }
        return $e;
    }

    /**
     * Pfad und Wert der ersten Stelle im Baustein $i, auf die $passt zutrifft (Tiefensuche in der Reihenfolge des Bauplans).
     *
     * @return ?array{0: string, 1: mixed}
     */
    private function suche(int $i, callable $passt): ?array
    {
        $tiefe = static function (mixed $w, string $pfad) use (&$tiefe, $passt): ?array {
            if ($passt($w)) {
                return [$pfad, $w];
            }
            foreach (is_array($w) ? $w : [] as $s => $x) {
                if (($t = $tiefe($x, "$pfad.$s")) !== null) {
                    return $t;
                }
            }
            return null;
        };
        return $tiefe($this->plan['bausteine'][$i] ?? null, "bausteine.$i");
    }

    /** Das Textfeld des Bausteins $i, das genau $text enthält (Knopftext, Titel), sonst der Baustein. */
    private function feld(int $i, string $text): string
    {
        return $this->suche($i, static fn (mixed $w): bool => $text !== '' && is_string($w) && self::eng($w) === $text)[0] ?? "bausteine.$i";
    }

    /** Wo ein Bild des Bauplans auf der Seite steht (Kontext::bild): bestand/<name> als es/bilder/<name>, img/<name> bleibt. */
    private static function src(mixed $datei): ?string
    {
        if (!is_string($datei)) {
            return null;
        }
        return str_starts_with($datei, 'bestand/') ? 'es/bilder/' . substr($datei, 8) : (str_starts_with($datei, 'img/') ? $datei : null);
    }

    /** Zugänglicher Name, vereinfacht wie im Browser: aria-labelledby, aria-label, Inhalt samt Alt-Texten, title. */
    private static function name(\DOMXPath $x, \DOMElement $e): string
    {
        $bezug = '';
        foreach (preg_split('/\s+/', trim($e->getAttribute('aria-labelledby')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
            $ziel = preg_match('/^[\w:.-]+$/', $id) ? $x->query("//*[@id=\"$id\"]")->item(0) : null;
            $bezug .= $ziel instanceof \DOMNode ? ' ' . self::text($ziel) : '';
        }
        foreach ([$bezug, $e->getAttribute('aria-label'), self::text($e), $e->getAttribute('title')] as $kandidat) {
            if (($n = self::eng($kandidat)) !== '') {
                return $n;
            }
        }
        return '';
    }

    /** Text eines Knotens, Bilder mit ihrem Alt-Text. */
    private static function text(\DOMNode $n): string
    {
        if ($n instanceof \DOMText) {
            return $n->data;
        }
        if ($n instanceof \DOMElement && $n->nodeName === 'img') {
            return ' ' . $n->getAttribute('alt') . ' ';
        }
        $s = '';
        foreach ($n->childNodes as $k) {
            $s .= self::text($k);
        }
        return $s;
    }

    /** Kurzform eines Elements für den Technik-Text: <a href="…" class="…">. */
    private static function kurz(\DOMElement $e): string
    {
        $s = '<' . $e->nodeName;
        foreach (['href', 'class'] as $a) {
            $s .= $e->hasAttribute($a) ? " $a=\"" . $e->getAttribute($a) . '"' : '';
        }
        return "$s>";
    }

    private static function eng(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** Ohne Satzzeichen und Zeichen am Rand („Mehr »“ → „Mehr“). */
    private static function ohneRand(string $s): string
    {
        return (string) preg_replace('/^[\s\p{P}\p{S}]+|[\s\p{P}\p{S}]+$/u', '', $s);
    }

    private static function lesen(string $seite, string $datei): string
    {
        $inhalt = is_file("$seite/$datei") ? file_get_contents("$seite/$datei") : false;
        return $inhalt !== false ? $inhalt : throw new \RuntimeException("$datei fehlt — die Seite ist nicht gebaut (php lp/bauen.php)");
    }

    private static function dom(string $html): \DOMDocument
    {
        $d = new \DOMDocument();
        $vorher = libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($vorher);
        return $d;
    }

    private function mangel(string $bereich, string $pfad, string $technik, string $kunde): void
    {
        $this->punkt('mangel', $bereich, $pfad, $technik, $kunde);
    }

    private function hinweis(string $bereich, string $pfad, string $technik, string $kunde): void
    {
        $this->punkt('hinweis', $bereich, $pfad, $technik, $kunde);
    }

    private function punkt(string $art, string $bereich, string $pfad, string $technik, string $kunde): void
    {
        if (!isset($this->gesehen[$schluessel = "$art|$bereich|$pfad|$kunde"])) {
            $this->gesehen[$schluessel] = true;
            $this->punkte[] = new Pruefpunkt($art, $bereich, $pfad, $technik, $kunde);
        }
    }
}
