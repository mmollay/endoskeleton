<?php
/**
 * Bilder sparsam laden (3.70.0): kleinere Fassungen eines WebP-Bildes für srcset, damit ein Handy nicht das Bild in voller Breite lädt.
 * Die Varianten erzeugt der Bau mit GD aus dem Bild des Bauplans (bestand/ oder img/) nach es/groessen/; das Original bleibt die größte
 * Fassung. Kann GD kein WebP (oder ist das Bild nicht lesbar), gibt es keine Varianten und kein srcset — die Seite baut trotzdem.
 *
 * Wann eine Fassung entsteht: Das Original hat mindestens MIN_BYTES (darunter spart eine kleinere Fassung kaum etwas, und flache Grafiken
 * werden neu gerechnet oft sogar größer), höchstens MAX_PIXEL (wie die Vorschaukarte: ein großes Bild entpackt braucht viel Speicher und
 * Zeit — der Bau im Panel hat 15 s und 256 MB), die Fassung ist mindestens ein Fünftel schmaler als das Original und hat weniger Bytes.
 *
 * Gleiches Bild, gleiche Bytes auf demselben Rechner (GD und libwebp rechnen ohne Zufall); auf einem anderen Rechner mit anderer libwebp
 * können die Bytes abweichen — darum vergleicht der Test des Musterbuchs die Varianten nach Maßen und Format, nicht nach Bytes.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Bildgroessen
{
    /** Breiten der Varianten in Pixeln. */
    public const BREITEN = [480, 960];
    public const ORDNER = 'es/groessen';
    public const MIN_BYTES = 50 * 1024;
    public const MAX_PIXEL = 16000000;
    /** Eine Variante lohnt sich erst, wenn sie mindestens ein Fünftel schmaler ist als das Original. */
    private const ABSTAND = 0.8;
    private const QUALITAET = 80;
    /** @var array<string, array<int, string>> Zwischenspeicher je Quelle (Prüfsumme) — ein Lauf (Tests, Musterbuch) baut dasselbe Bild oft. */
    private static array $merk = [];

    public static function moeglich(): bool
    {
        return function_exists('imagecreatefromwebp') && function_exists('imagewebp') && function_exists('imagecopyresampled');
    }

    /** @return list<int> Breiten der Varianten, die ein Bild dieser Breite bekommen kann (keine, wenn GD kein WebP kann). */
    public static function breiten(int $breite): array
    {
        if (!self::moeglich()) {
            return [];
        }
        return array_values(array_filter(self::BREITEN, static fn (int $b): bool => $b <= $breite * self::ABSTAND));
    }

    /** Pfad der Variante im Seitenordner: es/groessen/<bestand|img>-<Name ohne .webp>-<Breite>w.webp — eigener Ordner, damit kein Bild des Bestands überschrieben wird. */
    public static function pfad(string $src, int $breite): string
    {
        $herkunft = str_starts_with($src, 'img/') ? 'img' : 'bestand';
        return sprintf('%s/%s-%s-%dw.webp', self::ORDNER, $herkunft, basename($src, '.webp'), $breite);
    }

    /**
     * Die Fassungen eines WebP-Bildes (Maße aus getimagesize) als Breite => WebP-Bytes; nur die, die es geben soll (siehe oben). Das Bild wird
     * einmal entpackt, alle Breiten werden daraus gerechnet.
     *
     * @return array<int, string>
     */
    public static function fassungen(string $quelle, array $mass): array
    {
        $breiten = self::breiten((int) $mass[0]);
        $bytes = (int) @filesize($quelle);
        if ($breiten === [] || ($mass[2] ?? null) !== IMAGETYPE_WEBP || $bytes < self::MIN_BYTES || (int) $mass[0] * (int) $mass[1] > self::MAX_PIXEL) {
            return [];
        }
        $schluessel = hash_file('sha256', $quelle);
        if (!isset(self::$merk[$schluessel])) {
            self::$merk[$schluessel] = array_filter(self::rechnen($quelle, $breiten), static fn (string $d): bool => strlen($d) < $bytes);
        }
        return self::$merk[$schluessel];
    }

    /** @return array<int, string> */
    private static function rechnen(string $quelle, array $breiten): array
    {
        $bild = @imagecreatefromwebp($quelle);
        if ($bild === false) {
            return [];   // auch ein animiertes WebP: GD liest es nicht — dann eben ohne Fassungen
        }
        [$b, $h] = [imagesx($bild), imagesy($bild)];
        $aus = [];
        foreach ($breiten as $breite) {
            $hoehe = max(1, (int) round($h * $breite / $b));
            $klein = imagecreatetruecolor($breite, $hoehe);
            // Durchsichtiges bleibt durchsichtig; imagecopyresampled mittelt die Farben nach ihrer Deckkraft (keine Farbsäume an durchsichtigen Kanten)
            imagealphablending($klein, false);
            imagesavealpha($klein, true);
            imagefill($klein, 0, 0, imagecolorallocatealpha($klein, 0, 0, 0, 127));
            if (!imagecopyresampled($klein, $bild, 0, 0, 0, 0, $breite, $hoehe, $b, $h)) {
                continue;
            }
            ob_start();
            $ok = @imagewebp($klein, null, self::QUALITAET);
            $daten = (string) ob_get_clean();
            if ($ok && $daten !== '') {
                $aus[$breite] = $daten;
            }
        }
        return $aus;
    }
}
