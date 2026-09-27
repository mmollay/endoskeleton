<?php
/** Technischer Fehler beim Bau (falsche Versionsmarke, fehlendes Profil) — Exit-Code 2, kein Kundenbefund. */

declare(strict_types=1);

namespace Endo\Lp;

final class BauFehler extends \RuntimeException
{
}
