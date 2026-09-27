<?php
/**
 * Gemeinsamer Rahmen der Kommandozeilen-Skripte: Befunde als JSON-Lines auf stdout
 * ({art, pfad, technik, kunde}), Exit 0 = in Ordnung, 1 = sperrende Befunde, 2 = technischer Fehler (stderr).
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Cli
{
    /** @param callable(list<string>): list<Befund> $fn  bekommt die Argumente ohne Skriptnamen */
    public static function lauf(array $argv, string $aufruf, int $mindestens, callable $fn): never
    {
        $args = array_slice($argv, 1);
        if (count($args) < $mindestens || in_array($args[0] ?? '', ['-h', '--help'], true)) {
            fwrite(STDERR, "Aufruf: $aufruf\n");
            exit(2);
        }
        try {
            $befunde = $fn($args);
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
            exit(2);
        }
        foreach ($befunde as $b) {
            echo json_encode($b->alsArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        }
        exit(Befund::sperrt($befunde) ? 1 : 0);
    }

    /** Wert einer Option --name <wert>; entfernt beide aus $args. */
    public static function option(array &$args, string $name): ?string
    {
        $i = array_search($name, $args, true);
        if ($i === false) {
            return null;
        }
        $wert = $args[$i + 1] ?? throw new \InvalidArgumentException("$name braucht einen Wert");
        array_splice($args, $i, 2);
        return $wert;
    }

    public static function ordner(string $pfad): string
    {
        return realpath($pfad) ?: throw new \InvalidArgumentException("Ordner nicht gefunden: $pfad");
    }
}
