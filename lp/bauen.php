<?php
/** Baut eine Landingpage aus ihrem Ordner (seite.json, ENDOSKELETON, profil/). --felder (3.74.0): jedes Textstück trägt seinen Pfad im Bauplan (Vorschau des Panels, nie live). */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Bauer;
use Endo\Lp\Cli;

Cli::lauf($argv, 'php lp/bauen.php <seitenordner> [--felder]', 1, static function (array $a): array {
    $felder = Cli::schalter($a, '--felder');
    if (count($a) !== 1 || str_starts_with($a[0], '--')) {
        throw new InvalidArgumentException('genau ein Seitenordner erwartet');
    }
    return Bauer::bauen(Cli::ordner($a[0]), true, null, $felder)->befunde;
}, 2);
