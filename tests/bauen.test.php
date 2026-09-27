<?php
/** Der Bau einer Seite: vollständig, deterministisch, sauberes HTML, verweigert bei Fehlern (lp/src/Bauer.php). */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\BauFehler;
use Endo\Lp\Bauer;
use Endo\Lp\Katalog;

$k = Katalog::laden();
$alle = array_map(static fn (string $t): array => $k->baustein($t)['beispiel'], ['termin', 'themen', 'video', 'erfahrungen', 'faq', 'abschluss']);
$dateien = static function (string $d): array {
    $aus = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { $aus[substr($f->getPathname(), strlen($d) + 1)] = hash_file('sha256', $f->getPathname()); }
    ksort($aus);
    return $aus;
};
$dom = static function (string $html): DOMDocument {
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    return $d;
};
$gebaut = static function (array $seite): string {
    $o = H::ordner($seite);
    $e = Bauer::bauen($o);
    T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
    return $o;
};

return [
    'baut Seite, Rechtsseiten, robots.txt und es/' => static function () use ($gebaut, $alle): void {
        $o = $gebaut(H::seite($alle));
        foreach (['index.html', 'impressum.html', 'datenschutz.html', 'robots.txt', 'es/lp.css', 'es/lp.js', 'es/logo.svg',
                  'es/bilder/beispiel-vortrag.webp', 'es/bilder/beispiel-video.webp'] as $f) {
            T::ok(is_file("$o/$f"), "gebaut: $f");
        }
    },
    'zweimal bauen ergibt dieselben Bytes' => static function () use ($gebaut, $dateien, $alle): void {
        $o = $gebaut(H::seite($alle));
        $vorher = $dateien($o);
        sleep(1);
        Endo\Lp\Bauer::bauen($o);
        T::eq($dateien($o), $vorher, 'gleiche Dateien, gleiche Prüfsummen');
    },
    'genau eine H1, Überschriften ohne Sprung' => static function () use ($gebaut, $dom, $alle): void {
        $o = $gebaut(H::seite($alle));
        foreach (['index.html', 'impressum.html', 'datenschutz.html'] as $f) {
            $d = $dom((string) file_get_contents("$o/$f"));
            T::eq($d->getElementsByTagName('h1')->length, 1, "$f: eine H1");
            $x = new DOMXPath($d);
            $vorige = 0;
            foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
                $stufe = (int) substr($h->nodeName, 1);
                T::ok($stufe <= $vorige + 1, "$f: kein Sprung vor {$h->nodeName} „" . trim($h->textContent) . '"');
                $vorige = $stufe;
            }
        }
    },
    'kein style, kein Inline-Skript, keine on-Attribute, keine Entitäten für Umlaute' => static function () use ($gebaut, $alle): void {
        $o = $gebaut(H::seite($alle));
        foreach (['index.html', 'impressum.html', 'datenschutz.html'] as $f) {
            $h = (string) file_get_contents("$o/$f");
            T::ok(!preg_match('/\sstyle\s*=/i', $h), "$f: kein style=");
            T::notContains($h, '<style', "$f: kein <style>");
            T::ok(!preg_match('/<[^>]+\son[a-z]+\s*=/i', $h), "$f: keine on*-Attribute");
            preg_match_all('/<script\b([^>]*)>/i', $h, $m);
            foreach ($m[1] as $attr) {
                T::ok(str_contains($attr, 'src="es/lp.js?v=') || str_contains($attr, 'type="application/ld+json"'), "$f: Skript nur als Datei oder JSON-LD: $attr");
            }
            T::ok(!preg_match('/&[aou]uml;|&szlig;/i', $h), "$f: echte Umlaute statt Entitäten");
            T::contains($h, '<meta name="generator" content="SSI Endoskeleton 3.51.0">', "$f: Versionsmarke");
        }
    },
    'ids eindeutig, Bezüge vorhanden' => static function () use ($gebaut, $dom, $alle): void {
        $o = $gebaut(H::seite($alle));
        $d = $dom((string) file_get_contents("$o/index.html"));
        $x = new DOMXPath($d);
        $ids = [];
        foreach ($x->query('//*[@id]') as $e) { $ids[] = $e->getAttribute('id'); }
        T::eq(count($ids), count(array_unique($ids)), 'ids eindeutig');
        foreach ($x->query('//*[@aria-labelledby]|//*[@aria-describedby]|//label[@for]') as $e) {
            foreach (['aria-labelledby', 'aria-describedby', 'for'] as $a) {
                foreach (array_filter(explode(' ', $e->getAttribute($a))) as $ref) { T::ok(in_array($ref, $ids, true), "$a verweist auf vorhandene id $ref"); }
            }
        }
        foreach ($x->query('//a[starts-with(@href, "#")]') as $a) {
            T::ok(in_array(substr($a->getAttribute('href'), 1), $ids, true), 'Sprungziel vorhanden: ' . $a->getAttribute('href'));
        }
    },
    'CSS und JS mit Prüfsumme eingebunden' => static function () use ($gebaut): void {
        $o = $gebaut(H::seite());
        $h = (string) file_get_contents("$o/index.html");
        T::contains($h, 'href="es/lp.css?v=' . substr(hash_file('sha256', "$o/es/lp.css"), 0, 10) . '"', 'CSS-Marke');
        T::contains($h, 'src="es/lp.js?v=' . substr(hash_file('sha256', "$o/es/lp.js"), 0, 10) . '"', 'JS-Marke');
    },
    'jedes var(--…) aus lp.css ist im Bündel gesetzt' => static function () use ($gebaut): void {
        $o = $gebaut(H::seite());
        $css = (string) file_get_contents("$o/es/lp.css");
        preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $gesetzt);
        preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*\)/i', (string) file_get_contents(H::wurzel() . '/lp/lp.css'), $genutzt);
        T::ok(count($genutzt[1]) > 20, 'lp.css nutzt Tokens');
        foreach (array_unique($genutzt[1]) as $t) { T::ok(in_array($t, $gesetzt[1], true), "Token gesetzt: $t"); }
        T::contains($css, '--primary-rgb: 29, 92, 140;', '--primary-rgb aus --primary abgeleitet');
    },
    'lp.css nutzt nur Tokens, keine festen Farben' => static function (): void {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(H::wurzel() . '/lp/lp.css'));
        T::ok(!preg_match('/#[0-9a-f]{3,8}\b/i', $css, $m), 'kein Hex-Wert ' . ($m[0] ?? ''));
        T::ok(!preg_match('/\b(rgba?|hsla?)\(\s*[\d.]/i', $css, $m), 'kein rgb()/hsl() mit Zahlen ' . ($m[0] ?? ''));
        T::ok(!preg_match('/:\s*[^;]*\b(white|black|red|green|blue|gr[ae]y|silver|orange|yellow|purple)\b/i', $css, $m), 'keine Farbnamen ' . ($m[0] ?? ''));
    },
    'robots.txt und noindex folgen „indexieren"' => static function () use ($gebaut): void {
        $o = $gebaut(H::seite());
        T::eq((string) file_get_contents("$o/robots.txt"), "User-agent: *\nDisallow: /\n", 'gesperrt ohne indexieren');
        T::contains((string) file_get_contents("$o/index.html"), '<meta name="robots" content="noindex, nofollow">', 'noindex');
        $o = $gebaut(H::seite([], ['indexieren' => true]));
        T::eq((string) file_get_contents("$o/robots.txt"), "User-agent: *\nAllow: /\n", 'erlaubt mit indexieren');
        T::notContains((string) file_get_contents("$o/index.html"), 'noindex', 'kein noindex auf der Seite');
        T::contains((string) file_get_contents("$o/impressum.html"), 'noindex', 'Rechtsseiten nie im Index');
    },
    'es/ wird bei jedem Bau ersetzt' => static function () use ($gebaut): void {
        $o = $gebaut(H::seite());
        file_put_contents("$o/es/alt.txt", 'alt');
        Bauer::bauen($o);
        T::ok(!is_file("$o/es/alt.txt"), 'alte Datei in es/ entfernt');
    },
    'falsche oder fehlende Versionsmarke verweigert den Bau' => static function (): void {
        foreach (['3.50.0', "3.51.0; rm -rf /", ''] as $marke) {
            $o = H::ordner(H::seite(), $marke);
            try { Bauer::bauen($o); T::ok(false, "BauFehler erwartet für Marke „{$marke}\""); } catch (BauFehler $e) { T::contains($e->getMessage(), 'ENDOSKELETON', 'Meldung nennt die Marke'); }
            T::ok(!is_file("$o/index.html"), 'nichts gebaut');
        }
    },
    'ohne Profilkopie kein Bau' => static function (): void {
        $o = H::ordner(H::seite());
        Endo\Lp\Dateien::loeschen("$o/profil");
        try { Bauer::bauen($o); T::ok(false, 'BauFehler erwartet'); } catch (BauFehler $e) { T::contains($e->getMessage(), 'profil', 'Meldung nennt das Profil'); }
    },
    'kaputter Bauplan: Befund, nichts geschrieben' => static function (): void {
        $o = H::ordner(H::seite());
        file_put_contents("$o/seite.json", '{"format": 1, "meta": ');
        $e = Bauer::bauen($o);
        T::ok(count(H::sperrend($e->befunde)) === 1 && str_contains($e->befunde[0]->technik, 'JSON'), 'JSON-Fehler gemeldet: ' . H::texte($e->befunde));
        T::ok(!is_file("$o/index.html"), 'nichts gebaut');
        $s = H::seite(); $s['bausteine'][0]['titel'] = str_repeat('x', 81);
        $o = H::ordner($s);
        T::ok(H::sperrend(Bauer::bauen($o)->befunde) !== [], 'Schema-Befund');
        T::ok(!is_dir("$o/es"), 'nichts gebaut');
    },
    'Profil mit zu schwachem Kontrast baut nicht' => static function (): void {
        $p = H::profilKopie();
        file_put_contents("$p/profil.css", str_replace('--text: #2b3440;', '--text: #9aa3ad;', (string) file_get_contents("$p/profil.css")));
        $o = H::ordner(H::seite(), null, $p);
        $b = H::sperrend(Bauer::bauen($o)->befunde);
        T::ok($b !== [] && str_contains($b[0]->technik, 'Kontrast --text auf --bg'), 'Kontrast-Befund: ' . H::texte($b));
        T::ok(!is_file("$o/index.html"), 'nichts gebaut');
    },
];
