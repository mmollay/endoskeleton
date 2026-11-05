<?php
/**
 * Checkliste einer gebauten Landingpage: Barrierefreiheit und Formular (lp/src/Checkliste.php). JSON-Lines {art, bereich, pfad, technik, kunde},
 * Exit 0 = keine Mängel (Hinweise erlaubt), 1 = Mängel, 2 = technischer Fehler. Ändert nichts an der Freigabe-Prüfung (lp/pruefen.php).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Checkliste;
use Endo\Lp\Cli;
use Endo\Lp\Pruefpunkt;

Cli::lauf($argv, 'php lp/checkliste.php <seitenordner>', 1, static fn (array $a): array => Checkliste::pruefen(Cli::ordner($a[0])), null, Pruefpunkt::mangelhaft(...));
