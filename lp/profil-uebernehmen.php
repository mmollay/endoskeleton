<?php
/** „Profil übernehmen“: kopiert das Kundenprofil in den Ordner einer Landingpage (profil/). */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Cli;
use Endo\Lp\ProfilKopie;

Cli::lauf($argv, 'php lp/profil-uebernehmen.php <seitenordner> <profilordner>', 2,
    static fn (array $a): array => ProfilKopie::uebernehmen(Cli::ordner($a[0]), Cli::ordner($a[1])));
