<?php
/**
 * Ein Punkt der Checkliste (Endo\Lp\Checkliste): Mangel oder Hinweis, mit Bereich. Getrennt von Befund, damit die Freigabe-Prüfung
 * (lp/pruefen.php, „sperrend“ | „hinweis“) und die Checkliste nie in einer Liste zusammenlaufen.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Pruefpunkt
{
    public function __construct(
        public readonly string $art,      // 'mangel' | 'hinweis'
        public readonly string $bereich,  // 'barrierefreiheit' | 'formular'
        public readonly string $pfad,     // Feld im Bauplan (bausteine.0.bild.alt), im Profil (profil.logo.alt) oder Datei (index.html)
        public readonly string $technik,  // für Entwickler
        public readonly string $kunde,    // für den Kunden: wo und was, verständlich
    ) {
    }

    /** @return array{art: string, bereich: string, pfad: string, technik: string, kunde: string} */
    public function alsArray(): array
    {
        return ['art' => $this->art, 'bereich' => $this->bereich, 'pfad' => $this->pfad, 'technik' => $this->technik, 'kunde' => $this->kunde];
    }

    /** @param list<self> $punkte */
    public static function mangelhaft(array $punkte): bool
    {
        foreach ($punkte as $p) {
            if ($p->art === 'mangel') {
                return true;
            }
        }
        return false;
    }
}
