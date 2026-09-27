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
    'die acht Bausteine von 3.51.0' => static fn () => T::eq(array_keys($k->bausteine), ['hero', 'termin', 'themen', 'video', 'erfahrungen', 'faq', 'anmeldung', 'abschluss'], 'Typen'),
    'jeder Eintrag vollständig' => static function () use ($k, $version, $typen): void {
        foreach ($k->bausteine as $typ => $e) {
            foreach (['name', 'seit', 'geaendert', 'klassen', 'varianten', 'felder', 'regeln', 'farbpaare', 'beispiel'] as $s) {
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
            foreach ($e['farbpaare'] as $paar) { T::ok(count($paar) === 3 && is_bool($paar[2]), "$typ: Farbpaar mit groß/klein"); }
        }
    },
    'jedes Beispiel ist ein gültiger Baustein' => static function () use ($k): void {
        $p = Profil::laden(H::testprofil());
        foreach ($k->bausteine as $typ => $e) {
            T::eq($e['beispiel']['typ'] ?? '', $typ, "$typ: Beispiel hat den Typ");
            $mitte = in_array($typ, ['hero', 'anmeldung'], true) ? [] : [$e['beispiel']];
            T::eq(H::texte(Schema::pruefen(H::seite($mitte), $k, $p, T::tmp())), '', "$typ: Beispiel gültig");
        }
    },
];
