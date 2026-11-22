<?php
/** Text-Helfer für Vorlagen: Maskieren, **fett**, Adressen im Text als Links, Absätze, deutsches Datum, Zeitpunkte, Link-Prüfung. */

declare(strict_types=1);

namespace Endo\Lp;

final class Text
{
    public const ZONE = 'Europe/Vienna';

    /** Längste Adresse im Text in Byte; ein längeres Wort bleibt Text (hält die Regel auch bei bösen Eingaben schnell). */
    private const ADRESSE_MAX = 300;

    /**
     * Strenges Muster einer Mailadresse, die auch ohne link_hosts ein Link wird ($mails, 3.72.0): Buchstaben, Ziffern und . _ + - vor dem @,
     * eine Domain mit Punkt und Endung aus Buchstaben. Kein % (mailto: dekodiert es), kein ? & = : (Kopfzeilen, fremde Schemata).
     */
    private const MAIL_STRENG = '/^[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}\z/';

    private const TAGE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    private const MONATE = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Maskiert und macht aus **x** ein <strong> — sonst keine Auszeichnung. Mit $hosts (die link_hosts des Profils) werden außerdem
     * Adressen im Text zu Links (nur für Fließtext: nie in Überschriften, Knöpfen, Beschriftungen und Linktexten). Mit $mails (3.72.0) wird
     * außerdem jede Mailadresse nach dem strengen Muster ein mailto:-Link, auch auf fremde Domains (nur der Text „danach“ der Anmeldung).
     */
    public static function inline(string $s, array $hosts = [], bool $mails = false): string
    {
        $html = $hosts === [] && !$mails ? self::e($s) : self::verlinkt($s, $hosts, $mails);
        return (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
    }

    /** Leerzeile = neuer Absatz, Zeilenumbruch = <br>. $klasse (optional) steht an jedem Absatz; $hosts und $mails machen Adressen zu Links (siehe inline). */
    public static function absaetze(string $s, string $klasse = '', array $hosts = [], bool $mails = false, string $feld = ''): string
    {
        $p = ($klasse !== '' ? '<p class="' . self::e($klasse) . '"' : '<p') . $feld . '>';   // $feld: Markierung (Kontext::feld) an jedem Absatz — mehrere Absätze, derselbe Pfad
        $aus = [];
        foreach (preg_split('/\n[ \t]*\n/', str_replace("\r\n", "\n", $s)) ?: [] as $absatz) {
            $zeilen = array_values(array_filter(array_map('trim', explode("\n", $absatz)), static fn (string $z): bool => $z !== ''));
            if ($zeilen) {
                $aus[] = $p . implode("<br>\n", array_map(static fn (string $z): string => self::inline($z, $hosts, $mails), $zeilen)) . '</p>';
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
    private static function verlinkt(string $s, array $hosts, bool $mails = false): string
    {
        $teile = preg_split('/(\s+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        return $teile === false ? self::e($s) : implode('', array_map(static fn (string $w): string => self::wort($w, $hosts, $mails), $teile));
    }

    /** Ein Wort als HTML: Satzzeichen und Klammern am Rand bleiben draußen, der Rest wird ein Link, wenn ziel() ihn erlaubt. */
    private static function wort(string $wort, array $hosts, bool $mails): string
    {
        if (strlen($wort) > self::ADRESSE_MAX
            || !preg_match('/^([(\[{<„“"\'»«‚‘*]*)(.+?)([.,;:!?)\]}>”“"\'«»’‘…*]*)$/u', $wort, $m)
            || ($ziel = self::ziel($m[2], $hosts, $mails)) === null) {
            return self::e($wort);
        }
        return self::e($m[1]) . '<a href="' . self::e($ziel) . '">' . self::e($m[2]) . '</a>' . self::e($m[3]);
    }

    /**
     * Wohin eine Adresse im Text führt: https auf einen Host der Liste (www.… bekommt https://, die Entscheidung trifft https()) oder
     * mailto: auf eine Mailadresse, deren Domain in der Liste steht (mit oder ohne www.). Sonst null = bleibt Text.
     * Ein Sternchen in der Adresse macht sie zu Text: Fettdruck und Link dürfen sich nicht verschränken. Mit $mails wird jede Mailadresse nach
     * MAIL_STRENG ein mailto:-Link, gleich auf welcher Domain.
     */
    private static function ziel(string $adresse, array $hosts, bool $mails = false): ?string
    {
        if (str_contains($adresse, '*')) {
            return null;
        }
        if ($mails && preg_match(self::MAIL_STRENG, $adresse) === 1 && filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false) {
            return "mailto:$adresse";
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
