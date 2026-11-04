<?php
/**
 * Qualitätsboden (3.70.0) — der statische Teil; das Verhalten im Browser (hell und dunkel, 320 bis 1440 px, Tastatur, weniger Bewegung,
 * Druck) prüft ci/browser.js für jede Seite des Musterbuchs.
 *
 *   QB01  Druck: lp.css setzt die Farben des Profils für Papier um (Flächen durchsichtig, Schrift erbt das Schwarz von base.css), ohne feste Farbe
 *   QB02  Druck: lp.js klappt die Fragen für den Druck auf und danach wieder zu
 *   QB03  srcset: ein breites Bild bekommt 480er- und 960er-Fassungen (WebP, richtige Maße) mit sizes je Verwendung
 *   QB04  srcset: ein schmales Bild bekommt keine Fassung und kein srcset; Bilder aus img/ und bestand/ kommen sich nie in die Quere
 *   QB05  srcset: zweimal bauen = dieselben Bytes; der Prüfer prüft jede Fassung wie ein Bild
 *   QB06  Prüfliste: musterbuch/pruefliste.html ist aus lp/pruefliste.json gebaut, jeder Punkt nennt eine Prüfung, die es gibt
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Bildgroessen;
use Endo\Lp\Kontext;
use Endo\Lp\Pruefer;

const QB_HEUTE = '2026-09-27T12:00';

/** Baut die Seite und gibt [Ordner, XPath von index.html] zurück. */
$bau = static function (array $seite, ?callable $vorher = null): array {
    $o = H::ordner($seite);
    if ($vorher) { $vorher($o); }
    $e = Bauer::bauen($o);
    T::eq(H::texte(H::sperrend($e->befunde)), '', 'baut');
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . (string) @file_get_contents("$o/index.html"));
    libxml_clear_errors();
    return [$o, new DOMXPath($d)];
};
$hero = static fn (string $variante, string $datei, array $mehr = []): array => [
    'typ' => 'hero', 'variante' => $variante, 'titel' => 'Gesund durch den Winter', 'bild' => ['datei' => $datei] + $mehr,
    'knopf' => ['text' => 'Zur Anmeldung', 'ziel' => '#anmeldung'],
];
/** Ein WebP-Bild in der gewünschten Breite (GD), z. B. für img/ im Seitenordner. */
$webp = static function (string $pfad, int $breite, int $hoehe): void {
    $b = imagecreatetruecolor($breite, $hoehe);
    imagefill($b, 0, 0, imagecolorallocate($b, 40, 90, 140));
    imagefilledrectangle($b, (int) ($breite / 4), (int) ($hoehe / 4), (int) ($breite / 2), (int) ($hoehe / 2), imagecolorallocate($b, 230, 200, 60));
    @mkdir(dirname($pfad), 0700, true);
    imagewebp($b, $pfad, 80);
};
$abschnitt = static function (string $css, string $name): string {
    $start = strpos($css, "/* ─── $name ");
    if ($start === false) { return ''; }
    $ende = strpos($css, '/* ─── ', $start + 10);
    return substr($css, $start, $ende === false ? null : $ende - $start);
};

