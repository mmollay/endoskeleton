<?php
/** Baut eine Landingpage aus ihrem Ordner (seite.json, ENDOSKELETON, profil/). */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Bauer;
use Endo\Lp\Cli;

Cli::lauf($argv, 'php lp/bauen.php <seitenordner>', 1, static fn (array $a): array => Bauer::bauen(Cli::ordner($a[0]))->befunde);
