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
    'Musterbuch in Kunden-Optik: fremdes Profil ohne die Beispielbilder' => static function () use ($w, $soll): void {
        $p = H::profilKopie();
        file_put_contents("$p/profil.css", str_replace(['--primary: #1d5c8c;', '--card-bg: #fbfcfe;'], ['--primary: #7a1d5c;', '--card-bg: #ffffff;'], (string) file_get_contents("$p/profil.css")));
        foreach (glob("$p/bilder/*.webp") ?: [] as $f) { unlink($f); }
        file_put_contents("$p/bilder/bilder.json", '[]');
        $ziel = T::tmp() . '/_musterbuch';
        $repoVorher = hash_file('sha256', "$w/musterbuch/hero--schlicht/index.html");
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($p) . ' --ziel ' . escapeshellarg($ziel) . ' 2>&1', $aus, $rc);
        T::eq($rc, 0, 'gebaut: ' . implode(' ', $aus));
        $dirs = array_map('basename', glob("$ziel/*", GLOB_ONLYDIR) ?: []);
        sort($dirs);
        T::eq($dirs, $soll, 'alle Seiten');
        T::contains((string) @file_get_contents("$ziel/hero--bild-rechts/es/lp.css"), '--primary: #7a1d5c;', 'mit den Farben des Kundenprofils');
        T::ok(is_file("$ziel/hero--bild-rechts/es/bilder/beispiel-vortrag.webp"), 'neutrale Beispielbilder mitgebracht');
        T::eq(hash_file('sha256', "$w/musterbuch/hero--schlicht/index.html"), $repoVorher, 'Repo-Musterbuch unberührt');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($p) . ' --ziel ' . escapeshellarg($ziel) . ' 2>&1', $aus, $rc);
        T::eq($rc, 0, 'ein früheres Musterbuch darf ersetzt werden');
        $fremd = T::tmp() . '/webseite';
        mkdir($fremd);
        file_put_contents("$fremd/index.html", 'Kundenseite');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($p) . ' --ziel ' . escapeshellarg($fremd) . ' 2>&1', $aus, $rc);
        T::ok($rc === 2 && file_get_contents("$fremd/index.html") === 'Kundenseite', 'ein fremder Ordner wird nie gelöscht');
    },
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
        $ist = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$w/musterbuch/$name", FilesystemIterator::SKIP_DOTS)) as $f) {
            $r = substr($f->getPathname(), strlen("$w/musterbuch/$name") + 1);
            if (!str_starts_with($r, 'profil/')) { $ist[] = $r; }
        }
        $soll = array_merge(array_keys($e->dateien), ['seite.json', 'ENDOSKELETON']);
        sort($ist); sort($soll);
        T::eq($ist, $soll, 'keine veralteten Dateien');
        T::ok(!file_exists("$w/musterbuch/$name/profil/VERSION"), 'keine VERSION-Datei unter dem DocumentRoot (die Haupt-.htaccess sperrt sie mit „denied“ → fail2ban)');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen("$w/musterbuch/$name", '2026-09-27T12:00'))), '', 'Prüfer ohne sperrende Befunde');
    };
}
return $tests;
