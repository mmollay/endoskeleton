<?php
/** WCAG-Kontrast wie gkContrast() in GRIDKit: Text ≥ 4,5:1, große Schrift und Bedienelemente ≥ 3:1. */

declare(strict_types=1);

namespace Endo\Lp;

final class Kontrast
{
    public static function hex(string $farbe): ?string
    {
        $f = strtolower(trim($farbe));
        if (preg_match('/^#([0-9a-f]{3})$/', $f, $m)) {
            return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        return preg_match('/^#[0-9a-f]{6}$/', $f) ? $f : null;
    }

    public static function verhaeltnis(string $a, string $b): float
    {
        $la = self::leuchtdichte(self::hex($a) ?? throw new \InvalidArgumentException("keine Hex-Farbe: $a"));
        $lb = self::leuchtdichte(self::hex($b) ?? throw new \InvalidArgumentException("keine Hex-Farbe: $b"));
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * @param array<string, string>            $tokens  --name => Wert
     * @param list<array{0: string, 1: string, 2: bool}> $paare  [vorn, hinten, groß]
     * @return list<Befund>
     */
    public static function pruefe(array $tokens, array $paare, string $pfad, string $wo): array
    {
        $befunde = [];
        foreach ($paare as [$vorn, $hinten, $gross]) {
            $a = self::hex($tokens[$vorn] ?? '');
            $b = self::hex($tokens[$hinten] ?? '');
            if ($a === null || $b === null) {
                $fehlt = $a === null ? $vorn : $hinten;
                $befunde[] = Befund::sperrend($pfad, "Farbe $fehlt fehlt im Profil oder ist keine Hex-Farbe", "$wo: Im Kundenprofil fehlt eine Farbe ($fehlt).");
                continue;
            }
            $grenze = $gross ? 3.0 : 4.5;
            $r = self::verhaeltnis($a, $b);
            if ($r < $grenze) {
                // gerundet, aber nie so, dass ein Durchfaller wie bestanden aussieht (4,497 → 4,49, nicht 4,50)
                $zahl = number_format(round($r, 2) >= $grenze ? floor($r * 100) / 100 : round($r, 2), 2, ',', '');
                $soll = str_replace('.', ',', (string) $grenze);
                $befunde[] = Befund::sperrend($pfad, "Kontrast $vorn auf $hinten = $zahl:1 < $soll:1",
                    "$wo: Schrift und Hintergrund heben sich zu wenig ab ($zahl:1, nötig sind $soll:1). Bitte die Profilfarben anpassen.");
            }
        }
        return $befunde;
    }

    private static function leuchtdichte(string $hex): float
    {
        $kanal = static function (string $h): float {
            $c = hexdec($h) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $kanal(substr($hex, 1, 2)) + 0.7152 * $kanal(substr($hex, 3, 2)) + 0.0722 * $kanal(substr($hex, 5, 2));
    }
}
