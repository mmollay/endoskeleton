<?php
/**
 * Baustein „bewegung“ (3.64.0): Bewegungsdatei „bewegung v1“ (Strichfigur ohne Kamerabild), Player lp/bewegung.js, Kamera-Vorführung
 * lp/bewegung-live.js — php8.2 tests/run.php bewegung
 *
 *   BW01 Katalog: Baustein, Felder (alt Pflicht), Klassen, Beispiel
 *   BW02 Pfadmuster bew/<name>.bewegung (\z) und Datei: da, gewöhnlich, nicht über 300 000 Byte
 *   BW03 Format: die Beispieldatei ist gültig, jede Regel hat ihre Kennung (PHP)
 *   BW04 Parität: der JS-Prüfer in lp/bewegung.js sagt für jeden Fall dasselbe wie der PHP-Prüfer
 *   BW05 Schema: alt und bewegung sind Pflicht, stil und knopf_text nur wo erlaubt
 *   BW06 Bau „wiedergabe“: Leinwand, Beschriftung, Steuerung; nur der Player wird mitgeliefert
 *   BW07 Bau „live“: Kamera-Knopf erst nach Klick, Datenschutzsatz, Live-Skript; Seiten ohne den Baustein laden nichts
 *   BW08 Gesamtprüfer: bew/ gehört dem Bauplan, modell/ nur mit den bekannten Prüfsummen, nur das eigene Skript erlaubt
 *   BW09 Beispieldatei: aus dem Erzeuger reproduzierbar, synthetisch, ohne Gesicht; Vorlage „motion-capture“ baut
 *   BW10 Player und Live-Skript: klein, keine Fremdadresse, kein Speicher im Browser, kein Cookie; CSS nur Tokens
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\BauFehler;
use Endo\Lp\Bewegung;
use Endo\Lp\Katalog;
use Endo\Lp\Kontext;
use Endo\Lp\Profil;
use Endo\Lp\Pruefer;
use Endo\Lp\Schema;

const BW_DATEI = 'bew/beispiel-winken.bewegung';

$k = Katalog::laden();
$beispiel = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/musterbuch/' . BW_DATEI);
/** Die Beispieldatei als Feldliste (assoziativ) zum Verändern. */
$roh = static fn (): array => json_decode($beispiel(), true, 8, JSON_THROW_ON_ERROR);
/** Bauplan mit dem Baustein „bewegung“ (Katalog-Beispiel, Änderungen; null löscht ein Feld). */
$seite = static function (array $b = [], string $variante = 'wiedergabe') use ($k): array {
    $b = array_filter($b + ['variante' => $variante] + $k->baustein('bewegung')['beispiel'], static fn ($w) => $w !== null);
    return H::seite([$b]);
};
/** Seitenordner mit der Beispieldatei (oder dem übergebenen Inhalt) unter bew/. */
$ordner = static function (array $plan, ?string $inhalt = null, string $pfad = BW_DATEI) use ($beispiel): string {
    $o = H::ordner($plan);
    @mkdir("$o/bew", 0700, true);
    file_put_contents("$o/$pfad", $inhalt ?? $beispiel());
    return $o;
};
$schema = static fn (array $s, string $ordner): string => H::texte(Schema::pruefen($s, Katalog::laden(), Profil::laden(H::testprofil()), $ordner));
$sperrend = static fn (string $o): string => H::texte(H::sperrend(Pruefer::pruefen($o, '2026-10-29T12:00')));

/**
 * Fälle für den Vergleich PHP ↔ JS: [Name => [Text, Bytes, erwartete Kennung]]. Dieselben Fälle prüfen beide Prüfer.
 *
 * @return array<string, array{0: string, 1: int, 2: ?string}>
 */
