<?php
/** Gemeinsame Helfer der Tests: gültige Baupläne und Seitenordner mit dem neutralen Testprofil. */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use Endo\Lp\Dateien;
use Endo\Lp\Katalog;

final class H
{
    public static function wurzel(): string
    {
        return dirname(__DIR__);
    }

    public static function testprofil(): string
    {
        return self::wurzel() . '/lp/musterbuch/_testprofil';
    }

    /** Ein gültiger Bauplan: Kopfbereich, die übergebenen Bausteine, Anmeldung (Ziel der Knöpfe). */
    public static function seite(array $mitte = [], array $meta = []): array
    {
        $k = Katalog::laden();
        return [
            'format' => 1,
            'meta' => $meta + ['titel' => 'Gesund durch den Winter', 'beschreibung' => 'Kostenloser Online-Abend mit Fragerunde.'],
            'bausteine' => array_merge([$k->baustein('hero')['beispiel']], $mitte, [$k->baustein('anmeldung')['beispiel']]),
        ];
    }

    /** Legt einen Seitenordner mit Bauplan, Marke und Profilkopie an und gibt den Pfad zurück. */
    public static function ordner(array $seite, ?string $marke = null, ?string $profil = null): string
    {
        $d = T::tmp() . '/seite';
        mkdir($d, 0700, true);
        file_put_contents("$d/seite.json", json_encode($seite, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        file_put_contents("$d/ENDOSKELETON", ($marke ?? trim((string) file_get_contents(self::wurzel() . '/VERSION'))) . "\n");
        Dateien::kopieren($profil ?? self::testprofil(), "$d/profil");
        file_put_contents("$d/profil/VERSION", "1.0.0\n");
        return $d;
    }

    /** Kopie des Testprofils, die ein Test verändern darf. */
    public static function profilKopie(): string
    {
        $d = T::tmp() . '/profil';
        Dateien::kopieren(self::testprofil(), $d);
        return $d;
    }

    /** @param list<\Endo\Lp\Befund> $befunde */
    public static function sperrend(array $befunde): array
    {
        return array_values(array_filter($befunde, static fn ($b) => $b->art === 'sperrend'));
    }

    /** @param list<\Endo\Lp\Befund> $befunde */
    public static function texte(array $befunde): string
    {
        return implode("\n", array_map(static fn ($b) => "[$b->art] $b->pfad | $b->technik | $b->kunde", $befunde));
    }
}
