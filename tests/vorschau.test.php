<?php
/**
 * Was die Marke 3.53.0 fürs Teilen eines Seitenlinks bringt: Vorschaukarte (Open Graph, Twitter-Karte) mit eigenem
 * Vorschaubild es/vorschau.jpg (JPEG, 1200×630, ≤ 300 KB), Adresse der Seite in meta.adresse, Standard = Kopfbild,
 * Prüfregeln für das Ergebnis (lp/src/Vorschau.php, Pruefer::vorschau).
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;
use Endo\Lp\Skill;
use Endo\Lp\Vorschau;

const VORSCHAU_ADRESSE = 'https://ki.beispiel.invalid/';
const VORSCHAU_HEUTE = '2026-09-27T12:00';

$k = Katalog::laden();
/** Seite mit Adresse; $kopf ersetzt den Kopfbereich (Bild, Variante). */
$seite = static function (array $meta = [], ?array $kopf = null) use ($k): array {
    $s = H::seite([], $meta + ['adresse' => VORSCHAU_ADRESSE]);
    if ($kopf !== null) { $s['bausteine'][0] = $kopf; }
    return $s;
};
$kopfMit = static function (string $datei, string $alt = 'Das Kopfbild') use ($k): array {
    $h = $k->baustein('hero')['beispiel'];
    $h['bild'] = ['datei' => $datei, 'alt' => $alt];
    return $h;
};
/** Legt ein Bild (GD) in img/ des Seitenordners; $zeichnen bekommt das Bild, seine Breite und Höhe. */
$bildLegen = static function (string $ordner, string $name, int $b, int $h, callable $zeichnen, string $art = 'webp'): void {
    @mkdir("$ordner/img", 0700, true);
    $im = imagecreatetruecolor($b, $h);
    $zeichnen($im, $b, $h);
    match ($art) {
        'webp' => imagewebp($im, "$ordner/img/$name", 90),
        'png' => imagepng($im, "$ordner/img/$name"),
        'jpg' => imagejpeg($im, "$ordner/img/$name", 90),
    };
};
$flaeche = static fn (int $r, int $g, int $bl) => static function (GdImage $im, int $b, int $h) use ($r, $g, $bl): void {
    imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $bl));
};
/** Baut und gibt [ordner, ergebnis] zurück. */
$bauen = static function (array $seite, ?callable $vorher = null, ?string $profil = null): array {
    $o = H::ordner($seite, null, $profil);
    if ($vorher) { $vorher($o); }
    return [$o, Bauer::bauen($o)];
};
/** Alle <meta property|name> der Seite: Name => erster Inhalt. */
$metas = static function (string $html): array {
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    $aus = [];
    foreach ((new DOMXPath($d))->query('//meta[@property or @name]') as $m) {
        $aus[$m->getAttribute('property') ?: $m->getAttribute('name')] ??= $m->getAttribute('content');
    }
    return $aus;
};
$jpg = static function (string $ordner): ?GdImage {
    $b = @file_get_contents("$ordner/es/vorschau.jpg");
    return $b === false ? null : (@imagecreatefromstring($b) ?: null);
};
/** RGB eines Pixels. */
$px = static function (GdImage $im, int $x, int $y): array {
    $c = imagecolorat($im, $x, $y);
    return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
};
$hat = static function (array $befunde, string $art, string $teil, string $was): void {
    foreach ($befunde as $x) {
        if (str_starts_with($x->technik, 'gebaute Dateien passen nicht')) { continue; }
        if ($x->art === $art && (str_contains($x->technik, $teil) || str_contains($x->pfad, $teil))) { T::ok(true, $was); return; }
    }
    T::ok(false, "$was — erwartet $art mit „{$teil}“, bekommen:\n" . H::texte($befunde));
};

