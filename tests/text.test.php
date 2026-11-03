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
        T::eq(Text::absaetze("**Erster**\n\nZweiter", 'lp-x'), "<p class=\"lp-x\"><strong>Erster</strong></p>\n<p class=\"lp-x\">Zweiter</p>", 'Klasse an jedem Absatz');
    },
    'Adressen im Text: www., https:// und E-Mail werden Links, wenn der Host in der Liste steht' => static function (): void {
        $h = ['www.example.com', 'beispiel.invalid'];
        $www = '<a href="https://www.example.com">www.example.com</a>';
        T::eq(Text::inline('Mehr auf www.example.com', $h), "Mehr auf $www", 'www. ohne Pfad');
        T::eq(Text::inline('Mehr auf www.example.com.', $h), "Mehr auf $www.", 'Punkt am Satzende bleibt draußen');
        T::eq(Text::inline('Auf www.example.com/seite?a=1&b=2, dort', $h), 'Auf <a href="https://www.example.com/seite?a=1&amp;b=2">www.example.com/seite?a=1&amp;b=2</a>, dort', 'Pfad und Suchteil, Komma draußen, & maskiert');
        T::eq(Text::inline('Siehe https://www.example.com/x!', $h), 'Siehe <a href="https://www.example.com/x">https://www.example.com/x</a>!', 'https:// wie geschrieben');
        T::eq(Text::inline('(www.example.com)', $h), "($www)", 'Klammern');
        T::eq(Text::inline('„www.example.com“;', $h), "„{$www}“;", 'typografische Anführungszeichen und Semikolon');
        T::eq(Text::inline('<www.example.com>', $h), "&lt;{$www}&gt;", 'spitze Klammern bleiben maskierter Text');
        T::eq(Text::inline("Zeile www.example.com\tund\nwww.example.com?", $h), "Zeile $www\tund\n$www?", 'Tabulator und Zeilenumbruch trennen Wörter');
        T::eq(Text::inline('WWW.Example.COM', $h), '<a href="https://WWW.Example.COM">WWW.Example.COM</a>', 'Groß-/Kleinschreibung: Host wird klein verglichen, Text bleibt wie geschrieben');
        T::eq(Text::inline('office@beispiel.invalid.', $h), '<a href="mailto:office@beispiel.invalid">office@beispiel.invalid</a>.', 'E-Mail auf erlaubter Domain');
        T::eq(Text::inline('office@example.com', $h), '<a href="mailto:office@example.com">office@example.com</a>', 'E-Mail: Domain ohne www. passt zum Host mit www.');
        T::eq(Text::inline('office@www.beispiel.invalid', ['beispiel.invalid']), '<a href="mailto:office@www.beispiel.invalid">office@www.beispiel.invalid</a>', 'E-Mail: Domain mit www. passt zum Host ohne www.');
        T::eq(Text::inline('www.example.com', []), 'www.example.com', 'ohne Hostliste kein Link (Verhalten wie bisher)');
        T::eq(Text::inline('www.example.com'), 'www.example.com', 'Vorgabe: kein Link');
    },
    'Adressen im Text: feindliche und fremde Adressen bleiben Text' => static function (): void {
        $h = ['www.example.com', 'beispiel.invalid'];
        foreach ([
            'www.example.com.fremd.test', 'https://example.com@fremd.test', 'https://www.example.com@fremd.test', 'www.example.com@fremd.test',
            'https://www.example.com:8080/x', 'www.example.com:8080', 'www.fremd.test/www.example.com', 'https://fremd.test/?www.example.com',
            'javascript:alert(1)', 'javascript://www.example.com/%0aalert(1)', 'data:text/html;base64,AAAA', 'ftp://www.example.com',
            'http://www.example.com', 'HTTPS://www.example.com', 'example.com', 'beispiel.invalid', 'xwww.example.com', 'ww.example.com',
            'https://www.example.com/"onmouseover="x', "https://www.example.com/'onmouseover='x", 'www.example.com<script>', 'www.example.com/<b>',
            'www.example.com%0d%0a', 'www.example.com`x', 'www.example.com\\x', 'https://', 'www.', 'www.example.com/a**b',
            'office@fremd.test', 'a@b@beispiel.invalid', '"x"@beispiel.invalid', '@beispiel.invalid', 'office@', 'office@beispiel.invalid?cc=x@fremd.test',
            'office@beispiel.invalid.fremd.test', 'office@sub.beispiel.invalid', 'a..b@beispiel.invalid', 'a?cc=x@beispiel.invalid', 'a&bcc=x@beispiel.invalid', "o'brien@beispiel.invalid", '.office@beispiel.invalid', 'office@beispiel..invalid', 'mailto:office@beispiel.invalid',
        ] as $x) {
            T::notContains(Text::inline($x, $h), '<a ', "bleibt Text: $x");
            T::notContains(Text::inline("Satz $x. Ende", $h), '<a ', "bleibt Text im Satz: $x");
        }
        T::eq(Text::inline('a@b@beispiel.invalid', $h), 'a@b@beispiel.invalid', 'zwei @: Text unverändert');
        T::eq(Text::inline('www.example.com<script>', $h), 'www.example.com&lt;script&gt;', 'Skript-Tag maskiert, kein Link');
    },
    'Adressen im Text: **fett** und Link sind richtig geschachtelt' => static function (): void {
        $h = ['www.example.com'];
        $a = '<a href="https://www.example.com">www.example.com</a>';
        T::eq(Text::inline('**www.example.com**', $h), "<strong>$a</strong>", 'ganze Adresse fett');
        T::eq(Text::inline('**Siehe www.example.com hier** und mehr', $h), "<strong>Siehe $a hier</strong> und mehr", 'Link mitten im Fettdruck');
        T::eq(Text::inline('**Siehe** www.example.com.', $h), "<strong>Siehe</strong> $a.", 'Link nach dem Fettdruck');
        T::eq(Text::inline('www.example.com/**x**', $h), 'www.example.com/<strong>x</strong>', 'Sternchen in der Adresse: Text, nie ein Link im Fettdruck verschränkt');
        $dom = new DOMDocument();
        foreach (['**www.example.com**', '**a www.example.com b** c www.example.com **d**', 'x**www.example.com', 'www.example.com**'] as $x) {
            $html = Text::inline($x, $h);
            T::ok($dom->loadHTML('<?xml encoding="UTF-8"><body><p>' . $html . '</p></body>', LIBXML_NOERROR), "lädt: $x");
            T::eq(substr_count($html, '<a '), substr_count($html, '</a>'), "Links geschlossen: $x");
            T::eq(substr_count($html, '<strong>'), substr_count($html, '</strong>'), "Fettdruck geschlossen: $x");
            T::eq((new DOMXPath($dom))->query('//a//strong|//strong//strong|//a//a')->length, 0, "nichts verschränkt: $x");
        }
    },
    'Adressen im Text: Absätze geben die Liste weiter, Zeilenumbrüche bleiben' => static function (): void {
        $h = ['www.example.com'];
        $a = '<a href="https://www.example.com">www.example.com</a>';
        T::eq(Text::absaetze("Erste www.example.com\nZweite Zeile\n\nNeu: www.example.com.", '', $h), "<p>Erste $a<br>\nZweite Zeile</p>\n<p>Neu: $a.</p>", 'Absätze');
        T::eq(Text::absaetze('Siehe www.example.com', 'lp-x', $h), "<p class=\"lp-x\">Siehe $a</p>", 'Klasse und Link');
        T::eq(Text::absaetze('Siehe www.example.com'), '<p>Siehe www.example.com</p>', 'ohne Liste wie bisher');
    },
    'Adressen im Text: Rechenzeit bleibt linear, auch bei bösen Eingaben' => static function (): void {
        $h = ['www.example.com', 'beispiel.invalid'];
        $roh = [
            'viele Links' => str_repeat('www.example.com, ', 20000),
            'ein langes Wort' => 'www.example.com/' . str_repeat('a.', 100000),
            'nur Satzzeichen' => str_repeat('!', 200000),
            'Satzzeichen vor x' => str_repeat('.', 200000) . 'x',
            'öffnende Klammern' => str_repeat('(', 200000) . 'x',
            'Wörter an der Grenze' => str_repeat('www.example.com' . str_repeat('.', 284) . 'x ', 600),
            'viele @' => str_repeat('a@', 100000) . 'beispiel.invalid',
            'Sterne' => str_repeat('*', 5000) . 'www.example.com' . str_repeat('*', 5000),
        ];
        foreach ($roh as $name => $s) {
            $t0 = hrtime(true);
            $html = Text::inline($s, $h);
            $ms = (hrtime(true) - $t0) / 1e6;
            T::ok($ms < 1500, sprintf('%s: %d Zeichen in %.0f ms (Grenze 1500)', $name, strlen($s), $ms));
            T::ok($html !== '', "$name: Ergebnis da");
        }
        T::notContains(Text::inline($roh['ein langes Wort'], $h), '<a ', 'Wörter über 300 Byte bleiben Text');
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
