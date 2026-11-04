<?php
/**
 * Das Musterbuch: je Variante jedes Bausteins eine Fixture (lp/musterbuch/<typ>--<variante>.json) und ihre gebaute
 * Seite unter musterbuch/<typ>--<variante>/ — zugleich die Golddateien: jeder Neubau muss Byte für Byte gleich sein.
 * Weitere Beispiele derselben Variante heißen <typ>--<variante>--<zusatz> (3.62.0: eigenes Video); ihre Probedateien liegen in lp/musterbuch/vid/.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Bewegung;
use Endo\Lp\Bildgroessen;
use Endo\Lp\Katalog;
use Endo\Lp\Kontrast;
use Endo\Lp\Profil;
use Endo\Lp\Pruefer;
use Endo\Lp\Sammelordner;
use Endo\Lp\Videodatei;

$k = Katalog::laden();
$w = H::wurzel();
$soll = [];
foreach ($k->bausteine as $typ => $e) {
    foreach ($e['varianten'] as $v) { $soll[] = "$typ--$v"; }
}
sort($soll);
$ist = array_map(static fn ($f) => basename($f, '.json'), glob("$w/lp/musterbuch/*.json") ?: []);
sort($ist);
/** Je Variante genau eine Grundfixture; die übrigen (drei Namensteile) sind weitere Beispiele einer Variante. */
$grund = array_values(array_filter($ist, static fn ($n) => substr_count($n, '--') === 1));
$zusatz = array_values(array_filter($ist, static fn ($n) => substr_count($n, '--') === 2));

