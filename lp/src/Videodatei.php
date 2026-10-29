<?php
/**
 * Die eigene Videodatei einer Seite (3.62.0): im Bauplan nur ein Pfad (Kopfbereich: video_datei, Baustein „video“: datei), die Datei selbst liegt
 * im Seitenordner unter vid/. Es kommt nur ein MP4 mit festem Namen durch: Muster, gewöhnliche Datei (kein Link), „ftyp“ an Stelle 4–8, höchstens
 * 40 MB. Eine einzige Prüfung für Schema (Bauplan) und Kontext (beim Bauen noch einmal) — so läuft keine Datei am Prüfer vorbei.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Videodatei
{
    /** Einziger erlaubter Pfad: vid/eigen-<16 Hexzeichen>.mp4. \z statt $ — ein Zeilenumbruch am Ende fiele bei $ durch. */
    public const MUSTER = '#^vid/eigen-[0-9a-f]{16}\.mp4\z#';
    /** Größte erlaubte Datei: 40 MiB = 41 943 040 Byte. */
    public const MAX_BYTES = 40 * 1024 * 1024;
    /** Ein MP4 beginnt mit einem Kasten: 4 Byte Länge, „ftyp“, 4 Byte Hauptmarke. */
    private const KOPF_BYTES = 12;

    /**
     * Die gültig benannten Videopfade eines Bauplans (ohne Doppelte) — damit kopiert das Musterbuch die Probedateien zu den Fixtures.
     *
     * @return list<string>
     */
    public static function imPlan(array $plan): array
    {
        $pfade = [];
        array_walk_recursive($plan, static function ($w) use (&$pfade): void {
            if (is_string($w) && preg_match(self::MUSTER, $w)) {
                $pfade[$w] = true;
            }
        });
        return array_keys($pfade);
    }

    /** @return array{0: string, 1: string}|null [technik, kunde] des ersten Fehlers, null wenn alles passt */
    public static function fehler(string $ordner, string $pfad): ?array
    {
        if (!preg_match(self::MUSTER, $pfad)) {
            return ['Pfad passt nicht zu vid/eigen-<16 Hexzeichen>.mp4', 'Der Name der Videodatei ist ungültig.'];
        }
        clearstatcache();
        $datei = "$ordner/$pfad";
        if (is_link("$ordner/vid") || is_link($datei)) {
            return ["$pfad ist ein Link", 'Die Videodatei ist keine gewöhnliche Datei.'];
        }
        if (!is_file($datei)) {
            return ["$pfad nicht gefunden", 'Die Videodatei wurde nicht gefunden.'];
        }
        $groesse = (int) filesize($datei);
        if ($groesse > self::MAX_BYTES) {
            return [sprintf('%s: %d Byte > %d', $pfad, $groesse, self::MAX_BYTES),
                sprintf('Das Video ist zu groß (%s MB, erlaubt sind %d MB).', number_format($groesse / 1048576, 1, ',', ''), self::MAX_BYTES / 1048576)];
        }
        $h = @fopen($datei, 'rb');
        $kopf = $h ? (string) fread($h, self::KOPF_BYTES) : '';
        if ($h) {
            fclose($h);
        }
        if (strlen($kopf) < self::KOPF_BYTES || substr($kopf, 4, 4) !== 'ftyp') {
            return ["$pfad ist kein MP4 (ftyp an Stelle 4–8 fehlt)", 'Die Datei ist kein MP4-Video.'];
        }
        return null;
    }
}
