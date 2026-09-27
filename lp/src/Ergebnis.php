<?php
/** Ergebnis eines Baus: Befunde und die gebauten Dateien (Pfad relativ zum Seitenordner => Inhalt). */

declare(strict_types=1);

namespace Endo\Lp;

final class Ergebnis
{
    /**
     * @param list<Befund>          $befunde
     * @param array<string, string> $dateien
     */
    public function __construct(public readonly array $befunde, public readonly array $dateien = [])
    {
    }
}
