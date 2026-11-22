<?php
/** Prüft eine gebaute Landingpage vor der Freigabe. --heute JJJJ-MM-TTTHH:MM stellt die Uhr (Tests); --felder: der Ordner ist mit bauen.php --felder gebaut. */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Cli;
use Endo\Lp\Pruefer;
use Endo\Lp\Text;

Cli::lauf($argv, 'php lp/pruefen.php <seitenordner> [--heute JJJJ-MM-TTTHH:MM] [--felder]', 1, static function (array $a): array {
    $felder = Cli::schalter($a, '--felder');   // der Ordner ist mit bauen.php --felder gebaut (3.74.0)
    $heute = Cli::option($a, '--heute');
    if ($heute !== null && !Text::zeitpunktGueltig($heute)) {
        throw new InvalidArgumentException("--heute: kein Zeitpunkt JJJJ-MM-TTTHH:MM: $heute");
    }
    if (count($a) !== 1) {
        throw new InvalidArgumentException('genau ein Seitenordner erwartet');
    }
    return Pruefer::pruefen(Cli::ordner($a[0]), $heute, null, $felder);
}, 4);
