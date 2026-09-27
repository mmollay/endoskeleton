<?php
/**
 * Format „rechtstext" für Impressum und Datenschutz: ## und ### als Überschriften (die H1 setzt der Rahmen),
 * Absätze, Listen mit „- ", **fett**, Links [Text](https://…) nur auf erlaubte Hosts oder mailto:.
 * Alles andere wird maskiert.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Rechtstext
{
    public static function platzhalter(string $text): bool
    {
        return str_contains($text, '{{');
    }

    /** @param list<string> $hosts */
    public static function html(string $text, array $hosts): string
    {
        $aus = [];
        $absatz = [];
        $liste = [];
        $leeren = static function () use (&$aus, &$absatz, &$liste, $hosts): void {
            if ($absatz) {
                $aus[] = '<p>' . implode("<br>\n", array_map(static fn (string $z): string => self::zeile($z, $hosts), $absatz)) . '</p>';
                $absatz = [];
            }
            if ($liste) {
                $aus[] = "<ul>\n" . implode("\n", array_map(static fn (string $z): string => '<li>' . self::zeile($z, $hosts) . '</li>', $liste)) . "\n</ul>";
                $liste = [];
            }
        };
        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $roh) {
            $z = trim($roh);
            if ($z === '') {
                $leeren();
            } elseif (preg_match('/^(#{2,3}) (.+)$/u', $z, $m)) {
                $leeren();
                $h = strlen($m[1]);
                $aus[] = "<h$h>" . self::zeile($m[2], $hosts) . "</h$h>";
            } elseif (str_starts_with($z, '- ')) {
                if ($absatz) { $l = $liste; $liste = []; $leeren(); $liste = $l; }
                $liste[] = substr($z, 2);
            } else {
                if ($liste) { $leeren(); }
                $absatz[] = $z;
            }
        }
        $leeren();
        return implode("\n", $aus);
    }

    /** Eine Zeile: Links auf erlaubte Ziele werden <a>, alles andere Text mit **fett**. */
    private static function zeile(string $z, array $hosts): string
    {
        $aus = '';
        $rest = $z;
        while (preg_match('/\[([^\]]+)\]\(([^)\s]+)\)/u', $rest, $m, PREG_OFFSET_CAPTURE)) {
            [$ganz, $pos] = $m[0];
            $aus .= Text::inline(substr($rest, 0, $pos));
            $ziel = $m[2][0];
            $ok = Text::https($ziel, $hosts)
                || (str_starts_with($ziel, 'mailto:') && filter_var(substr($ziel, 7), FILTER_VALIDATE_EMAIL) !== false);
            $aus .= $ok ? '<a href="' . Text::e($ziel) . '">' . Text::inline($m[1][0]) . '</a>' : Text::inline($ganz);
            $rest = substr($rest, $pos + strlen($ganz));
        }
        return $aus . Text::inline($rest);
    }
}
