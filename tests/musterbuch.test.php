<?php
/**
 * Das Musterbuch: je Variante jedes Bausteins eine Fixture (lp/musterbuch/<typ>--<variante>.json) und ihre gebaute
 * Seite unter musterbuch/<typ>--<variante>/ — zugleich die Golddateien: jeder Neubau muss Byte für Byte gleich sein.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;
use Endo\Lp\Sammelordner;

$k = Katalog::laden();
$w = H::wurzel();
$soll = [];
foreach ($k->bausteine as $typ => $e) {
    foreach ($e['varianten'] as $v) { $soll[] = "$typ--$v"; }
}
sort($soll);
$ist = array_map(static fn ($f) => basename($f, '.json'), glob("$w/lp/musterbuch/*.json") ?: []);
sort($ist);

$tests = [
    'je Variante genau eine Fixture' => static fn () => T::eq($ist, $soll, 'Fixtures = Varianten des Katalogs'),
    'Übersicht und Sperren des Musterbuchs' => static function () use ($w, $soll): void {
        $index = (string) @file_get_contents("$w/musterbuch/index.html");
        foreach ($soll as $name) { T::contains($index, "href=\"$name/\"", "Übersicht verlinkt $name"); }
        T::eq((string) @file_get_contents("$w/musterbuch/.htaccess"), Sammelordner::HTACCESS, 'musterbuch/.htaccess = Sammelordner');
        $dirs = array_map('basename', glob("$w/musterbuch/*", GLOB_ONLYDIR) ?: []);
        sort($dirs);
        T::eq($dirs, $soll, 'keine veralteten Ordner im Musterbuch');
    },
];
foreach ($soll as $name) {
    $tests["$name: baut ohne Befund und gleicht dem Musterbuch"] = static function () use ($w, $name): void {
        $seite = json_decode((string) file_get_contents("$w/lp/musterbuch/$name.json"), true);
        $o = H::ordner($seite);
        $e = Bauer::bauen($o, false);
        T::eq(H::texte($e->befunde), '', 'keine Befunde');
        foreach ($e->dateien as $pfad => $inhalt) {
            T::ok(@file_get_contents("$w/musterbuch/$name/$pfad") === $inhalt, "musterbuch/$name/$pfad aktuell (sonst: php ci/musterbuch-bauen.php)");
        }
        T::eq(@file_get_contents("$w/musterbuch/$name/seite.json"), file_get_contents("$w/lp/musterbuch/$name.json"), 'Bauplan kopiert');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen("$w/musterbuch/$name", '2026-09-27T12:00'))), '', 'Prüfer ohne sperrende Befunde');
    };
}
return $tests;