$faelle = static function () use ($roh): array {
    $ok = $roh();
    $json = static fn (array $d): string => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '';
    $mit = static function (callable $f) use ($ok, $json): string {
        $d = $ok;
        $f($d);
        return $json($d);
    };
    $f = [];
    $f['gültig'] = [$json($ok), 1000, null];
    $f['zu groß'] = [$json($ok), 300001, 'groesse'];
    $f['Liste statt Objekt'] = ['[1,2]', 10, 'form'];
    $f['leere Liste'] = ['[]', 2, 'form'];
    $f['Text'] = ['"x"', 3, 'form'];
    $f['null'] = ['null', 4, 'form'];
    $f['leeres Objekt'] = ['{}', 2, 'schluessel'];
    $f['kein JSON'] = ['{"v":', 5, 'json'];
    $f['Angabe fehlt'] = [$mit(static function (&$d) { unset($d['fps']); }), 1000, 'schluessel'];
    $f['quelle fehlt'] = [$mit(static function (&$d) { unset($d['quelle']); }), 1000, 'schluessel'];
    $f['unbekannte Angabe'] = [$mit(static function (&$d) { $d['person'] = 'Anna'; }), 1000, 'schluessel'];
    $f['v = 2'] = [$mit(static function (&$d) { $d['v'] = 2; }), 1000, 'v'];
    $f['v als Text'] = [$mit(static function (&$d) { $d['v'] = '1'; }), 1000, 'v'];
    foreach (['leer' => '', 'Spitzklammer' => '<b>x</b>', 'Zeilenumbruch' => "Ab\n", 'Steuerzeichen' => "A\x07b", 'Anfang Leerzeichen' => ' Ab', '61 Zeichen' => str_repeat('a', 61), 'Zahl' => 12] as $was => $name) {
        $f["Name $was"] = [$mit(static function (&$d) use ($name) { $d['name'] = $name; }), 1000, 'name'];
    }
    $f['Name mit Umlaut und Klammern'] = [$mit(static function (&$d) { $d['name'] = 'Ärmel hoch (Übung 2) – leicht'; }), 1000, null];
    $f['Name 60 Zeichen'] = [$mit(static function (&$d) { $d['name'] = str_repeat('ä', 60); }), 1000, null];
    foreach (['kamera', '', 'Synthetisch', 7] as $i => $q) {
        $f["quelle ungültig $i"] = [$mit(static function (&$d) use ($q) { $d['quelle'] = $q; }), 1000, 'quelle'];
    }
    foreach (['eigene-aufnahme', 'einwilligung'] as $q) {
        $f["quelle $q"] = [$mit(static function (&$d) use ($q) { $d['quelle'] = $q; }), 1000, null];
    }
    foreach (['0.2' => 0.2, '4.5' => 4.5, 'vier Stellen' => 0.5625001, 'Text' => '0.5', 'null' => null] as $was => $v) {
        $f["verhaeltnis $was"] = [$mit(static function (&$d) use ($v) { $d['verhaeltnis'] = $v; }), 1000, 'verhaeltnis'];
    }
    foreach (['9' => 9, '61' => 61, '29.5' => 29.5, 'Text' => '30'] as $was => $v) {
        $f["fps $was"] = [$mit(static function (&$d) use ($v) { $d['fps'] = $v; }), 1000, 'fps'];
    }
    $f['dim 4'] = [$mit(static function (&$d) { $d['dim'] = 4; }), 1000, 'dim'];
    $f['dim 3 mit 2 Achsen'] = [$mit(static function (&$d) { $d['dim'] = 3; }), 1000, 'rahmen.laenge'];
    $f['Punkte vertauscht'] = [$mit(static function (&$d) { [$d['punkte'][0], $d['punkte'][1]] = [$d['punkte'][1], $d['punkte'][0]]; }), 1000, 'punkte'];
    $f['Punkt fehlt'] = [$mit(static function (&$d) { array_pop($d['punkte']); }), 1000, 'punkte'];
    $f['ein Punkt Text mehr'] = [$mit(static function (&$d) { $d['punkte'][5] .= 'x'; }), 1000, 'punkte'];
    $f['keine Bilder'] = [$mit(static function (&$d) { $d['rahmen'] = []; }), 1000, 'rahmen.anzahl'];
    $f['ein Bild'] = [$mit(static function (&$d) { $d['rahmen'] = [$d['rahmen'][0]]; }), 1000, 'rahmen.anzahl'];
    $f['601 Bilder'] = [$mit(static function (&$d) { $d['rahmen'] = array_fill(0, 601, $d['rahmen'][0]); }), 1000, 'rahmen.anzahl'];
    $f['Bilder als Objekt'] = [$mit(static function (&$d) { $d['rahmen'] = (object) ['a' => $d['rahmen'][0], 'b' => $d['rahmen'][1]]; }), 1000, 'rahmen.anzahl'];
    $f['Bild zu kurz'] = [$mit(static function (&$d) { array_pop($d['rahmen'][3]); }), 1000, 'rahmen.laenge'];
    $f['Bild als Text'] = [$mit(static function (&$d) { $d['rahmen'][3] = 'abc'; }), 1000, 'rahmen.laenge'];
    $f['Bild als Objekt'] = [$mit(static function (&$d) { $d['rahmen'][3] = (object) array_slice($d['rahmen'][3], 0, 99, true); }), 1000, 'rahmen.laenge'];
    $f['Wert als Text'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = '0.5'; }), 1000, 'wert'];
    $f['Wert null'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = null; }), 1000, 'wert'];
    $f['Wert wahr'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = true; }), 1000, 'wert'];
    $f['Wert über 1'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = 1.001; }), 1000, 'bereich'];
    $f['Wert unter 0'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = -0.001; }), 1000, 'bereich'];
    $f['Wert 1 genau'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = 1.0; }), 1000, null];
    $f['Wert 4 Stellen'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = 0.1234; }), 1000, 'stellen'];
    $f['Sichtbarkeit über 1'] = [$mit(static function (&$d) { $d['rahmen'][2][35] = 2; }), 1000, 'bereich'];
    $f['unsichtbar mit Lage'] = [$mit(static function (&$d) { $d['rahmen'][2][35] = 0; }), 1000, 'unsichtbar'];
    $f['unsichtbar ganz null'] = [$mit(static function (&$d) { $d['rahmen'][2][33] = 0; $d['rahmen'][2][34] = 0; $d['rahmen'][2][35] = 0; }), 1000, null];
    foreach (['Auge links innen' => 1, 'Auge rechts' => 5, 'Mund links' => 9, 'Mund rechts' => 10] as $was => $i) {
        $f["Gesicht $was sichtbar"] = [$mit(static function (&$d) use ($i) { $d['rahmen'][2][$i * 3] = 0.5; $d['rahmen'][2][$i * 3 + 1] = 0.2; $d['rahmen'][2][$i * 3 + 2] = 1; }), 1000, 'gesicht'];
    }
    $f['Nase sichtbar (Kopfhaltung erlaubt)'] = [$mit(static function (&$d) { $d['rahmen'][2][2] = 1; }), 1000, null];
    $f['dauer_ms falsch'] = [$mit(static function (&$d) { $d['dauer_ms'] = 2999; }), 1000, 'dauer'];
    $f['dauer_ms als Text'] = [$mit(static function (&$d) { $d['dauer_ms'] = '3000'; }), 1000, 'dauer'];
    $f['länger als 10 s'] = [$mit(static function (&$d) {
        $d['fps'] = 30;
        $d['rahmen'] = array_merge($d['rahmen'], $d['rahmen'], $d['rahmen'], array_slice($d['rahmen'], 0, 31));
        $d['dauer_ms'] = (int) round(count($d['rahmen']) * 1000 / 30);
    }), 1000, 'dauer'];
    $f['genau 10 s'] = [$mit(static function (&$d) {
        $d['rahmen'] = array_merge($d['rahmen'], $d['rahmen'], $d['rahmen'], array_slice($d['rahmen'], 0, 30));
        $d['dauer_ms'] = 10000;
    }), 1000, null];
    // drei Achsen: x, y, z (−1…1), Sichtbarkeit
    $drei = static function (callable $g) use ($roh, $json): string {
        $d = $roh();
        $d['dim'] = 3;
        $d['rahmen'] = array_map(static function (array $bild): array {
            $neu = [];
            foreach (array_chunk($bild, 3) as $p) {
                array_push($neu, $p[0], $p[1], $p[2] > 0 ? -0.25 : 0, $p[2]);
            }
            return $neu;
        }, $d['rahmen']);
        $g($d);
        return $json($d);
    };
    $f['dim 3 gültig'] = [$drei(static function (&$d) {}), 1000, null];
    $f['z = −1 genau'] = [$drei(static function (&$d) { $d['rahmen'][1][46] = -1; }), 1000, null];
    $f['z unter −1'] = [$drei(static function (&$d) { $d['rahmen'][1][46] = -1.001; }), 1000, 'bereich'];
    $f['z über 1'] = [$drei(static function (&$d) { $d['rahmen'][1][46] = 1.5; }), 1000, 'bereich'];
    $f['dim 3: Bild mit 99 Werten'] = [$drei(static function (&$d) { $d['rahmen'][1] = array_slice($d['rahmen'][1], 0, 99); }), 1000, 'rahmen.laenge'];
    return $f;
};

