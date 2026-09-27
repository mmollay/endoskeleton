<?php
/** Dateihelfer: rekursiv löschen und kopieren (sortiert, damit Läufe gleich ablaufen), schreiben mit Ordnern. */

declare(strict_types=1);

namespace Endo\Lp;

final class Dateien
{
    public static function loeschen(string $pfad): void
    {
        if (is_link($pfad) || is_file($pfad)) {
            unlink($pfad);
            return;
        }
        if (!is_dir($pfad)) {
            return;
        }
        foreach (self::eintraege($pfad) as $e) {
            self::loeschen("$pfad/$e");
        }
        rmdir($pfad);
    }

    /** Kopiert eine Datei oder einen Ordner; Verweise werden nicht verfolgt, sondern abgelehnt. */
    public static function kopieren(string $von, string $nach): void
    {
        if (is_link($von)) {
            throw new \RuntimeException("Verweis wird nicht kopiert: $von");
        }
        if (is_file($von)) {
            self::ordner(dirname($nach));
            if (!copy($von, $nach)) {
                throw new \RuntimeException("Kopieren fehlgeschlagen: $von");
            }
            return;
        }
        self::ordner($nach);
        foreach (self::eintraege($von) as $e) {
            self::kopieren("$von/$e", "$nach/$e");
        }
    }

    public static function schreiben(string $datei, string $inhalt): void
    {
        self::ordner(dirname($datei));
        if (file_put_contents($datei, $inhalt) === false) {
            throw new \RuntimeException("Schreiben fehlgeschlagen: $datei");
        }
    }

    /** @return list<string> Einträge eines Ordners, sortiert, ohne . und .. */
    public static function eintraege(string $ordner): array
    {
        $e = array_values(array_diff(scandir($ordner) ?: [], ['.', '..']));
        sort($e, SORT_STRING);
        return $e;
    }

    private static function ordner(string $d): void
    {
        if (!is_dir($d) && !mkdir($d, 0755, true) && !is_dir($d)) {
            throw new \RuntimeException("Ordner nicht anlegbar: $d");
        }
    }
}
