<?php
/** Der Baustein-Katalog lp/bausteine.json: vollständig, versioniert, jedes Beispiel gültig. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Schema;

$k = Katalog::laden();
$version = trim((string) file_get_contents(H::wurzel() . '/VERSION'));
$typen = ['zeile', 'absatz', 'zahl', 'zeitpunkt', 'auswahl', 'url', 'anker', 'kennung', 'bild', 'bilddatei', 'knopf', 'liste', 'janein'];

return [
    'Katalogversion = VERSION' => static fn () => T::eq($k->version, $version, 'version in bausteine.json'),
    'die neun Bausteine von 3.53.0' => static fn () => T::eq(array_keys($k->bausteine), ['hero', 'termin', 'themen', 'video', 'erfahrungen', 'faq', 'anmeldung', 'person', 'abschluss'], 'Typen'),
    'jeder Eintrag vollständig' => static function () use ($k, $version, $typen): void {
        foreach ($k->bausteine as $typ => $e) {
            foreach (['name', 'seit', 'geaendert', 'klassen', 'varianten', 'felder', 'regeln', 'beispiel'] as $s) {
                T::ok(array_key_exists($s, $e), "$typ: Schlüssel $s");
            }
            T::ok(version_compare($e['seit'], $version, '<=') && version_compare($e['geaendert'], $version, '<='), "$typ: seit/geändert ≤ VERSION");
            T::ok($e['varianten'] !== [], "$typ: mindestens eine Variante");
            T::ok(isset($e['klassen']["lp-$typ"]), "$typ: Grundklasse lp-$typ");
            foreach ($e['varianten'] as $v) { T::ok(isset($e['klassen']["lp-$typ--$v"]), "$typ: Klasse für Variante $v"); }
            foreach ($e['klassen'] as $klasse => $seit) { T::ok(version_compare($seit, $version, '<='), "$klasse: seit ≤ VERSION"); }
            $felder = $e['felder'];
            array_walk_recursive($felder, static function ($w, $s) use ($typ, $typen): void {
                if ($s === 'typ') { T::ok(in_array($w, $typen, true), "$typ: Feldtyp $w bekannt"); }
            });
            foreach ($e['felder'] as $name => $f) { T::ok(isset($f['label']) && $f['label'] !== '', "$typ.$name: label"); }
        }
    },
    'Kontrast-Matrix: jede Schriftfarbe auf jeder Fläche' => static function () use ($k): void {
        $paare = array_map(static fn ($p) => "$p[0] auf $p[1]", $k->rahmen['farbpaare']);
        foreach (['--text', '--heading', '--primary', '--primary-dark'] as $schrift) {
            foreach (['--bg', '--bg-alt', '--card-bg', '--lp-akzent'] as $flaeche) { T::ok(in_array("$schrift auf $flaeche", $paare, true), "$schrift auf $flaeche geprüft"); }
        }
        foreach ($k->rahmen['farbpaare'] as $p) { T::ok($p[2] === false || $p[0] === '--focus', "$p[0] auf $p[1]: nur der Fokusrahmen darf mit 3:1 auskommen"); }
    },
    'jedes Beispiel ist ein gültiger Baustein' => static function () use ($k): void {
        $p = Profil::laden(H::testprofil());
        foreach ($k->bausteine as $typ => $e) {
            T::eq($e['beispiel']['typ'] ?? '', $typ, "$typ: Beispiel hat den Typ");
            $mitte = in_array($typ, ['hero', 'anmeldung'], true) ? [] : [$e['beispiel']];
            T::eq(H::texte(Schema::pruefen(H::seite($mitte), $k, $p, T::tmp())), '', "$typ: Beispiel gültig");
        }
    },
    'jede Klasse kommt im Musterbuch vor, jede lp-Klasse steht im Katalog' => static function () use ($k): void {
        $w = H::wurzel();
        $css = (string) file_get_contents("$w/lp/lp.css");
        preg_match_all('/\.(lp-[a-z0-9-]+)/', $css, $m);
        $html = '';
        // gebaute Seiten + Vorlagen (Klassen, die nur unter Bedingungen erscheinen, z. B. ohne Formularziel)
        foreach (array_merge(glob("$w/musterbuch/*/*.html") ?: [], glob("$w/lp/bausteine/*.php") ?: []) as $f) { $html .= (string) file_get_contents($f); }
        preg_match_all('/class="([^"]*)"/', $html, $c);
        $genutzt = array_unique(array_filter(explode(' ', implode(' ', $c[1])), static fn ($x) => str_starts_with($x, 'lp-')));
        $klassen = $k->klassen();
        foreach (array_unique(array_merge($m[1], $genutzt)) as $x) { T::ok(isset($klassen[$x]), "Klasse $x steht im Katalog"); }
        foreach (array_keys($klassen) as $x) {
            T::ok(in_array($x, $genutzt, true), "Klasse $x kommt im Musterbuch oder einer Vorlage vor");
        }
    },
    'neue Klassen stehen im CHANGELOG ihrer Version' => static function () use ($k, $version): void {
        $log = (string) file_get_contents(H::wurzel() . '/CHANGELOG.md');
        $start = strpos($log, "## [$version]");
        $ende = strpos($log, "\n## [", $start + 5);
        $abschnitt = substr($log, (int) $start, $ende === false ? null : $ende - $start);
        foreach ($k->klassen() as $x => $seit) {
            if ($seit === $version) { T::contains($abschnitt, "`$x`", "CHANGELOG $version nennt $x"); }
        }
    },
];
