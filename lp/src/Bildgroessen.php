<?php
/**
 * Bilder sparsam laden (3.70.0): kleinere Fassungen eines WebP-Bildes für srcset, damit ein Handy nicht das Bild in voller Breite lädt.
 * Die Varianten erzeugt der Bau mit GD aus dem Bild des Bauplans (bestand/ oder img/) nach es/groessen/; das Original bleibt die größte
 * Fassung. Kann GD kein WebP (oder ist das Bild nicht lesbar), gibt es keine Varianten und kein srcset — die Seite baut trotzdem.
 *
 * Gleiches Bild, gleiche Bytes auf demselben Rechner (GD und libwebp rechnen ohne Zufall); auf einem anderen Rechner mit anderer libwebp
 * können die Bytes abweichen — darum vergleicht der Test des Musterbuchs die Varianten nach Maßen und Größe, nicht nach Bytes.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Bildgroessen
{
    /** Breiten der Varianten in Pixeln. */
    public const BREITEN = [480, 960];
    public const ORDNER = 'es/groessen';
    /** Eine Variante lohnt sich erst, wenn sie mindestens ein Fünftel schmaler ist als das Original. */
    private const ABSTAND = 0.8;
    private const QUALITAET = 80;
    /** @var array<string, string|false> Zwischenspeicher je Quelle (Prüfsumme) und Breite — ein Lauf (Tests, Musterbuch) baut dasselbe Bild oft. */
    private static array $merk = [];

    public static function moeglich(): bool
    {
        return function_exists('imagecreatefromwebp') && function_exists('imagewebp') && function_exists('imagescale');
    }

    /** @return list<int> Breiten der Varianten, die ein Bild dieser Breite bekommt (keine, wenn GD kein WebP kann). */
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

    /** Die Variante als WebP-Bytes — null, wenn das Bild nicht lesbar ist oder GD scheitert. */
    public static function erzeugen(string $quelle, int $breite): ?string
    {
        $schluessel = hash_file('sha256', $quelle) . ":$breite";
        if (!array_key_exists($schluessel, self::$merk)) {
            self::$merk[$schluessel] = self::rechnen($quelle, $breite) ?? false;
        }
        $daten = self::$merk[$schluessel];
        return $daten === false ? null : $daten;
    }

    private static function rechnen(string $quelle, int $breite): ?string
    {
        $bild = @imagecreatefromwebp($quelle);
        if ($bild === false) {
            return null;
        }
        $klein = @imagescale($bild, $breite, -1, IMG_BICUBIC);
        if ($klein === false) {
            return null;
        }
        imagealphablending($klein, false);
        imagesavealpha($klein, true);
        ob_start();
        $ok = @imagewebp($klein, null, self::QUALITAET);
        $daten = (string) ob_get_clean();
        return $ok && $daten !== '' ? $daten : null;
    }
}
