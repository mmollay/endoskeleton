<?php
/**
 * Qualitätsboden (3.70.0) — der statische Teil; das Verhalten im Browser (hell und dunkel, 320 bis 1440 px, Tastatur, weniger Bewegung,
 * Druck) prüft ci/browser.js für jede Seite des Musterbuchs.
 *
 *   QB01  Druck: lp.css setzt die Farben des Profils für Papier um (Flächen durchsichtig, Schrift erbt das Schwarz von base.css), ohne feste Farbe
 *   QB02  Druck: lp.js klappt die Fragen für den Druck auf und danach wieder zu
 *   QB03  srcset: ein großes Bild bekommt 480er- und 960er-Fassungen (WebP, richtige Maße, leichter) mit sizes je Verwendung; ein leichtes keine
 *   QB04  srcset: schmal, flach (unter 50 KB) oder über 16 MP: keine Fassung; Bilder aus img/ und bestand/ kommen sich nie in die Quere
 *   QB05  srcset: ein Neubau in einem eigenen Prozess = dieselben Bytes; der Prüfer prüft jede Fassung wie ein Bild, das Original nicht doppelt
 *   QB06  Prüfliste: musterbuch/pruefliste.html ist aus lp/pruefliste.json gebaut, jeder Punkt nennt eine Prüfung, die es gibt
 *   QB07  srcset: durchsichtige Bilder bleiben durchsichtig, ohne Farbsaum an der Kante
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
        T::ok(preg_match('/\.lp-formular fieldset,[^{]*\{\s*display:\s*none;/', $druck) === 1, 'Formularfelder und Bedienelemente entfallen (Meldungen bleiben)');
        T::ok(preg_match('/\.lp-bewegung-figur canvas\s*\{[^}]*print-color-adjust:\s*exact;/', $druck) === 1, 'die Leinwand der Bewegung behält ihren Grund im Druck');
        T::ok(preg_match('/:root\s*\{\s*--lp-figur-grund:\s*var\(--heading\);\s*--lp-figur-farbe:\s*var\(--bg\);/', $css) === 1, 'Grund und Farbe der Figur an :root aus dem Profil (nicht vom Druck durchsichtig gemacht)');
        T::ok(preg_match('/\.lp-bewegung-figur canvas\s*\{[^}]*background:\s*var\(--lp-figur-grund\);[^}]*color:\s*var\(--lp-figur-farbe\);/', $css) === 1, 'die Leinwand nutzt sie');
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
    'QB03 srcset: großes Bild mit 480er- und 960er-Fassung, sizes je Verwendung' => static function () use ($bau, $hero): void {
        T::ok(Bildgroessen::moeglich(), 'GD kann WebP (sonst baut die Seite ohne srcset)');
        $seite = H::seite([['typ' => 'person', 'variante' => 'bild-links', 'titel' => 'Wer spricht?', 'name' => 'Anna Beispiel', 'bild' => ['datei' => 'bestand/beispiel-landschaft.webp']]]);
        $seite['bausteine'][0] = $hero('bild-rechts', 'bestand/beispiel-landschaft.webp');
        [$o, $x] = $bau($seite);
        $img = $x->query('//img[contains(@class,"lp-hero-bild")]')->item(0);
        T::ok($img instanceof DOMElement, 'Bild im Kopfbereich');
        T::eq($img?->getAttribute('srcset'), 'es/groessen/bestand-beispiel-landschaft-480w.webp 480w, es/groessen/bestand-beispiel-landschaft-960w.webp 960w, es/bilder/beispiel-landschaft.webp 1600w', 'srcset: zwei Fassungen und das Original');
        T::eq($img?->getAttribute('sizes'), Kontext::GROESSEN['halb'], 'sizes: Bildspalte neben dem Text');
        T::eq($img?->getAttribute('src'), 'es/bilder/beispiel-landschaft.webp', 'src bleibt das Original (ohne srcset-Unterstützung)');
        T::eq([$img?->getAttribute('width'), $img?->getAttribute('height')], ['1600', '900'], 'Maße des Originals gegen Verschieben');
        $original = filesize("$o/es/bilder/beispiel-landschaft.webp");
        foreach ([480 => 270, 960 => 540] as $b => $h) {
            $f = "$o/es/groessen/bestand-beispiel-landschaft-{$b}w.webp";
            $m = @getimagesize($f);
            T::ok($m !== false && $m[0] === $b && $m[1] === $h && $m[2] === IMAGETYPE_WEBP, "Fassung {$b}w ist WebP {$b}×{$h}");
            T::ok(is_file($f) && filesize($f) < $original, "Fassung {$b}w ist leichter als das Original");
        }
        $person = $x->query('//img[contains(@class,"lp-person-bild")]')->item(0);
        T::eq($person?->getAttribute('sizes'), Kontext::GROESSEN['klein'], 'Person: kleines Bild (12 rem)');
        T::contains((string) $person?->getAttribute('srcset'), 'es/groessen/bestand-beispiel-landschaft-480w.webp 480w', 'Person: dieselben Fassungen, einmal abgelegt');
        T::eq($person?->getAttribute('loading'), 'lazy', 'Person: lädt erst, wenn es ins Bild kommt');
        [, $x2] = $bau(['format' => 1, 'meta' => ['titel' => 'T', 'beschreibung' => 'B'], 'bausteine' => [$hero('bild-hinten', 'bestand/beispiel-landschaft.webp'), H::seite()['bausteine'][1]]]);
        T::eq($x2->query('//img[contains(@class,"lp-hero-bild")]')->item(0)?->getAttribute('sizes'), Kontext::GROESSEN['voll'], 'Bild hinter dem Kopfbereich: volle Breite');
        [, $x3] = $bau(H::seite());
        T::ok(!$x3->query('//img[contains(@class,"lp-video-bild")]')->item(0)?->hasAttribute('srcset'), 'kleine Datei (Testbild 4 KB, 1200 px breit): keine Fassung — unter 50 KB lohnt sie nicht');
    },
    'QB04 srcset: schmales oder leichtes Bild ohne Fassung; img/ und bestand/ getrennt' => static function () use ($bau, $hero, $webp): void {
        $seite = H::seite();
        $seite['bausteine'][0] = $hero('bild-rechts', 'img/schmal.webp', ['alt' => 'Ein schmales Bild']);
        [$o, $x] = $bau($seite, static fn (string $o) => @mkdir("$o/img", 0700, true) && imagewebp(imagescale(imagecreatefromwebp(H::testprofil() . '/bilder/beispiel-landschaft.webp'), 560), "$o/img/schmal.webp", 100));
        T::ok(filesize("$o/img/schmal.webp") >= Bildgroessen::MIN_BYTES, 'Probebild schwer genug (sonst prüfte der Fall nur die Bytes)');
        $img = $x->query('//img[contains(@class,"lp-hero-bild")]')->item(0);
        T::ok($img instanceof DOMElement && !$img->hasAttribute('srcset') && !$img->hasAttribute('sizes'), '560 px: keine Fassung (480 wäre weniger als ein Fünftel schmaler)');
        T::ok(!is_dir("$o/es/groessen"), 'kein Ordner es/groessen');
        $seite['bausteine'][0] = $hero('bild-rechts', 'img/flach.webp', ['alt' => 'Eine flache Grafik']);
        [, $xf] = $bau($seite, static fn (string $o) => $webp("$o/img/flach.webp", 1600, 900));
        T::ok(!$xf->query('//img[contains(@class,"lp-hero-bild")]')->item(0)?->hasAttribute('srcset'), '1600 px, aber flache Grafik unter 50 KB: keine Fassung');
        $seite = H::seite([['typ' => 'person', 'variante' => 'bild-links', 'titel' => 'Wer spricht?', 'name' => 'Anna Beispiel', 'bild' => ['datei' => 'bestand/beispiel-landschaft.webp']]]);
        $seite['bausteine'][0] = $hero('bild-rechts', 'img/beispiel-landschaft.webp', ['alt' => 'Gleicher Name wie im Bestand']);
        [$o2, $x2] = $bau($seite, static fn (string $o) => @mkdir("$o/img", 0700, true) && copy(H::testprofil() . '/bilder/beispiel-landschaft.webp', "$o/img/beispiel-landschaft.webp"));
        T::contains((string) $x2->query('//img[contains(@class,"lp-hero-bild")]')->item(0)?->getAttribute('srcset'), 'es/groessen/img-beispiel-landschaft-960w.webp 960w, img/beispiel-landschaft.webp 1600w', 'img/: eigene Fassungen, Original bleibt in img/');
        T::contains((string) $x2->query('//img[contains(@class,"lp-person-bild")]')->item(0)?->getAttribute('srcset'), 'es/groessen/bestand-beispiel-landschaft-960w.webp 960w', 'bestand/: eigene Fassungen');
        T::ok(is_file("$o2/es/groessen/img-beispiel-landschaft-960w.webp") && is_file("$o2/es/groessen/bestand-beispiel-landschaft-960w.webp"), 'beide abgelegt, keine überschreibt die andere');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o2, QB_HEUTE))), '', 'Prüfer ohne sperrenden Befund');
        T::eq(Bildgroessen::fassungen(H::testprofil() . '/bilder/beispiel-landschaft.webp', [5000, 4000, IMAGETYPE_WEBP]), [], 'über 16 Millionen Bildpunkte: keine Fassung (Speicher und Zeit im Panel)');
    },
    'QB05 srcset: ein Neubau in einem anderen Prozess ergibt dieselben Bytes; der Prüfer prüft jede Fassung wie ein Bild' => static function () use ($bau, $hero): void {
        $seite = H::seite();
        $seite['bausteine'][0] = $hero('bild-rechts', 'bestand/beispiel-landschaft.webp');
        [$o] = $bau($seite);
        $vorher = (string) file_get_contents("$o/es/groessen/bestand-beispiel-landschaft-480w.webp");
        // eigener Prozess (wie im Panel: bauen.php und pruefen.php laufen getrennt) — ohne den Zwischenspeicher dieses Laufs
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(H::wurzel() . '/lp/bauen.php') . ' ' . escapeshellarg($o) . ' 2>&1', $aus, $rc);
        T::eq($rc, 0, 'Neubau im eigenen Prozess: ' . implode(' ', $aus));
        T::ok((string) file_get_contents("$o/es/groessen/bestand-beispiel-landschaft-480w.webp") === $vorher, 'gleiche Bytes beim Neubau');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o, QB_HEUTE))), '', 'frisch gebaut: Prüfer ohne sperrenden Befund');
        $png = imagecreatetruecolor(480, 270);
        ob_start(); imagepng($png); file_put_contents("$o/es/groessen/bestand-beispiel-landschaft-480w.webp", (string) ob_get_clean());
        $befunde = H::texte(H::sperrend(Pruefer::pruefen($o, QB_HEUTE)));
        T::contains($befunde, 'es/groessen/bestand-beispiel-landschaft-480w.webp ist kein WebP', 'eine Fassung, die kein WebP ist, sperrt');
        T::contains($befunde, 'gebaute Dateien passen nicht zu seite.json: es/groessen/bestand-beispiel-landschaft-480w.webp', 'und fällt beim Nachbau auf');
        T::eq(substr_count($befunde, 'es/bilder/beispiel-landschaft.webp'), 0, 'das Original im srcset wird nicht doppelt gemeldet');
    },
    'QB07 srcset: durchsichtige Bilder bleiben durchsichtig, ohne Farbsaum an der Kante' => static function (): void {
        $d = T::tmp();
        $b = imagecreatetruecolor(1600, 900);
        imagealphablending($b, false);
        imagesavealpha($b, true);
        mt_srand(7);
        for ($y = 0; $y < 900; $y++) {
            for ($x = 0; $x < 1600; $x++) {
                // links durchsichtiges Rot (soll nie durchscheinen), rechts deckendes, verrauschtes Blau
                imagesetpixel($b, $x, $y, $x < 800 ? imagecolorallocatealpha($b, 255, 0, 0, 127) : (mt_rand(0, 40) << 8) | (180 + mt_rand(0, 60)));
            }
        }
        imagewebp($b, "$d/kante.webp", 90);
        $fassungen = Bildgroessen::fassungen("$d/kante.webp", getimagesize("$d/kante.webp"));
        T::ok(isset($fassungen[480]), 'Fassung 480w erzeugt (' . filesize("$d/kante.webp") . ' Bytes)');
        $k = imagecreatefromstring($fassungen[480] ?? '');
        $rot = 0;
        $durchsichtig = true;
        for ($x = 0; $k !== false && $x < 480; $x++) {
            $p = imagecolorsforindex($k, imagecolorat($k, $x, 135));
            if ($p['alpha'] < 120 && $p['red'] > 40) { $rot++; }
            if ($x < 230 && $p['alpha'] < 120) { $durchsichtig = false; }
        }
        T::ok($durchsichtig, 'links bleibt durchsichtig');
        T::eq($rot, 0, 'kein Rot aus der durchsichtigen Fläche in sichtbaren Bildpunkten');
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
            foreach ($p['pruefungen'] as $eintrag) {
                // „<Datei> [<Name der Prüfung>]“: die Datei gibt es, und der Name steht darin (QB01, „Tastatur: Sprunglink zuerst“ …)
                [$datei, $name] = array_pad(explode(' ', $eintrag, 2), 2, '');
                T::ok(is_file("$w/$datei"), "Prüfung gibt es: $datei");
                T::ok($name === '' || str_contains((string) @file_get_contents("$w/$datei"), $name), "„{$name}“ steht in $datei");
            }
        }
    },
];
