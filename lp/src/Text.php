<?php
/** Text-Helfer für Vorlagen: Maskieren, **fett**, Absätze, deutsches Datum, Zeitpunkte, Link-Prüfung. */

declare(strict_types=1);

namespace Endo\Lp;

final class Text
{
    public const ZONE = 'Europe/Vienna';

    private const TAGE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    private const MONATE = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** Maskiert und macht aus **x** ein <strong> — sonst keine Auszeichnung. */
    public static function inline(string $s): string
    {
        return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', self::e($s));
    }

    /** Leerzeile = neuer Absatz, Zeilenumbruch = <br>. $klasse (optional) steht an jedem Absatz. */
    public static function absaetze(string $s, string $klasse = ''): string
    {
        $p = $klasse !== '' ? '<p class="' . self::e($klasse) . '">' : '<p>';
        $aus = [];
        foreach (preg_split('/\n[ \t]*\n/', str_replace("\r\n", "\n", $s)) ?: [] as $absatz) {
            $zeilen = array_values(array_filter(array_map('trim', explode("\n", $absatz)), static fn (string $z): bool => $z !== ''));
            if ($zeilen) {
                $aus[] = $p . implode("<br>\n", array_map([self::class, 'inline'], $zeilen)) . '</p>';
            }
        }
        return implode("\n", $aus);
    }

    public static function zeitpunktGueltig(string $z): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $z) && self::zeit($z)?->format('Y-m-d\TH:i') === $z;
    }

    /** „Dienstag, 6. Oktober 2026, 19:00 Uhr“ — in Österreich „Jänner“. */
    public static function datum(string $z, string $sprache): string
    {
        $d = self::zeit($z) ?? throw new \InvalidArgumentException("ungültiger Zeitpunkt: $z");
        $monat = (int) $d->format('n');
        $name = ($monat === 1 && $sprache === 'de-AT') ? 'Jänner' : self::MONATE[$monat - 1];
        return sprintf('%s, %d. %s %s, %s Uhr', self::TAGE[(int) $d->format('N') - 1], (int) $d->format('j'), $name, $d->format('Y'), $d->format('H:i'));
    }

    /** ISO 8601 mit Wiener Versatz, optional um Minuten verschoben (echte Zeit, über Umstellungen hinweg). */
    public static function iso(string $z, int $plusMinuten = 0): string
    {
        $d = self::zeit($z) ?? throw new \InvalidArgumentException("ungültiger Zeitpunkt: $z");
        return $d->setTimestamp($d->getTimestamp() + $plusMinuten * 60)->format('Y-m-d\TH:i:sP');
    }

    /** Nur https://, kein Benutzer, kein Port, Host genau aus der Liste, keine Sonderzeichen. */
    public static function https(string $url, array $hosts): bool
    {
        if (!str_starts_with($url, 'https://') || preg_match('/["\'<>\s\\\\`]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $t = parse_url($url);
        if (!is_array($t) || isset($t['user']) || isset($t['pass']) || isset($t['port']) || !isset($t['host'])) {
            return false;
        }
        return in_array(strtolower($t['host']), array_map('strtolower', $hosts), true);
    }

    private static function zeit(string $z): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $z, new \DateTimeZone(self::ZONE));
        $fehler = \DateTimeImmutable::getLastErrors();
        return ($d === false || ($fehler !== false && ($fehler['warning_count'] > 0 || $fehler['error_count'] > 0))) ? null : $d;
    }
}
