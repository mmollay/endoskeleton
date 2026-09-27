<?php
/** Ein Befund aus Bau oder Prüfung: sperrend (verhindert Bau/Freigabe) oder Hinweis zum Gegenlesen. */

declare(strict_types=1);

namespace Endo\Lp;

final class Befund
{
    public function __construct(
        public readonly string $art,      // 'sperrend' | 'hinweis'
        public readonly string $pfad,     // technischer Feldpfad, z. B. bausteine.4.eintraege.0.text
        public readonly string $technik,  // für Entwickler
        public readonly string $kunde,    // für den Kunden: Abschnitt mit Nummer und Namen, verständlich
    ) {
    }

    public static function sperrend(string $pfad, string $technik, string $kunde): self
    {
        return new self('sperrend', $pfad, $technik, $kunde);
    }

    public static function hinweis(string $pfad, string $technik, string $kunde): self
    {
        return new self('hinweis', $pfad, $technik, $kunde);
    }

    /** @return array{art: string, pfad: string, technik: string, kunde: string} */
    public function alsArray(): array
    {
        return ['art' => $this->art, 'pfad' => $this->pfad, 'technik' => $this->technik, 'kunde' => $this->kunde];
    }

    /** @param list<self> $befunde */
    public static function sperrt(array $befunde): bool
    {
        foreach ($befunde as $b) {
            if ($b->art === 'sperrend') {
                return true;
            }
        }
        return false;
    }
}