$tests = [
    'Musterbuch in Kunden-Optik: fremdes Profil ohne die Beispielbilder' => static function () use ($w, $ist): void {
        $p = H::profilKopie();
        file_put_contents("$p/profil.css", str_replace(['--primary: #1d5c8c;', '--card-bg: #fbfcfe;'], ['--primary: #7a1d5c;', '--card-bg: #ffffff;'], (string) file_get_contents("$p/profil.css")));
        foreach (glob("$p/bilder/*.webp") ?: [] as $f) { unlink($f); }
        file_put_contents("$p/bilder/bilder.json", '[]');
        $pj = json_decode((string) file_get_contents("$p/profil.json"), true);
        $pj['link_hosts'] = ['beispiel.invalid'];   // Kunde ohne die Beispiel-Hosts der Fixtures
        file_put_contents("$p/profil.json", json_encode($pj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ziel = T::tmp() . '/_musterbuch';
        $repoVorher = hash_file('sha256', "$w/musterbuch/hero--schlicht/index.html");
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($p) . ' --ziel ' . escapeshellarg($ziel) . ' 2>&1', $aus, $rc);
        T::eq($rc, 0, 'gebaut: ' . implode(' ', $aus));
        $dirs = array_map('basename', glob("$ziel/*", GLOB_ONLYDIR) ?: []);
        sort($dirs);
        T::eq($dirs, $ist, 'alle Seiten');
        T::contains((string) @file_get_contents("$ziel/hero--bild-rechts/es/lp.css"), '--primary: #7a1d5c;', 'mit den Farben des Kundenprofils');
        T::ok(is_file("$ziel/hero--bild-rechts/es/bilder/beispiel-vortrag.webp"), 'neutrale Beispielbilder mitgebracht');
        T::eq(hash_file('sha256', "$w/musterbuch/hero--schlicht/index.html"), $repoVorher, 'Repo-Musterbuch unberührt');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($p) . ' --ziel ' . escapeshellarg($ziel) . ' 2>&1', $aus, $rc);
        T::eq($rc, 0, 'ein früheres Musterbuch darf ersetzt werden');
        $kaputt = T::tmp() . '/kaputt';
        $pk = H::profilKopie();
        file_put_contents("$pk/profil.css", str_replace('--text: #2b3440;', '--text: #b0b8c0;', (string) file_get_contents("$pk/profil.css")));
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($pk) . ' --ziel ' . escapeshellarg($kaputt) . ' 2>&1', $aus, $rc);
        T::ok($rc === 1 && is_file("$kaputt/.musterbuch"), 'auch ein abgebrochener Bau hinterlässt die Markierung (sonst ließe sich der Rest nie ersetzen)');
        $fremd = T::tmp() . '/webseite';
        mkdir($fremd);
        file_put_contents("$fremd/index.html", 'Kundenseite');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$w/ci/musterbuch-bauen.php") . ' --profil ' . escapeshellarg($p) . ' --ziel ' . escapeshellarg($fremd) . ' 2>&1', $aus, $rc);
        T::ok($rc === 2 && file_get_contents("$fremd/index.html") === 'Kundenseite', 'ein fremder Ordner wird nie gelöscht');
    },
    'je Variante genau eine Fixture' => static fn () => T::eq($grund, $soll, 'Fixtures = Varianten des Katalogs'),
    'weitere Beispiele gehören zu einer Variante des Katalogs' => static function () use ($zusatz, $soll): void {
        foreach ($zusatz as $name) { T::ok(in_array(implode('--', array_slice(explode('--', $name), 0, 2)), $soll, true), "$name gehört zu einer Variante"); }
    },
    'Übersicht und Sperren des Musterbuchs' => static function () use ($w, $ist): void {
        $index = (string) @file_get_contents("$w/musterbuch/index.html");
        foreach ($ist as $name) { T::contains($index, "href=\"$name/\"", "Übersicht verlinkt $name"); }
        T::eq((string) @file_get_contents("$w/musterbuch/.htaccess"), Sammelordner::HTACCESS, 'musterbuch/.htaccess = Sammelordner');
        $dirs = array_map('basename', glob("$w/musterbuch/*", GLOB_ONLYDIR) ?: []);
        sort($dirs);
        T::eq($dirs, $ist, 'keine veralteten Ordner im Musterbuch');
    },
];
$tests['dunkles Testprofil (3.70.0): wirklich dunkel, neutral wie das helle, besteht die Kontrastmatrix'] = static function () use ($w, $k): void {
    $hell = Profil::laden(H::testprofil());
    $dunkel = Profil::laden(H::testprofilDunkel());
    T::ok(Kontrast::verhaeltnis($dunkel->tokens['--bg'], '#000000') < 1.3, 'Seitengrund dunkel (' . $dunkel->tokens['--bg'] . ')');
    T::ok(Kontrast::verhaeltnis($dunkel->tokens['--card-bg'], '#000000') < 1.6, 'Karten dunkel (' . $dunkel->tokens['--card-bg'] . ')');
    T::eq($dunkel->pruefen(), [], 'Profil ohne Befund');
    T::eq(Kontrast::pruefe($dunkel->tokens, $k->rahmen['farbpaare'], 'profil.css', 'Kundenprofil'), [], 'Kontrastmatrix bestanden');
    $ohneName = static fn (array $d): array => array_diff_key($d, ['name' => 1]);
    T::eq($ohneName($dunkel->daten), $ohneName($hell->daten), 'dieselben Angaben wie das helle Testprofil (nur der Name unterscheidet sich)');
    T::eq(array_keys($dunkel->tokens), array_keys($hell->tokens), 'dieselben Tokens');
    T::eq((string) file_get_contents(H::testprofilDunkel() . '/bilder/bilder.json'), (string) file_get_contents(H::testprofil() . '/bilder/bilder.json'), 'dieselben Bilder');
};
foreach ($ist as $name) {
    $tests["$name: baut auch mit dem dunklen Testprofil ohne Befund (3.70.0)"] = static function () use ($w, $name): void {
        $seite = json_decode((string) file_get_contents("$w/lp/musterbuch/$name.json"), true);
        $o = H::ordner($seite, null, H::testprofilDunkel());
        $videos = Videodatei::imPlan($seite);
        if ($videos) { mkdir("$o/vid", 0700, true); }
        foreach ($videos as $video) { copy("$w/lp/musterbuch/$video", "$o/$video") || throw new RuntimeException("Probedatei fehlt: $video"); }
        H::bewegungen($o, $seite);
        $e = Bauer::bauen($o);
        T::eq(H::texte($e->befunde), '', 'keine Befunde');
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o, '2026-09-27T12:00'))), '', 'Prüfer ohne sperrende Befunde');
    };
    $tests["$name: baut ohne Befund und gleicht dem Musterbuch"] = static function () use ($w, $name): void {
        $seite = json_decode((string) file_get_contents("$w/lp/musterbuch/$name.json"), true);
        $o = H::ordner($seite);
        $videos = Videodatei::imPlan($seite);
        if ($videos) { mkdir("$o/vid", 0700, true); }
        foreach ($videos as $video) { copy("$w/lp/musterbuch/$video", "$o/$video") || throw new RuntimeException("Probedatei fehlt: $video"); }
        H::bewegungen($o, $seite);
        $e = Bauer::bauen($o);
        T::eq(H::texte($e->befunde), '', 'keine Befunde');
        foreach ($e->dateien as $pfad => $inhalt) {
            if (str_starts_with($pfad, Bildgroessen::ORDNER . '/')) {
                // Kleinere Fassungen der Bilder (3.70.0): die Bytes hängen an der libwebp des Rechners — gleich sein müssen Format und Maße
                $gold = @getimagesizefromstring((string) @file_get_contents("$w/musterbuch/$name/$pfad"));
                $neu = getimagesizefromstring($inhalt);
                T::ok($gold !== false && array_slice($gold, 0, 3) === array_slice((array) $neu, 0, 3), "musterbuch/$name/$pfad: WebP in denselben Maßen (sonst: php ci/musterbuch-bauen.php)");
                continue;
            }
            T::ok(@file_get_contents("$w/musterbuch/$name/$pfad") === $inhalt, "musterbuch/$name/$pfad aktuell (sonst: php ci/musterbuch-bauen.php)");
        }
        T::eq(@file_get_contents("$w/musterbuch/$name/seite.json"), file_get_contents("$w/lp/musterbuch/$name.json"), 'Bauplan kopiert');
        $ist = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$w/musterbuch/$name", FilesystemIterator::SKIP_DOTS)) as $f) {
            $r = substr($f->getPathname(), strlen("$w/musterbuch/$name") + 1);
            if (!str_starts_with($r, 'profil/')) { $ist[] = $r; }
        }
        $soll = array_merge(array_keys($e->dateien), ['seite.json', 'ENDOSKELETON'], $videos, Bewegung::imPlan($seite));
        sort($ist); sort($soll);
        T::eq($ist, $soll, 'keine veralteten Dateien');
        T::ok(!file_exists("$w/musterbuch/$name/profil/VERSION"), 'keine VERSION-Datei unter dem DocumentRoot (die Haupt-.htaccess sperrt sie mit „denied“ → fail2ban)');
        // Der Prüfer vergleicht Byte für Byte mit einem Neubau — das gilt auf dem Rechner, der gebaut hat (3.70.0: die Bildfassungen hängen an
        // seiner libwebp). Darum prüft er hier den frischen Bau; dass das Musterbuch dem gleicht, steht oben.
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o, '2026-09-27T12:00'))), '', 'Prüfer ohne sperrende Befunde');
    };
}
return $tests;