return [
    'BW01 Katalog: Baustein bewegung, Varianten, Felder (alt Pflicht), Klassen seit 3.64.0, Beispiel gültig' => static function () use ($k, $schema, $ordner): void {
        $b = $k->baustein('bewegung');
        T::ok(is_array($b), 'Baustein bewegung im Katalog');
        T::eq($b['varianten'] ?? null, ['wiedergabe', 'live'], 'Varianten wiedergabe und live');
        T::eq($b['seit'] ?? null, '3.64.0', 'seit 3.64.0');
        foreach (['titel', 'bewegung', 'alt'] as $f) {
            T::ok($b['felder'][$f]['pflicht'] ?? false, "Feld $f ist Pflicht");
        }
        T::eq($b['felder']['bewegung']['typ'] ?? null, 'bewegungsdatei', 'bewegung hat den Typ bewegungsdatei');
        T::eq($b['felder']['stil']['werte'] ?? null, ['strich', 'punkte'], 'stil: strich oder punkte');
        T::eq($b['felder']['stil']['standard'] ?? null, 'strich', 'Standard strich');
        T::eq($b['felder']['knopf_text']['nur_bei'] ?? null, ['live'], 'knopf_text nur bei live');
        T::ok(isset($b['felder']['text']) && !($b['felder']['text']['pflicht'] ?? false), 'text ist freiwillig');
        T::ok(count($b['klassen']) >= 8, 'mindestens acht Klassen');
        foreach ($b['klassen'] as $klasse => $seit) {
            T::ok(str_starts_with($klasse, 'lp-bewegung'), "$klasse beginnt mit lp-bewegung");
            T::eq($seit, '3.64.0', "$klasse seit 3.64.0");
        }
        T::eq($schema(H::seite([$b['beispiel']]), $ordner(H::seite([$b['beispiel']]))), '', 'das Katalog-Beispiel ist gültig');
    },
    'BW02 Pfadmuster ^bew/[a-z0-9][a-z0-9-]{0,39}\.bewegung\z und Datei (da, gewöhnlich, ≤ 300 000 Byte)' => static function () use ($seite, $ordner, $schema, $beispiel): void {
        $o = $ordner($seite());
        T::eq($schema($seite(), $o), '', 'gültig');
        $schlecht = [
            'Zeilenumbruch am Ende' => BW_DATEI . "\n", 'Großbuchstaben' => 'bew/Beispiel.bewegung', 'andere Endung' => 'bew/beispiel.json', 'zweite Endung' => BW_DATEI . '.php',
            'Unterordner' => 'bew/x/beispiel.bewegung', 'falscher Ordner' => 'img/beispiel.bewegung', 'Hochschritt' => '../bew/beispiel.bewegung', 'absolut' => '/etc/passwd',
            'Adresse' => 'https://beispiel.invalid/bew/beispiel.bewegung', 'Abfrage' => BW_DATEI . '?x=1', 'Nullbyte' => "bew/beispiel.bewegung\0.png", 'leer' => '',
            'Bindestrich vorn' => 'bew/-beispiel.bewegung', '41 Zeichen' => 'bew/' . str_repeat('a', 41) . '.bewegung', 'Leerzeichen' => 'bew/bei spiel.bewegung',
        ];
        foreach ($schlecht as $was => $pfad) {
            T::contains($schema($seite(['bewegung' => $pfad]), $o), '[sperrend] bausteine.1.bewegung', "ungültig ($was) ist sperrend am Feld");
        }
        foreach ([123, true, [BW_DATEI], ['datei' => BW_DATEI]] as $i => $x) {
            T::contains($schema($seite(['bewegung' => $x]), $o), '[sperrend] bausteine.1.bewegung', "kein Text (Fall $i) ist sperrend");
        }
        T::contains($schema($seite(['bewegung' => 'bew/fehlt.bewegung']), $o), 'bew/fehlt.bewegung nicht gefunden', 'Datei fehlt: sperrend');
        $o2 = $ordner($seite(), str_repeat(' ', Bewegung::MAX_BYTES + 1));
        T::contains($schema($seite(), $o2), 'groesse', 'über 300 000 Byte: sperrend');
        T::contains($schema($seite(), $o2), 'zu groß', 'Kundentext nennt die Größe');
        $o3 = $ordner($seite(), '{"v":');
        T::contains($schema($seite(), $o3), 'Bewegungsdatei ist kein gültiges JSON', 'kaputtes JSON: sperrend mit Kundentext');
        $o4 = H::ordner($seite());
        @mkdir("$o4/bew", 0700, true);
        file_put_contents("$o4/echt.bewegung", $beispiel());
        symlink("$o4/echt.bewegung", "$o4/" . BW_DATEI);
        T::contains($schema($seite(), $o4), 'ist ein Link', 'Datei als Link: sperrend');
        $o5 = H::ordner($seite());
        @mkdir("$o5/echt-bew", 0700, true);
        file_put_contents("$o5/echt-bew/" . basename(BW_DATEI), $beispiel());
        symlink("$o5/echt-bew", "$o5/bew");
        T::contains($schema($seite(), $o5), 'ist ein Link', 'Ordner bew als Link: sperrend');
    },
    'BW03 Format (PHP): die Beispieldatei ist gültig, jede Regel hat ihre Kennung' => static function () use ($faelle): void {
        $n = 0;
        foreach ($faelle() as $was => [$text, $bytes, $soll]) {
            try {
                $ist = Bewegung::pruefen(json_decode($text, false, 8, JSON_THROW_ON_ERROR), $bytes);
            } catch (\JsonException) {
                $ist = 'json';
            }
            T::eq($ist, $soll, "Fall „{$was}“");
            $n++;
        }
        T::ok($n >= 70, "mindestens 70 Fälle ($n)");
        foreach (Bewegung::TEXTE as $kennung => $text) {
            T::ok(str_ends_with($text, '.') && mb_strlen($text) > 20, "Kundentext zu $kennung ist ein Satz");
        }
    },
    'BW04 Parität: der JS-Prüfer in lp/bewegung.js liefert für jeden Fall dieselbe Kennung wie der PHP-Prüfer' => static function () use ($faelle): void {
        $datei = T::tmp() . '/faelle.json';
        $liste = [];
        foreach ($faelle() as $was => [$text, $bytes]) {
            $liste[] = ['name' => $was, 'text' => $text, 'bytes' => $bytes];
        }
        file_put_contents($datei, json_encode($liste, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $aus = [];
        exec('node ' . escapeshellarg(H::wurzel() . '/tests/bewegung-paritaet.js') . ' ' . escapeshellarg($datei) . ' 2>&1', $aus, $rc);
        $js = json_decode(implode("\n", $aus), true);
        T::ok(is_array($js), 'node liefert JSON (Rückgabe ' . $rc . '): ' . substr(implode(' ', $aus), 0, 200));
        foreach ($faelle() as $was => [, , $soll]) {
            T::eq(array_key_exists($was, $js) ? $js[$was] : 'fehlt', $soll, "JS-Prüfer, Fall „{$was}“");
        }
    },
    'BW05 Schema: bewegung und alt sind Pflicht; knopf_text nur bei live; stil nur strich oder punkte; unbekannte Felder sperrend' => static function () use ($seite, $ordner, $schema): void {
        $o = $ordner($seite());
        T::eq($schema($seite(), $o), '', 'Ausgangslage gültig');
        T::contains($schema($seite(['alt' => null]), $o), 'alt', 'alt fehlt: sperrend');
        T::contains($schema($seite(['alt' => '']), $o), '[sperrend] bausteine.1.alt', 'alt leer: sperrend');
        T::contains($schema($seite(['alt' => str_repeat('a', 201)]), $o), '[sperrend] bausteine.1.alt', 'alt über 200 Zeichen: sperrend');
        T::contains($schema($seite(['bewegung' => null]), $o), 'bewegung', 'bewegung fehlt: sperrend');
        T::contains($schema($seite(['titel' => null]), $o), 'titel', 'titel fehlt: sperrend');
        T::eq($schema($seite(['stil' => 'punkte']), $o), '', 'stil punkte gilt');
        T::contains($schema($seite(['stil' => 'knochen']), $o), '[sperrend] bausteine.1.stil', 'stil knochen: sperrend');
        T::contains($schema($seite(['knopf_text' => 'Kamera an']), $o), 'knopf_text', 'knopf_text bei wiedergabe: sperrend');
        T::eq($schema($seite(['knopf_text' => 'Kamera an'], 'live'), $o), '', 'knopf_text bei live: erlaubt');
        T::contains($schema($seite(['knopf_text' => str_repeat('x', 41)], 'live'), $o), 'knopf_text', 'knopf_text über 40 Zeichen: sperrend');
        T::contains($schema($seite(['video' => 'x']), $o), 'video', 'unbekanntes Feld: sperrend');
        T::contains($schema($seite([], 'kamera'), $o), 'variante', 'unbekannte Variante: sperrend');
    },
    'BW06 Bau „wiedergabe“: Leinwand mit Beschriftung, Steuerung, Maße der Datei; nur der Player wird mitgeliefert' => static function () use ($seite, $ordner): void {
        $plan = $seite(['text' => "Das ist eine Bewegung.\n\nSie wird ohne Kamera gezeigt."]);
        $o = $ordner($plan);
        $e = Bauer::bauen($o);
        T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
        $html = (string) file_get_contents("$o/index.html");
        $d = new DOMDocument();
        libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        $x = new DOMXPath($d);
        T::eq($x->query('//section[contains(@class,"lp-bewegung--wiedergabe")]')->length, 1, 'ein Abschnitt lp-bewegung--wiedergabe');
        $fig = $x->query('//figure[@data-lp-bewegung]')->item(0);
        T::eq($fig?->getAttribute('data-lp-bewegung'), BW_DATEI, 'Figur nennt die Datei');
        T::eq($fig?->getAttribute('data-lp-stil'), 'strich', 'Standardstil strich');
        T::eq($fig?->getAttribute('data-lp-modus'), 'wiedergabe', 'Modus wiedergabe');
        $cv = $x->query('.//canvas', $fig)->item(0);
        T::eq([$cv?->getAttribute('width'), $cv?->getAttribute('height')], ['360', '640'], 'Leinwand 360×640 (Verhältnis 9:16 aus der Datei)');
        T::eq($cv?->getAttribute('role'), 'img', 'Leinwand role=img');
        T::eq($cv?->getAttribute('tabindex'), '0', 'Leinwand per Tastatur erreichbar');
        $bez = $cv?->getAttribute('aria-labelledby') ?? '';
        $cap = $x->query('//*[@id="' . $bez . '"]')->item(0);
        T::ok($bez !== '' && $cap !== null && str_contains($cap->textContent, 'Strichfigur'), 'Leinwand ist über die Bildunterschrift (alt) beschriftet');
        foreach (['pause', 'spiegeln', 'tempo'] as $b) {
            T::eq($x->query('//*[@data-lp-b="' . $b . '"]')->length, 1, "Bedienelement $b");
        }
        T::eq($x->query('//*[@data-lp-b="kamera"]')->length, 0, 'wiedergabe: kein Kamera-Knopf');
        T::eq($x->query('//div[contains(@class,"lp-bewegung-leiste")][not(@hidden)]')->length, 0, 'Steuerung ist ohne JavaScript verborgen (hidden)');
        T::contains($html, 'Sie wird ohne Kamera gezeigt.', 'Text-Absatz da');
        T::eq($x->query('//script[contains(@src,"bewegung.js")]')->length, 1, 'ein Skript es/bewegung.js');
        T::eq(preg_match('#<script src="es/bewegung\.js\?v=[0-9a-f]{10}" defer></script>#', $html), 1, 'Skript mit Stempel und defer');
        T::notContains($html, 'bewegung-live', 'wiedergabe: das Live-Skript wird nicht eingebunden');
        T::ok(is_file("$o/es/bewegung.js"), 'es/bewegung.js kopiert');
        T::ok(!is_file("$o/es/bewegung-live.js"), 'es/bewegung-live.js nicht kopiert');
        T::notContains($html, ' style=', 'kein style-Attribut');
        $abschnitt = $d->saveHTML($x->query('//section[contains(@class,"lp-bewegung--wiedergabe")]')->item(0));
        T::eq(preg_match('#https?://#', (string) $abschnitt), 0, 'keine fremde Adresse im Abschnitt der Bewegung (Formular und Rahmen der Seite zählen nicht)');
        $r = (string) file_get_contents("$o/impressum.html");
        T::notContains($r, 'bewegung', 'Impressum lädt das Skript nicht');
        $s = $seite(['stil' => 'punkte']);
        $o2 = $ordner($s);
        Bauer::bauen($o2);
        T::contains((string) file_get_contents("$o2/index.html"), 'data-lp-stil="punkte"', 'Stil punkte kommt an');
        $a = (string) file_get_contents("$o/index.html");
        Bauer::bauen($o);
        T::eq((string) file_get_contents("$o/index.html"), $a, 'zweimal bauen = dieselben Bytes');
    },
    'BW07 Bau „live“: Kamera-Knopf, Datenschutzsatz, Live-Skript; Seiten ohne den Baustein laden nichts' => static function () use ($seite, $ordner): void {
        $o = $ordner($seite(['knopf_text' => 'Kamera einschalten'], 'live'));
        T::eq(H::texte(Bauer::bauen($o)->befunde), '', 'Bau ohne Befunde');
        $html = (string) file_get_contents("$o/index.html");
        T::contains($html, 'lp-bewegung--live', 'Abschnitt lp-bewegung--live');
        T::contains($html, 'data-lp-modus="live"', 'Modus live');
        foreach (['kamera', 'aufnehmen', 'bild', 'speichern'] as $b) {
            T::contains($html, 'data-lp-b="' . $b . '"', "Bedienelement $b");
        }
        T::contains($html, 'Kamera einschalten', 'Text des Kamera-Knopfs aus knopf_text');
        T::contains($html, 'Das Kamerabild bleibt auf deinem Gerät', 'Datenschutzsatz (du)');
        T::contains($html, 'nicht gespeichert oder übertragen', 'Datenschutzsatz: nicht gespeichert oder übertragen');
        foreach (['fehler', 'keinkamera', 'laedt', 'aufnahme', 'fertig'] as $t) {
            T::contains($html, 'data-lp-t="' . $t . '"', "Meldung $t");
        }
        T::ok(is_file("$o/es/bewegung.js") && is_file("$o/es/bewegung-live.js"), 'Player und Live-Skript kopiert');
        T::eq(preg_match_all('#<script src=#', $html), 2, 'zwei Skripte: lp.js und der Player (das Live-Skript kommt erst nach dem Klick)');
        T::eq(preg_match('#<script src="es/bewegung\.js\?v=[0-9a-f]{10}" defer></script>#', $html), 1, 'der Player trägt den Stempel');
        T::notContains($html, 'bewegung-live.js', 'das Live-Skript steht nicht im HTML (wird erst nach dem Klick geladen)');
        T::notContains($html, 'modell/', 'das HTML nennt die Modelldateien nicht (das Live-Skript kennt den Ort)');
        // Sie-Anrede
        $p = H::profilKopie();
        file_put_contents("$p/profil.json", str_replace('"anrede": "du"', '"anrede": "Sie"', (string) file_get_contents("$p/profil.json")));
        $o2 = H::ordner($seite([], 'live'), null, $p);
        @mkdir("$o2/bew", 0700, true);
        file_put_contents("$o2/" . BW_DATEI, (string) file_get_contents(H::wurzel() . '/lp/musterbuch/' . BW_DATEI));
        Bauer::bauen($o2);
        T::contains((string) file_get_contents("$o2/index.html"), 'Das Kamerabild bleibt auf Ihrem Gerät', 'Datenschutzsatz (Sie)');
        // ohne den Baustein: weder Skript noch Datei
        $o3 = H::ordner(H::seite());
        Bauer::bauen($o3);
        T::notContains((string) file_get_contents("$o3/index.html"), 'bewegung', 'Seite ohne Bewegung: kein Hinweis darauf im HTML');
        T::ok(!is_file("$o3/es/bewegung.js") && !is_file("$o3/es/bewegung-live.js"), 'Seite ohne Bewegung: keine Skripte kopiert');
        // Kontext lässt nichts am Prüfer vorbei
        $plan = $seite();
        $o4 = $ordner($plan);
        $kontext = new Kontext(Profil::laden("$o4/profil"), $plan['meta'], $o4);
        T::eq($kontext->bewegungsDatei(BW_DATEI, false)['verhaeltnis'], 0.5625, 'Kontext liest das Verhältnis');
        foreach (['../' . BW_DATEI, '/etc/passwd', 'https://beispiel.invalid/a.bewegung', 'bew/fehlt.bewegung', BW_DATEI . "\n"] as $schlecht) {
            try {
                $kontext->bewegungsDatei($schlecht, false);
                T::ok(false, "$schlecht: hätte eine Ausnahme werfen müssen");
            } catch (BauFehler) {
                T::ok(true, 'BauFehler');
            }
        }
        file_put_contents("$o4/bew/kaputt.bewegung", '{"v":2}');
        try {
            $kontext->bewegungsDatei('bew/kaputt.bewegung', false);
            T::ok(false, 'falsches Format: hätte eine Ausnahme werfen müssen');
        } catch (BauFehler) {
            T::ok(true, 'falsches Format: BauFehler');
        }
    },
    'BW08 Gesamtprüfer: bew/ gehört dem Bauplan; modell/ nur mit bekannten Prüfsummen; nur das eigene Skript erlaubt' => static function () use ($seite, $ordner, $sperrend): void {
        $o = $ordner($seite(['knopf_text' => 'Kamera starten'], 'live'));
        Bauer::bauen($o);
        T::eq($sperrend($o), '', 'Seite mit Bewegung: keine sperrenden Befunde');
        $alle = H::texte(Pruefer::pruefen($o, '2026-10-29T12:00'));
        T::contains($alle, 'modell', 'live ohne modell/: Hinweis (nicht sperrend)');
        // eine Datei, die der Bauplan nicht nennt
        file_put_contents("$o/bew/andere.bewegung", 'x');
        T::contains($sperrend($o), 'bew/andere.bewegung wird im Bauplan nicht verwendet', 'unbenutzte Bewegungsdatei sperrend');
        unlink("$o/bew/andere.bewegung");
        // modell/: nur die Dateien der Liste, jede mit ihrer Prüfsumme
        @mkdir("$o/modell/wasm", 0700, true);
        file_put_contents("$o/modell/fremd.js", 'x');
        T::contains($sperrend($o), 'modell/fremd.js gehört nicht zu den Modelldateien', 'fremde Datei in modell/ sperrend');
        unlink("$o/modell/fremd.js");
        file_put_contents("$o/modell/pose_landmarker_lite.task", 'nicht das Modell');
        T::contains($sperrend($o), 'modell/pose_landmarker_lite.task hat eine falsche Prüfsumme', 'Modelldatei mit falscher Prüfsumme sperrend');
        unlink("$o/modell/pose_landmarker_lite.task");
        T::eq($sperrend($o), '', 'wieder sauber');
        // Skripte: nur es/lp.js und es/bewegung.js mit Stempel
        $h = (string) file_get_contents("$o/index.html");
        foreach (['https://beispiel.invalid/a.js' => 'fremde Adresse', 'es/bewegung.js?v=zzzzzzzzzz' => 'Stempel ungültig', 'es/bewegung-live.js?v=0123456789' => 'Live-Skript im HTML', 'es/bewegung.js' => 'ohne Stempel'] as $src => $was) {
            file_put_contents("$o/index.html", preg_replace('#(<script src=")es/bewegung\.js\?v=[0-9a-f]{10}#', '${1}' . $src, $h));
            T::contains($sperrend($o), 'Skript nicht erlaubt', "Skript: $was sperrend");
        }
        file_put_contents("$o/index.html", $h);
        // eingebettetes Skript bleibt verboten
        file_put_contents("$o/index.html", str_replace('</body>', '<script>alert(1)</script></body>', $h));
        T::contains($sperrend($o), 'Skript nicht erlaubt', 'Inline-Skript weiterhin sperrend');
        file_put_contents("$o/index.html", $h);
        T::eq($sperrend($o), '', 'zurückgesetzt: sauber');
        // verändertes Skript im Seitenordner: Nachbau-Vergleich schlägt an
        file_put_contents("$o/es/bewegung.js", "// von Hand\n" . file_get_contents("$o/es/bewegung.js"));
        T::contains($sperrend($o), 'es/bewegung.js', 'von Hand verändertes Skript sperrend (Nachbau)');
    },
    'BW09 Beispieldatei: aus dem Erzeuger reproduzierbar, synthetisch, ohne Gesicht, klein; Vorlage „motion-capture“ baut' => static function () use ($beispiel, $roh): void {
        $d = $roh();
        T::eq($d['quelle'], 'synthetisch', 'quelle synthetisch');
        T::eq($d['punkte'], Bewegung::PUNKTE, '33 Punkte in der festen Reihenfolge');
        T::eq(Bewegung::pruefen(json_decode($beispiel(), false), strlen($beispiel())), null, 'gültig');
        T::ok(strlen($beispiel()) < 120000, 'unter 120 KB (' . strlen($beispiel()) . ' Byte)');
        foreach ($d['rahmen'] as $bild) {
            foreach (Bewegung::GESICHT as $i) {
                if (array_slice($bild, $i * 3, 3) !== [0, 0, 0] && array_slice($bild, $i * 3, 3) !== [0.0, 0.0, 0.0]) {
                    T::ok(false, "Gesichtspunkt $i ist nicht null");
                    break 2;
                }
            }
        }
        $aus = [];
        exec('php8.2 ' . escapeshellarg(H::wurzel() . '/ci/bewegung-beispiel-bauen.php') . ' --ausgeben 2>&1', $aus, $rc);
        T::eq($rc, 0, 'Erzeuger läuft');
        T::eq(implode("\n", $aus) . "\n", $beispiel(), 'der Erzeuger liefert genau die Datei im Musterbuch (byte-gleich)');
        // Vorlage
        $v = json_decode((string) file_get_contents(H::wurzel() . '/lp/vorlagen/motion-capture.json'), true);
        T::ok(is_array($v), 'Vorlage motion-capture.json lesbar');
        $typen = array_map(static fn ($b) => $b['typ'] . ':' . ($b['variante'] ?? ''), $v['bausteine'] ?? []);
        T::eq($typen[0] ?? null, 'hero:schlicht', 'Vorlage beginnt mit dem Kopfbereich');
        T::contains(implode(' ', $typen), 'bewegung:live', 'Vorlage hat die Kamera-Vorführung');
        T::contains(implode(' ', $typen), 'bewegung:wiedergabe', 'Vorlage hat die Wiedergabe');
        T::contains(implode(' ', $typen), 'faq:', 'Vorlage hat Häufige Fragen');
        T::contains((string) file_get_contents(H::wurzel() . '/lp/vorlagen/motion-capture.json'), BW_DATEI, 'Vorlage nennt die Beispieldatei');
    },
    'BW10 Player und Live-Skript: klein, keine Fremdadresse, kein Speicher im Browser, kein Cookie; CSS nur mit Tokens' => static function (): void {
        $w = H::wurzel();
        $js = (string) file_get_contents("$w/lp/bewegung.js");
        $live = (string) file_get_contents("$w/lp/bewegung-live.js");
        T::ok(strlen((string) gzencode($js, 9)) <= 6144, 'Player gzip ≤ 6 KB (' . strlen((string) gzencode($js, 9)) . ' Byte)');
        T::ok(strlen($js) <= 20000, 'Player roh ≤ 20 KB (' . strlen($js) . ' Byte)');
        foreach (['bewegung.js' => $js, 'bewegung-live.js' => $live] as $name => $code) {
            T::eq(preg_match('#https?://|//[a-z0-9.-]+\.[a-z]{2,}/#i', $code), 0, "$name: keine Fremdadresse");
            foreach (['localStorage', 'sessionStorage', 'indexedDB', 'document.cookie', 'XMLHttpRequest', 'sendBeacon', 'WebSocket', 'eval(', 'new Function'] as $verboten) {
                T::notContains($code, $verboten, "$name: kein $verboten");
            }
            T::notContains($code, 'cdn.', "$name: kein CDN");
            T::notContains($code, 'jsdelivr', "$name: kein jsDelivr");
        }
        T::ok(substr_count($live, 'fetch(') === 0, 'Live-Skript lädt nur über FilesetResolver/import, kein eigenes fetch');
        T::contains($js, 'prefers-reduced-motion', 'Player beachtet prefers-reduced-motion');
        T::contains($js, "credentials: 'omit'", "Player holt die Datei mit credentials: 'omit'");
        T::contains($live, 'getUserMedia', 'Live-Skript fragt die Kamera');
        T::notContains($js, 'getUserMedia', 'Der Player kennt die Kamera nicht');
        T::contains($live, 'modell/', 'Live-Skript lädt Modell und WASM vom eigenen Server (modell/)');
        $css = (string) file_get_contents("$w/lp/lp.css");
        T::ok(preg_match_all('/\.lp-bewegung[^{]*\{[^}]*\}/s', $css, $m) >= 8, 'CSS-Regeln für lp-bewegung da');
        $block = implode("\n", $m[0] ?? []);
        T::eq(preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $block), 0, 'lp-bewegung-CSS: keine festen Farben');
        T::contains($block, 'var(--', 'lp-bewegung-CSS: Tokens');
    },
];