return [
    'QB01 Druck: Farben des Profils für Papier, Knöpfe ohne Fläche, Bedienelemente aus — ohne feste Farbe' => static function () use ($abschnitt): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        $druck = $abschnitt($css, 'DRUCK');
        T::ok($druck !== '' && str_contains($druck, '@media print'), 'Abschnitt DRUCK mit @media print');
        foreach (['--bg', '--bg-alt', '--card-bg', '--lp-akzent'] as $t) {
            T::ok(preg_match('/' . preg_quote($t, '/') . ':\s*transparent;/', $druck) === 1, "$t durchsichtig (der Browser druckt keine Hintergründe)");
        }
        foreach (['--heading', '--text', '--text-muted', '--primary', '--primary-dark', '--error', '--border'] as $t) {
            T::ok(preg_match('/' . preg_quote($t, '/') . ':\s*currentColor;/', $druck) === 1, "$t erbt die Druckfarbe");
        }
        T::ok(preg_match('/\.lp-seite \.btn\s*\{[^}]*background:\s*transparent;[^}]*color:\s*inherit;[^}]*border:[^}]*currentColor/', $druck) === 1, 'Knöpfe ohne Fläche, mit Rahmen in Schriftfarbe');
        T::ok(preg_match('/\.lp-formular,[^{]*\{\s*display:\s*none;/', $druck) === 1, 'Formular und Bedienelemente entfallen');
        T::ok(preg_match('/a\[href\]:not\(\[href\^="https:"\]\)::after\s*\{\s*content:\s*none;/', $druck) === 1, 'Adresse hinter dem Link nur bei https (nicht hinter Logo, Rechtsseiten, mailto)');
        T::ok(preg_match('/break-inside:\s*avoid/', $druck) === 1, 'Karten brechen nicht mitten durch');
        $ohneKommentar = (string) preg_replace('#/\*.*?\*/#s', '', $druck);
        T::ok(!preg_match('/#[0-9a-f]{3,8}\b|\b(white|black)\b/i', $ohneKommentar), 'keine feste Farbe (das Schwarz kommt aus base.css)');
        T::contains((string) file_get_contents(H::wurzel() . '/base.css'), "body {\n    color: #000000;\n    background: #ffffff;", 'base.css druckt schwarz auf weiß');
    },
    'QB02 Druck: lp.js klappt die Fragen auf und danach wieder zu' => static function (): void {
        $js = (string) file_get_contents(H::wurzel() . '/lp/lp.js');
        T::contains($js, "window.addEventListener('beforeprint'", 'vor dem Druck');
        T::contains($js, "document.querySelectorAll('details:not([open])')", 'nur die zugeklappten');
        T::contains($js, "window.addEventListener('afterprint'", 'nach dem Druck');
        T::contains($js, 'zugeklappt.forEach(function (d) { d.open = false; });', 'genau die wieder zu, die vorher zu waren');
    },
    'QB03 srcset: breites Bild mit 480er- und 960er-Fassung, sizes je Verwendung' => static function () use ($bau, $hero): void {
        T::ok(Bildgroessen::moeglich(), 'GD kann WebP (sonst baut die Seite ohne srcset)');
        [$o, $x] = $bau(H::seite([['typ' => 'person', 'variante' => 'bild-links', 'titel' => 'Wer spricht?', 'name' => 'Anna Beispiel', 'bild' => ['datei' => 'bestand/beispiel-video.webp']]]));
        $img = $x->query('//img[contains(@class,"lp-hero-bild")]')->item(0);
        T::ok($img instanceof DOMElement, 'Bild im Kopfbereich');
        T::eq($img?->getAttribute('srcset'), 'es/groessen/bestand-beispiel-vortrag-480w.webp 480w, es/groessen/bestand-beispiel-vortrag-960w.webp 960w, es/bilder/beispiel-vortrag.webp 1200w', 'srcset: zwei Fassungen und das Original');
        T::eq($img?->getAttribute('sizes'), Kontext::GROESSEN['halb'], 'sizes: Bildspalte neben dem Text');
        T::eq($img?->getAttribute('src'), 'es/bilder/beispiel-vortrag.webp', 'src bleibt das Original (ohne srcset-Unterstützung)');
        T::eq([$img?->getAttribute('width'), $img?->getAttribute('height')], ['1200', '800'], 'Maße des Originals gegen Verschieben');
        foreach ([480 => 320, 960 => 640] as $b => $h) {
            $m = @getimagesize("$o/es/groessen/bestand-beispiel-vortrag-{$b}w.webp");
            T::ok($m !== false && $m[0] === $b && $m[1] === $h && $m[2] === IMAGETYPE_WEBP, "Fassung {$b}w ist WebP {$b}×{$h}");
        }
        $person = $x->query('//img[contains(@class,"lp-person-bild")]')->item(0);
        T::eq($person?->getAttribute('sizes'), Kontext::GROESSEN['klein'], 'Person: kleines Bild (12 rem)');
        T::contains((string) $person?->getAttribute('srcset'), 'es/groessen/bestand-beispiel-video-480w.webp 480w', 'Person: Fassungen ihres eigenen Bildes');
        T::eq($person?->getAttribute('loading'), 'lazy', 'Person: lädt erst, wenn es ins Bild kommt');
        [, $x2] = $bau(['format' => 1, 'meta' => ['titel' => 'T', 'beschreibung' => 'B'], 'bausteine' => [$hero('bild-hinten', 'bestand/beispiel-vortrag.webp'), H::seite()['bausteine'][1]]]);
        T::eq($x2->query('//img[contains(@class,"lp-hero-bild")]')->item(0)?->getAttribute('sizes'), Kontext::GROESSEN['voll'], 'Bild hinter dem Kopfbereich: volle Breite');
    },
    'QB04 srcset: schmales Bild ohne Fassung; img/ und bestand/ getrennt' => static function () use ($bau, $hero, $webp): void {
        $seite = H::seite();
        $seite['bausteine'][0] = $hero('bild-rechts', 'img/schmal.webp', ['alt' => 'Ein schmales Bild']);
        [$o, $x] = $bau($seite, static fn (string $o) => $webp("$o/img/schmal.webp", 560, 400));
        $img = $x->query('//img[contains(@class,"lp-hero-bild")]')->item(0);
        T::ok($img instanceof DOMElement && !$img->hasAttribute('srcset') && !$img->hasAttribute('sizes'), '560 px: keine Fassung (480 wäre weniger als ein Fünftel schmaler)');
        T::ok(!is_dir("$o/es/groessen"), 'kein Ordner es/groessen');
        $seite = H::seite([['typ' => 'person', 'variante' => 'bild-links', 'titel' => 'Wer spricht?', 'name' => 'Anna Beispiel', 'bild' => ['datei' => 'bestand/beispiel-vortrag.webp']]]);
        $seite['bausteine'][0] = $hero('bild-rechts', 'img/beispiel-vortrag.webp', ['alt' => 'Gleicher Name wie im Bestand']);
        [$o2, $x2] = $bau($seite, static fn (string $o) => $webp("$o/img/beispiel-vortrag.webp", 1600, 900));
        T::contains((string) $x2->query('//img[contains(@class,"lp-hero-bild")]')->item(0)?->getAttribute('srcset'), 'es/groessen/img-beispiel-vortrag-960w.webp 960w, img/beispiel-vortrag.webp 1600w', 'img/: eigene Fassungen, Original bleibt in img/');
        T::contains((string) $x2->query('//img[contains(@class,"lp-person-bild")]')->item(0)?->getAttribute('srcset'), 'es/groessen/bestand-beispiel-vortrag-960w.webp 960w', 'bestand/: eigene Fassungen');
        T::eq(@getimagesize("$o2/es/groessen/img-beispiel-vortrag-960w.webp")[1] ?? null, 540, 'img/: Seitenverhältnis erhalten (1600×900 → 960×540)');
        T::eq(@getimagesize("$o2/es/groessen/bestand-beispiel-vortrag-960w.webp")[1] ?? null, 640, 'bestand/: Seitenverhältnis erhalten (1200×800 → 960×640)');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o2, QB_HEUTE))), '', 'Prüfer ohne sperrenden Befund');
    },
    'QB05 srcset: zweimal bauen = dieselben Bytes; der Prüfer prüft jede Fassung wie ein Bild' => static function () use ($bau): void {
        [$o] = $bau(H::seite());
        $vorher = (string) file_get_contents("$o/es/groessen/bestand-beispiel-vortrag-480w.webp");
        Bauer::bauen($o);
        T::ok((string) file_get_contents("$o/es/groessen/bestand-beispiel-vortrag-480w.webp") === $vorher, 'gleiche Bytes beim Neubau');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o, QB_HEUTE))), '', 'frisch gebaut: Prüfer ohne sperrenden Befund');
        $png = imagecreatetruecolor(480, 320);
        ob_start(); imagepng($png); file_put_contents("$o/es/groessen/bestand-beispiel-vortrag-480w.webp", (string) ob_get_clean());
        $befunde = H::texte(H::sperrend(Pruefer::pruefen($o, QB_HEUTE)));
        T::contains($befunde, 'es/groessen/bestand-beispiel-vortrag-480w.webp ist kein WebP', 'eine Fassung, die kein WebP ist, sperrt');
        T::contains($befunde, 'gebaute Dateien passen nicht zu seite.json: es/groessen/bestand-beispiel-vortrag-480w.webp', 'und fällt beim Nachbau auf');
    },
    'QB06 Prüfliste im Musterbuch: gebaut aus lp/pruefliste.json, jeder Punkt mit einer Prüfung, die es gibt' => static function (): void {
        $w = H::wurzel();
        $liste = json_decode((string) @file_get_contents("$w/lp/pruefliste.json"), true);
        T::ok(is_array($liste) && count($liste['punkte'] ?? []) >= 8, 'lp/pruefliste.json mit Punkten');
        $html = (string) @file_get_contents("$w/musterbuch/pruefliste.html");
        T::contains((string) @file_get_contents("$w/musterbuch/index.html"), 'href="pruefliste.html"', 'die Übersicht verlinkt die Prüfliste');
        foreach ($liste['punkte'] ?? [] as $p) {
            T::contains($html, Endo\Lp\Text::e($p['punkt']), 'Punkt steht in der Prüfliste: ' . $p['punkt']);
            T::ok(in_array($p['wie'], ['automatisch', 'von Hand'], true), 'automatisch oder von Hand: ' . $p['punkt']);
            T::ok($p['wie'] !== 'automatisch' || $p['pruefungen'] !== [], 'automatisch heißt: mit Prüfung — ' . $p['punkt']);
            foreach ($p['pruefungen'] as $datei) {
                T::ok(is_file("$w/" . strtok($datei, ' ')), "Prüfung gibt es: $datei");
            }
        }
    },
];
