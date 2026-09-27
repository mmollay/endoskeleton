<?php
/** Prüft eine gebaute Landingpage vor der Freigabe. --heute JJJJ-MM-TTTHH:MM stellt die Uhr (Tests). */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Cli;
use Endo\Lp\Pruefer;
use Endo\Lp\Text;

Cli::lauf($argv, 'php lp/pruefen.php <seitenordner> [--heute JJJJ-MM-TTTHH:MM]', 1, static function (array $a): array {
    $heute = Cli::option($a, '--heute');
    if ($heute !== null && !Text::zeitpunktGueltig($heute)) {
        throw new InvalidArgumentException("--heute: kein Zeitpunkt JJJJ-MM-TTTHH:MM: $heute");
    }
    return Pruefer::pruefen(Cli::ordner($a[0] ?? ''), $heute);
});
