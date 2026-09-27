<?php
/** Maskierung, Absätze, deutsches Datum, Zeitpunkte und Link-Prüfung (lp/src/Text.php). */

declare(strict_types=1);

use Endo\Lp\Text;

return [
    'maskiert HTML und Anführungszeichen' => static function (): void {
        T::eq(Text::e('<script>alert(1)</script>'), '&lt;script&gt;alert(1)&lt;/script&gt;', 'Skript-Tag');
        T::eq(Text::e('" onmouseover="x'), '&quot; onmouseover=&quot;x', 'doppelte Anführungszeichen');
        T::eq(Text::e("a'b"), 'a&apos;b', 'einfache Anführungszeichen');
        T::eq(Text::e('Groß & Klein'), 'Groß &amp; Klein', 'Umlaute bleiben, & wird maskiert');
    },
    'inline: nur **fett**, paarweise' => static function (): void {
        T::eq(Text::inline('Das ist **wichtig** <b>x</b>'), 'Das ist <strong>wichtig</strong> &lt;b&gt;x&lt;/b&gt;', 'fett + Maskierung');
        T::eq(Text::inline('ein ** allein'), 'ein ** allein', 'einzelnes ** bleibt Text');
        T::eq(Text::inline('**<i>x</i>**'), '<strong>&lt;i&gt;x&lt;/i&gt;</strong>', 'Inhalt im Fettdruck maskiert');
    },
    'Absätze aus Leerzeilen, Zeilenumbruch als br' => static function (): void {
        T::eq(Text::absaetze("Erster\nZweite Zeile\n\nNeuer Absatz"), "<p>Erster<br>\nZweite Zeile</p>\n<p>Neuer Absatz</p>", 'zwei Absätze');
        T::eq(Text::absaetze("  \n\n  "), '', 'nur Leerraum = nichts');
    },
    'deutsches Datum' => static function (): void {
        T::eq(Text::datum('2026-10-06T19:00', 'de-AT'), 'Dienstag, 6. Oktober 2026, 19:00 Uhr', 'Vortragstermin');
        T::eq(Text::datum('2027-01-01T09:05', 'de-AT'), 'Freitag, 1. Jänner 2027, 09:05 Uhr', 'Jänner in Österreich');
        T::eq(Text::datum('2027-01-01T09:05', 'de-DE'), 'Freitag, 1. Januar 2027, 09:05 Uhr', 'Januar in Deutschland');
    },
    'ISO-Zeitpunkt mit Wiener Zeitzone' => static function (): void {
        T::eq(Text::iso('2026-10-06T19:00'), '2026-10-06T19:00:00+02:00', 'Sommerzeit');
        T::eq(Text::iso('2026-12-01T19:00'), '2026-12-01T19:00:00+01:00', 'Winterzeit');
        T::eq(Text::iso('2026-10-24T23:30', 90), '2026-10-25T01:00:00+02:00', 'plus 90 Minuten, noch Sommerzeit');
        T::eq(Text::iso('2026-10-25T01:30', 120), '2026-10-25T02:30:00+01:00', 'über die Umstellung hinweg');
    },
    'ungültige Zeitpunkte' => static function (): void {
        T::ok(Text::zeitpunktGueltig('2026-10-06T19:00'), 'gültig');
        foreach (['2026-02-30T10:00', '2026-10-06 19:00', '2026-10-06T24:00', '2026-10-06T19:00:00', '', '06.10.2026'] as $x) {
            T::ok(!Text::zeitpunktGueltig($x), "ungültig: $x");
        }
    },
    'https-Links nur auf erlaubte Hosts' => static function (): void {
        $h = ['www.example.com', 'example.com'];
        T::ok(Text::https('https://www.example.com/pfad?x=1', $h), 'erlaubt');
        T::ok(Text::https('https://EXAMPLE.com', $h), 'Groß-/Kleinschreibung egal');
        foreach (['http://www.example.com', 'javascript:alert(1)', 'https://evil.example.org', 'https://www.example.com@evil.example.org/',
                  '//www.example.com', 'https://sub.example.com', 'https://www.example.com:8443/', " https://www.example.com", 'https://www.example.com/"onmouseover'] as $u) {
            T::ok(!Text::https($u, $h), "abgelehnt: $u");
        }
    },
];
