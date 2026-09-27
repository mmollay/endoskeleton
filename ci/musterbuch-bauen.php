<?php
/**
 * Baut das Musterbuch: je Fixture lp/musterbuch/<typ>--<variante>.json eine Seite unter musterbuch/<name>/ mit dem
 * neutralen Testprofil, dazu musterbuch/index.html und musterbuch/.htaccess.
 *
 *   php ci/musterbuch-bauen.php                                   → musterbuch/ im Repo (Golddateien, Testprofil)
 *   php ci/musterbuch-bauen.php --profil <_profil> --ziel <ordner> → Musterbuch in Kunden-Optik (z. B. lp.<kunde>/_musterbuch)
 *
 * profil/VERSION wird nicht mit abgelegt: die Haupt-.htaccess des Endoskeletons sperrt VERSION-Dateien mit
 * „Require all denied“ — ein Abruf zählte für fail2ban.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../lp/autoload.php';

use Endo\Lp\Bauer;
use Endo\Lp\Befund;
use Endo\Lp\Cli;
use Endo\Lp\Dateien;
use Endo\Lp\Katalog;
use Endo\Lp\ProfilKopie;
use Endo\Lp\Sammelordner;
use Endo\Lp\Text;

$w = dirname(__DIR__);
$args = array_slice($argv, 1);
$profil = Cli::option($args, '--profil') ?? "$w/lp/musterbuch/_testprofil";
$ziel = Cli::option($args, '--ziel') ?? "$w/musterbuch";
if ($args !== [] || !is_dir($profil) || (is_dir($ziel) && realpath($ziel) !== realpath("$w/musterbuch") && !is_file("$ziel/index.html") && (scandir($ziel) ?: []) !== ['.', '..'])) {
    fwrite(STDERR, "Aufruf: php ci/musterbuch-bauen.php [--profil <ordner>] [--ziel <ordner>] — Ziel muss leer oder ein Musterbuch sein\n");
    exit(2);
}
$version = trim((string) file_get_contents("$w/VERSION"));
$k = Katalog::laden();
Dateien::loeschen($ziel);
$liste = [];
foreach (glob("$w/lp/musterbuch/*.json") ?: [] as $fixture) {
    $name = basename($fixture, '.json');
    $o = "$ziel/$name";
    Dateien::kopieren($fixture, "$o/seite.json");
    Dateien::schreiben("$o/ENDOSKELETON", "$version\n");
    $pb = ProfilKopie::uebernehmen($o, $profil);
    if (Befund::sperrt($pb)) {
        fwrite(STDERR, "Profil: " . implode(' | ', array_map(static fn ($b) => $b->technik, $pb)) . "\n");
        exit(1);
    }
    unlink("$o/profil/VERSION");
    $e = Bauer::bauen($o);
    if (Befund::sperrt($e->befunde)) {
        fwrite(STDERR, "$name: " . implode(' | ', array_map(static fn ($b) => $b->technik, $e->befunde)) . "\n");
        exit(1);
    }
    [$typ, $variante] = explode('--', $name, 2);
    $b = $k->baustein($typ);
    $liste[] = sprintf('<li><a href="%s/">%s – %s</a> <span>(seit %s, geändert %s)</span></li>', $name, Text::e($b['name']), Text::e($variante), $b['seit'], $b['geaendert']);
}
Sammelordner::schreiben($ziel);
$css = substr(hash_file('sha256', "$ziel/hero--schlicht/es/lp.css"), 0, 10);
Dateien::schreiben("$ziel/index.html", <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Musterbuch der Landingpage-Bausteine – Endoskeleton $version</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="hero--schlicht/es/lp.css?v=$css">
</head>
<body class="lp-seite">
<main id="inhalt" class="lp-inhalt">
<section class="lp-block" aria-labelledby="mb-titel">
<div class="lp-block-innen container">
<h1 id="mb-titel">Musterbuch der Landingpage-Bausteine</h1>
<p>Jeder Baustein in jeder Variante (Endoskeleton $version).</p>
<ul>
{$liste[0]}
HTML . "\n" . implode("\n", array_slice($liste, 1)) . "\n</ul>\n</div>\n</section>\n</main>\n</body>\n</html>\n");
echo count($liste), " Seiten im Musterbuch gebaut\n";
