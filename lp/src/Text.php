<?php
/** Text-Helfer für Vorlagen: Maskieren, **fett**, Adressen im Text als Links, Absätze, deutsches Datum, Zeitpunkte, Link-Prüfung. */

declare(strict_types=1);

namespace Endo\Lp;

final class Text
{
    public const ZONE = 'Europe/Vienna';

    /** Längste Adresse im Text in Byte; ein längeres Wort bleibt Text (hält die Regel auch bei bösen Eingaben schnell). */
    private const ADRESSE_MAX = 300;

    private const TAGE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    private const MONATE = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Maskiert und macht aus **x** ein <strong> — sonst keine Auszeichnung. Mit $hosts (die link_hosts des Profils) werden außerdem
     * Adressen im Text zu Links (nur für Fließtext: nie in Überschriften, Knöpfen, Beschriftungen und Linktexten).
     */
    public static function inline(string $s, array $hosts = []): string
    {
        $html = $hosts === [] ? self::e($s) : self::verlinkt($s, $hosts);
        return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
    }

    /** Leerzeile = neuer Absatz, Zeilenumbruch = <br>. $klasse (optional) steht an jedem Absatz; $hosts macht Adressen zu Links (siehe inline). */
    public static function absaetze(string $s, string $klasse = '', array $hosts = []): string
    {
        $p = $klasse !== '' ? '<p class="' . self::e($klasse) . '">' : '<p>';
        $aus = [];
        foreach (preg_split('/\n[ \t]*\n/', str_replace("\r\n", "\n", $s)) ?: [] as $absatz) {
            $zeilen = array_values(array_filter(array_map('trim', explode("\n", $absatz)), static fn (string $z): bool => $z !== ''));
            if ($zeilen) {
                $aus[] = $p . implode("<br>\n", array_map(static fn (string $z): string => self::inline($z, $hosts), $zeilen)) . '</p>';
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

    /** Maskierter Text, in dem Adressen auf erlaubte Hosts <a>-Links sind. Wort für Wort (an Leerraum getrennt): ohne Rückverfolgung, also linear. */
    private static function verlinkt(string $s, array $hosts): string
    {
        $teile = preg_split('/(\s+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        return $teile === false ? self::e($s) : implode('', array_map(static fn (string $w): string => self::wort($w, $hosts), $teile));
    }

    /** Ein Wort als HTML: Satzzeichen und Klammern am Rand bleiben draußen, der Rest wird ein Link, wenn ziel() ihn erlaubt. */
    private static function wort(string $wort, array $hosts): string
    {
        if (strlen($wort) > self::ADRESSE_MAX
            || !preg_match('/^([(\[{<„“"\'»«‚‘*]*)(.+?)([.,;:!?)\]}>”“"\'«»’‘…*]*)$/u', $wort, $m)
            || ($ziel = self::ziel($m[2], $hosts)) === null) {
            return self::e($wort);
        }
        return self::e($m[1]) . '<a href="' . self::e($ziel) . '">' . self::e($m[2]) . '</a>' . self::e($m[3]);
    }

    /**
     * Wohin eine Adresse im Text führt: https auf einen Host der Liste (www.… bekommt https://, die Entscheidung trifft https()) oder
     * mailto: auf eine Mailadresse, deren Domain in der Liste steht (mit oder ohne www.). Sonst null = bleibt Text.
     * Ein Sternchen in der Adresse macht sie zu Text: Fettdruck und Link dürfen sich nicht verschränken.
     */
    private static function ziel(string $adresse, array $hosts): ?string
    {
        if (str_contains($adresse, '*')) {
            return null;
        }
        $url = preg_match('/^www\./i', $adresse) ? "https://$adresse" : $adresse;
        if (self::https($url, $hosts)) {
            return $url;
        }
        if (filter_var($adresse, FILTER_VALIDATE_EMAIL) === false || !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$/', $adresse)) {
            return null;
        }
        // Die Zeichenliste oben schließt aus, was FILTER_VALIDATE_EMAIL noch erlaubt (? & ' ...): mailto:a?cc=x@… würde Kopfzeilen setzen.
        $ohneWww = static fn (string $d): string => (string) preg_replace('/^www\./', '', strtolower($d));
        $domain = $ohneWww(substr($adresse, strpos($adresse, '@') + 1));
        return in_array($domain, array_map($ohneWww, $hosts), true) ? "mailto:$adresse" : null;
    }

    private static function zeit(string $z): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $z, new \DateTimeZone(self::ZONE));
        $fehler = \DateTimeImmutable::getLastErrors();
        return ($d === false || ($fehler !== false && ($fehler['warning_count'] > 0 || $fehler['error_count'] > 0))) ? null : $d;
    }
}