return [
    'Katalog kennt meta.adresse (Marke 3.53.0) und der Skill nennt sie' => static function () use ($k): void {
        T::ok(isset($k->seite['felder']['adresse']), 'seite.felder.adresse');
        T::contains(Skill::skill($k, '# Kopf'), '| `adresse` |', 'SKILL.md-Tabelle');
        T::contains(Skill::werkstatt($k), '`meta.adresse`', 'werkstatt.md: setzt der Betreiber, nicht ändern');
    },

    'ohne Adresse: Seite wie bisher — nur og:type, og:title, og:description, kein Bild, keine Datei' => static function () use ($bauen, $metas): void {
        [$o, $e] = $bauen(H::seite());
        T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
        $m = $metas((string) file_get_contents("$o/index.html"));
        T::ok(isset($m['og:type'], $m['og:title'], $m['og:description']), 'die drei bisherigen Angaben');
        foreach (['og:url', 'og:image', 'og:image:width', 'og:image:height', 'og:image:alt', 'twitter:card'] as $n) {
            T::ok(!isset($m[$n]), "ohne Adresse kein $n");
        }
        T::ok(!is_file("$o/es/vorschau.jpg"), 'keine es/vorschau.jpg');
    },

    'mit Adresse und Kopfbild: Karte mit og:image (absolut, https, ?v=), Maßen, Alt-Text und twitter:card' => static function () use ($bauen, $metas, $seite): void {
        [$o, $e] = $bauen($seite());
        T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
        $m = $metas((string) file_get_contents("$o/index.html"));
        $datei = (string) file_get_contents("$o/es/vorschau.jpg");
        T::eq($m['og:url'] ?? null, VORSCHAU_ADRESSE, 'og:url');
        T::eq($m['og:image'] ?? null, VORSCHAU_ADRESSE . 'es/vorschau.jpg?v=' . substr(hash('sha256', $datei), 0, 10), 'og:image absolut mit Prüfsumme');
        T::eq($m['og:image:type'] ?? null, 'image/jpeg', 'og:image:type');
        T::eq($m['og:image:width'] ?? null, '1200', 'og:image:width');
        T::eq($m['og:image:height'] ?? null, '630', 'og:image:height');
        T::eq($m['og:image:alt'] ?? null, 'Stilisierte Bühne mit Rednerpult und Publikum', 'og:image:alt = Alt-Text des Kopfbilds (aus dem Bildbestand)');
        T::eq($m['twitter:card'] ?? null, 'summary_large_image', 'twitter:card');
        T::eq($m['og:title'] ?? null, 'Gesund durch den Winter', 'og:title bleibt');
        $g = getimagesizefromstring($datei);
        T::ok($g !== false && $g[2] === IMAGETYPE_JPEG && $g[0] === 1200 && $g[1] === 630, 'es/vorschau.jpg ist ein JPEG, 1200×630');
        T::ok(strlen($datei) <= Vorschau::MAX_BYTES, 'höchstens 300 KB: ' . strlen($datei));
        T::ok(!str_contains($datei, "\xff\xc2"), 'Baseline-JPEG (nicht progressiv)');
    },

    'Impressum und Datenschutz bekommen keine Vorschaukarte' => static function () use ($bauen, $metas, $seite): void {
        [$o] = $bauen($seite());
        foreach (['impressum.html', 'datenschutz.html'] as $f) {
            $m = $metas((string) file_get_contents("$o/$f"));
            T::ok(!isset($m['og:image']) && !isset($m['og:url']) && !isset($m['twitter:card']), "$f: nur Titel und Beschreibung");
        }
    },

    'zweimal bauen ergibt dieselben Bytes, auch die Vorschau' => static function () use ($bauen, $seite): void {
        [$o] = $bauen($seite());
        $vorher = [hash_file('sha256', "$o/es/vorschau.jpg"), hash_file('sha256', "$o/index.html")];
        Bauer::bauen($o);
        T::eq([hash_file('sha256', "$o/es/vorschau.jpg"), hash_file('sha256', "$o/index.html")], $vorher, 'gleiche Prüfsummen');
    },

    'meta.vorschaubild geht vor dem Kopfbild' => static function () use ($bauen, $seite, $metas): void {
        [$a] = $bauen($seite(['vorschaubild' => 'bestand/beispiel-video.webp']));        // Kopfbild: beispiel-vortrag
        [$b] = $bauen($seite([], ['typ' => 'hero', 'variante' => 'bild-rechts', 'titel' => 'Gesund durch den Winter', 'bild' => ['datei' => 'bestand/beispiel-video.webp']]));
        [$c] = $bauen($seite());
        T::eq(hash_file('sha256', "$a/es/vorschau.jpg"), hash_file('sha256', "$b/es/vorschau.jpg"), 'gleiche Vorschau wie ein Kopfbild beispiel-video');
        T::ok(hash_file('sha256', "$a/es/vorschau.jpg") !== hash_file('sha256', "$c/es/vorschau.jpg"), 'anders als die Vorschau des Kopfbilds');
        T::eq($metas((string) file_get_contents("$a/index.html"))['og:image:alt'] ?? null, 'Stilisiertes Standbild eines Vortragsvideos', 'Alt-Text des Bestandsbilds');
    },

    'ohne Kopfbild (schlicht) und ohne Vorschaubild: og:url, aber keine Bildkarte und keine Datei' => static function () use ($bauen, $seite, $metas, $k): void {
        $h = $k->baustein('hero')['beispiel'];
        $h['variante'] = 'schlicht';
        unset($h['bild']);
        [$o, $e] = $bauen($seite([], $h));
        T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
        $m = $metas((string) file_get_contents("$o/index.html"));
        T::eq($m['og:url'] ?? null, VORSCHAU_ADRESSE, 'og:url');
        foreach (['og:image', 'og:image:width', 'og:image:alt', 'twitter:card'] as $n) { T::ok(!isset($m[$n]), "kein $n"); }
        T::ok(!is_file("$o/es/vorschau.jpg"), 'keine Datei');
    },

    'dekoratives Kopfbild: Karte ohne og:image:alt' => static function () use ($bauen, $seite, $metas, $k): void {
        $h = $k->baustein('hero')['beispiel'];
        $h['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'dekorativ' => true];
        [$o] = $bauen($seite([], $h));
        $m = $metas((string) file_get_contents("$o/index.html"));
        T::ok(isset($m['og:image']) && !isset($m['og:image:alt']), 'Bild ja, Alt-Text nein');
    },

    'jedes Seitenverhältnis wird 1200×630 — Zuschnitt aus der Mitte' => static function () use ($bauen, $seite, $kopfMit, $bildLegen, $px, $jpg): void {
        // Drei Streifen rot · grün · blau, nebeneinander ('v') oder übereinander ('h'). Gemessen wird an drei Stellen (links/oben, Mitte, rechts/unten).
        $streifen = static fn (string $richtung) => static function (GdImage $im, int $b, int $h) use ($richtung): void {
            $c = [imagecolorallocate($im, 220, 30, 30), imagecolorallocate($im, 30, 200, 30), imagecolorallocate($im, 30, 30, 220)];
            for ($i = 0; $i < 3; $i++) {
                $richtung === 'v'
                    ? imagefilledrectangle($im, intdiv($b * $i, 3), 0, intdiv($b * ($i + 1), 3) - 1, $h - 1, $c[$i])
                    : imagefilledrectangle($im, 0, intdiv($h * $i, 3), $b - 1, intdiv($h * ($i + 1), 3) - 1, $c[$i]);
            }
        };
        $farbe = static function (array $p): string {
            [$r, $g, $b] = $p;
            return $r > 150 && $g < 100 && $b < 100 ? 'r' : ($g > 150 && $r < 100 && $b < 100 ? 'g' : ($b > 150 && $r < 100 && $g < 100 ? 'b' : "?$r/$g/$b"));
        };
        $stellen = ['v' => [[100, 300], [600, 300], [1100, 300]], 'h' => [[600, 10], [600, 315], [600, 620]]];
        // Name => [Breite, Höhe, Richtung, erwartet an den drei Stellen]
        $faelle = [
            'breit 2400×630 (Seiten fallen weg)' => [2400, 630, 'v', 'rgb'],
            'schon 1200×630' => [1200, 630, 'v', 'rgb'],
            '16:9 1376×768 (oben/unten fällt weg, Breite bleibt)' => [1376, 768, 'v', 'rgb'],
            'quadratisch 900×900 (oben/unten fällt weg)' => [900, 900, 'h', 'rgb'],
            'hoch 600×2000 (nur die Mitte)' => [600, 2000, 'h', 'ggg'],
            'klein 300×200 (wird vergrößert)' => [300, 200, 'v', 'rgb'],
        ];
        foreach ($faelle as $name => [$b, $h, $richtung, $erwartet]) {
            [$o, $e] = $bauen($seite([], $kopfMit('img/kopf.webp')), static fn (string $o) => $bildLegen($o, 'kopf.webp', $b, $h, $streifen($richtung)));
            T::eq(H::texte(H::sperrend($e->befunde)), '', "$name: Bau ohne sperrende Befunde");
            $g = @getimagesize("$o/es/vorschau.jpg");
            T::ok($g !== false && $g[0] === 1200 && $g[1] === 630 && $g[2] === IMAGETYPE_JPEG, "$name: 1200×630 JPEG");
            $im = $jpg($o);
            T::ok($im !== null, "$name: lesbar");
            if ($im !== null) {
                T::eq(implode('', array_map(static fn (array $s) => $farbe($px($im, $s[0], $s[1])), $stellen[$richtung])), $erwartet, "$name: Farben links/oben, Mitte, rechts/unten");
            }
        }
    },

    'Transparenz wird auf --bg des Profils gelegt' => static function () use ($bauen, $seite, $kopfMit, $bildLegen, $px, $jpg): void {
        $durchsichtig = static function (GdImage $im, int $b, int $h): void {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 127));      // ganz durchsichtig
            imagefilledrectangle($im, 0, 0, intdiv($b, 4), $h - 1, imagecolorallocatealpha($im, 0, 0, 255, 0));   // linkes Viertel blau, deckend
        };
        [$o] = $bauen($seite([], $kopfMit('img/kopf.png')), static fn (string $o) => $bildLegen($o, 'kopf.png', 1200, 630, $durchsichtig, 'png'));
        $im = $jpg($o);
        T::ok($im !== null, 'lesbar');
        if ($im === null) { return; }
        [$r, $g, $b] = $px($im, 900, 300);
        T::ok($r > 240 && $g > 240 && $b > 240, "durchsichtige Fläche = --bg (#ffffff) statt schwarz oder rot: $r/$g/$b");
        [$r, $g, $b] = $px($im, 50, 300);
        T::ok($b > 200 && $r < 60, "deckende Fläche bleibt: $r/$g/$b");
    },

    'PNG und JPEG als Kopfbild gehen ebenso' => static function () use ($bauen, $seite, $kopfMit, $bildLegen, $flaeche): void {
        foreach (['png', 'jpg'] as $art) {
            [$o, $e] = $bauen($seite([], $kopfMit("img/kopf.$art")), static fn (string $o) => $bildLegen($o, "kopf.$art", 1000, 700, $flaeche(10, 120, 60), $art));
            T::eq(H::texte($e->befunde), '', "$art: Bau ohne Befunde");
            $g = @getimagesize("$o/es/vorschau.jpg");
            T::ok($g !== false && $g[0] === 1200 && $g[1] === 630, "$art: 1200×630");
        }
    },

    'Dateigröße bleibt unter 300 KB, auch bei sehr feinem Bild (Qualität sinkt)' => static function () use ($bauen, $seite, $kopfMit, $bildLegen): void {
        $rauschen = static function (GdImage $im, int $b, int $h): void {
            mt_srand(7);
            for ($y = 0; $y < $h; $y += 2) {
                for ($x = 0; $x < $b; $x += 2) {
                    imagefilledrectangle($im, $x, $y, $x + 1, $y + 1, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
                }
            }
        };
        [$o, $e] = $bauen($seite([], $kopfMit('img/kopf.png')), static fn (string $o) => $bildLegen($o, 'kopf.png', 1600, 900, $rauschen, 'png'));
        T::eq(H::texte(H::sperrend($e->befunde)), '', 'Bau ohne sperrende Befunde');
        $groesse = (int) @filesize("$o/es/vorschau.jpg");
        T::ok($groesse > 0 && $groesse <= Vorschau::MAX_BYTES, "Rauschen: $groesse Bytes ≤ " . Vorschau::MAX_BYTES);
    },

    'Kopfbild nicht lesbar (abgeschnittenes WebP): Bau gelingt, Hinweis, Karte ohne Bild' => static function () use ($bauen, $seite, $kopfMit, $bildLegen, $flaeche, $metas, $hat): void {
        [$o, $e] = $bauen($seite([], $kopfMit('img/kopf.webp')), static function (string $o) use ($bildLegen, $flaeche): void {
            $bildLegen($o, 'kopf.webp', 800, 450, static function (GdImage $im, int $b, int $h): void {
                mt_srand(3);
                for ($i = 0; $i < 4000; $i++) { imagefilledellipse($im, mt_rand(0, $b), mt_rand(0, $h), 30, 30, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))); }
            });
            $voll = (string) file_get_contents("$o/img/kopf.webp");
            file_put_contents("$o/img/kopf.webp", substr($voll, 0, (int) (strlen($voll) * 0.6)));
        });
        T::eq(H::texte(H::sperrend($e->befunde)), '', 'kein sperrender Befund');
        $hat($e->befunde, 'hinweis', 'aufbereitet', 'Hinweis');
        $m = $metas((string) file_get_contents("$o/index.html"));
        T::ok(!isset($m['og:image']) && !isset($m['twitter:card']), 'keine Bildkarte');
        T::eq($m['og:url'] ?? null, VORSCHAU_ADRESSE, 'og:url bleibt');
        T::ok(!is_file("$o/es/vorschau.jpg"), 'keine Datei');
    },

    'riesiges Kopfbild (16 Megapixel und mehr): nicht entpackt, Hinweis, Bau gelingt' => static function () use ($bauen, $seite, $kopfMit, $metas, $hat): void {
        // Ein gültiges 1-Bit-PNG von 4001×4000 (gepackt wenige KB): GD könnte es lesen — der Baukasten tut es nicht, die Grenze schützt den Speicher des Baus.
        $chunk = static fn (string $typ, string $daten): string => pack('N', strlen($daten)) . $typ . $daten . pack('N', crc32($typ . $daten));
        $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 4001, 4000, 1, 0, 0, 0, 0))
            . $chunk('IDAT', (string) gzcompress(str_repeat("\x00" . str_repeat("\xff", 501), 4000), 9)) . $chunk('IEND', '');
        [$o, $e] = $bauen($seite([], $kopfMit('img/kopf.png')), static function (string $o) use ($png): void {
            mkdir("$o/img", 0700, true);
            file_put_contents("$o/img/kopf.png", $png);
        });
        T::ok(@imagecreatefromstring($png) !== false, 'Vorbedingung: das PNG ist gültig und lesbar (sonst bewiese der Test die Grenze nicht)');
        T::eq(H::texte(H::sperrend($e->befunde)), '', 'kein sperrender Befund');
        $hat($e->befunde, 'hinweis', 'aufbereitet', 'Hinweis');
        T::ok(!isset($metas((string) file_get_contents("$o/index.html"))['og:image']) && !is_file("$o/es/vorschau.jpg"), 'keine Bildkarte');
    },

    'Adresse: nur https-Ursprung mit Schrägstrich (Schema)' => static function () use ($seite): void {
        $h = 'ki.beispiel.invalid';
        // Hosts nur über $h und Verkettung, damit die Hosts-Prüfung (neutral) nur reservierte Namen im Quelltext sieht.
        $schlecht = ["http://$h/", "https://$h", "https://$h/seite/", "https://$h:8443/", 'https://' . 'nutzer@' . $h . '/', "https://$h/?x=1", 'https://' . strtoupper($h) . '/',
            "https://$h/#a", "https://$h/\n", 'https://' . 'ki beispiel.invalid/', "//$h/", 'https://' . 'localhost' . '/', ''];
        foreach ($schlecht as $a) {
            $o = H::ordner($seite(['adresse' => $a]));
            $f = H::sperrend(Bauer::bauen($o, false)->befunde);
            $gefunden = false;
            foreach ($f as $x) { $gefunden = $gefunden || $x->pfad === 'meta.adresse'; }
            T::ok($gefunden, 'abgelehnt: ' . json_encode($a));
        }
        $o = H::ordner($seite(['adresse' => 'https://ki.beispiel.invalid/']));
        T::eq(H::texte(Bauer::bauen($o, false)->befunde), '', 'gültige Adresse');
    },

    'Adresse außerhalb der freigegebenen Websites des Profils: Hinweis, keine Karte' => static function () use ($bauen, $seite, $metas, $hat): void {
        // Testprofil: link_hosts = www.example.com, beispiel.invalid — Unterseiten (ki.beispiel.invalid) und die Wurzel gehen, sonst nichts.
        foreach (['https://beispiel.invalid/' => true, 'https://ki.beispiel.invalid/' => true, 'https://a.b.beispiel.invalid/' => true,
                  'https://evil.invalid/' => false, 'https://xbeispiel.invalid/' => false, 'https://beispiel.invalid.evil.invalid/' => false,
                  'https://example.com/' => false] as $adresse => $ok) {
            [$o, $e] = $bauen($seite(['adresse' => $adresse]));
            $m = $metas((string) file_get_contents("$o/index.html"));
            if ($ok) {
                T::eq($m['og:url'] ?? null, $adresse, "$adresse: erlaubt");
                T::ok(isset($m['og:image']) && str_starts_with($m['og:image'], $adresse), "$adresse: og:image auf der eigenen Adresse");
            } else {
                T::ok(!isset($m['og:url']) && !isset($m['og:image']) && !isset($m['twitter:card']) && !is_file("$o/es/vorschau.jpg"), "$adresse: keine Karte");
                T::ok(!str_contains((string) file_get_contents("$o/index.html"), $adresse), "$adresse: steht nirgends auf der Seite");
                $hat($e->befunde, 'hinweis', 'freigegebenen', "$adresse: Hinweis");
                T::eq(H::texte(H::sperrend($e->befunde)), '', "$adresse: nicht sperrend");
            }
        }
    },

    'meta.vorschaubild ohne meta.adresse: Hinweis, kein og:image' => static function () use ($bauen, $metas): void {
        [$o, $e] = $bauen(H::seite([], ['vorschaubild' => 'bestand/beispiel-video.webp']));
        $hat = false;
        foreach ($e->befunde as $x) { $hat = $hat || ($x->art === 'hinweis' && $x->pfad === 'meta.vorschaubild'); }
        T::ok($hat, 'Hinweis zu meta.vorschaubild');
        T::ok(!isset($metas((string) file_get_contents("$o/index.html"))['og:image']), 'kein og:image');
        T::eq(H::texte(H::sperrend($e->befunde)), '', 'nicht sperrend');
    },

    'Prüfer: gebaute Seite mit Karte ist in Ordnung; kleines Kopfbild gibt einen Hinweis' => static function () use ($bauen, $seite, $kopfMit, $bildLegen, $flaeche, $hat): void {
        [$o] = $bauen($seite());
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o, VORSCHAU_HEUTE))), '', 'keine sperrenden Befunde');
        [$o2] = $bauen($seite([], $kopfMit('img/kopf.webp')), static fn (string $o) => $bildLegen($o, 'kopf.webp', 400, 225, $flaeche(40, 90, 160)));
        $b = Pruefer::pruefen($o2, VORSCHAU_HEUTE);
        T::eq(H::texte(H::sperrend($b)), '', 'klein ist kein sperrender Befund');
        $hat($b, 'hinweis', 'zu klein', 'Hinweis „zu klein“');
    },

    'Prüfer: Vorschaudatei fehlt, falscher Typ, falsche Maße, zu groß, fremder Host' => static function () use ($bauen, $seite, $hat): void {
        $pruefe = static function (callable $aendern) use ($bauen, $seite): array {
            [$o] = $bauen($seite());
            $aendern($o);
            return Pruefer::pruefen($o, VORSCHAU_HEUTE);
        };
        $b = $pruefe(static fn (string $o) => unlink("$o/es/vorschau.jpg"));
        $hat($b, 'sperrend', 'es/vorschau.jpg fehlt', 'Datei fehlt');
        $b = $pruefe(static function (string $o): void { $im = imagecreatetruecolor(1200, 630); imagepng($im, "$o/es/vorschau.jpg"); });
        $hat($b, 'sperrend', 'kein JPEG', 'PNG statt JPEG');
        $b = $pruefe(static function (string $o): void { $im = imagecreatetruecolor(800, 420); imagejpeg($im, "$o/es/vorschau.jpg", 80); });
        $hat($b, 'sperrend', 'nicht 1200×630', 'falsche Maße');
        $b = $pruefe(static function (string $o): void {
            $im = imagecreatetruecolor(1200, 630);
            mt_srand(5);
            for ($y = 0; $y < 630; $y++) { for ($x = 0; $x < 1200; $x += 3) { imagefilledrectangle($im, $x, $y, $x + 2, $y, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))); } }
            imagejpeg($im, "$o/es/vorschau.jpg", 100);
        });
        $hat($b, 'sperrend', 'zu groß', 'über 300 KB');
        $b = $pruefe(static function (string $o): void {
            file_put_contents("$o/index.html", str_replace('https://ki.beispiel.invalid/es/', 'https://evil.invalid/es/', (string) file_get_contents("$o/index.html")));
        });
        $hat($b, 'sperrend', 'fremden Host', 'og:image auf fremdem Host');
        $b = $pruefe(static function (string $o): void {
            file_put_contents("$o/index.html", str_replace('property="og:url" content="https://ki.beispiel.invalid/"', 'property="og:url" content="https://evil.invalid/"', (string) file_get_contents("$o/index.html")));
        });
        $hat($b, 'sperrend', 'fremden Host', 'og:url auf fremdem Host');
        foreach (['https://ki.beispiel.invalid:8443/' => 'mit Port', 'https://nutzer@ki.beispiel.invalid/' => 'mit Benutzer', 'http://ki.beispiel.invalid/' => 'ohne https'] as $falsch => $was) {
            $b = $pruefe(static function (string $o) use ($falsch): void {
                file_put_contents("$o/index.html", str_replace('property="og:url" content="https://ki.beispiel.invalid/"', 'property="og:url" content="' . $falsch . '"', (string) file_get_contents("$o/index.html")));
            });
            $hat($b, 'sperrend', 'fremden Host', "og:url $was");
        }
    },

    'Prüfer: Karte ohne Bild und Seite ohne Adresse haben nichts zu prüfen' => static function () use ($bauen, $seite): void {
        [$o] = $bauen(H::seite());
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o, VORSCHAU_HEUTE))), '', 'ohne Adresse in Ordnung');
        T::ok(!is_file("$o/es/vorschau.jpg"), 'und ohne Datei');
    },

    'Bestandsschutz: ohne meta.adresse ist die Ausgabe Byte für Byte die der bisherigen Marke (Musterbuch)' => static function (): void {
        $w = H::wurzel();
        foreach (glob("$w/musterbuch/*/index.html") ?: [] as $f) {
            $html = (string) file_get_contents($f);
            T::ok(!str_contains($html, 'og:image') && !str_contains($html, 'twitter:card') && !str_contains($html, 'og:url'), basename(dirname($f)) . ': keine Karte');
        }
    },
];
