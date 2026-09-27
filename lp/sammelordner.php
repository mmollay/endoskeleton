<?php
/** Schreibt die .htaccess eines Sammelordners (lp.<kundendomain>/, auch musterbuch/) — siehe lp/src/Sammelordner.php. */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Cli;
use Endo\Lp\Sammelordner;

Cli::lauf($argv, 'php lp/sammelordner.php <sammelordner>', 1, static function (array $a): array {
    Sammelordner::schreiben(Cli::ordner($a[0]));
    return [];
});
