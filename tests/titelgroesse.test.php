<?php
/** Der Titel des Kopfbereichs passt seine Schriftgröße der Textmenge an (3.59.0); die Dachzeile darf zweizeilig werden. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Kontext;

/** Ein Text aus genau $n Zeichen (Wörter aus Umlauten, Ende nie ein Leerzeichen). */
$text = static fn (int $n): string => mb_substr(str_repeat('Gesünder leben ', 12), 0, $n - 1) . 'x';
/** Baut den Kopfbereich mit diesem Titel und gibt das <h1> zurück. */
$h1 = static function (string $titel, string $dachzeile = ''): DOMElement {
    $s = H::seite();
    $s['bausteine'][0]['titel'] = $titel;
    if ($dachzeile !== '') { $s['bausteine'][0]['dachzeile'] = $dachzeile; }
    $o = H::ordner($s);
    T::eq(H::texte(Bauer::bauen($o)->befunde), '', 'Bau ohne Befunde');
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . (string) file_get_contents("$o/index.html"), LIBXML_NONET);
    libxml_clear_errors();
    return (new DOMXPath($d))->query('//h1')->item(0);
};
$css = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.css');

return [
    'TG01 Stufe nach Zeichenzahl: 20, 45, 80 und 140 Zeichen' => static function () use ($text): void {
        T::eq(Kontext::schriftStufe($text(20)), 0, '20 Zeichen = Normalgröße (Stufe 0 = bisher)');
        T::eq(Kontext::schriftStufe($text(45)), 1, '45 Zeichen = Stufe 1');
        T::eq(Kontext::schriftStufe($text(80)), 3, '80 Zeichen (Obergrenze des Titels) = Stufe 3');
        T::eq(Kontext::schriftStufe($text(140)), 3, '140 Zeichen bleiben bei der letzten Stufe');
        T::eq(Kontext::schriftStufe(''), 0, 'leer = Stufe 0');
        foreach (Kontext::TITEL_STUFEN as $i => $grenze) {
            T::eq(Kontext::schriftStufe($text($grenze)), $i, "genau $grenze Zeichen = noch Stufe $i");
            T::eq(Kontext::schriftStufe($text($grenze + 1)), $i + 1, ($grenze + 1) . ' Zeichen = Stufe ' . ($i + 1));
        }
    },
    'TG02 Zeichen zählen, nicht Bytes; Leerraum am Rand zählt nicht' => static function (): void {
        $g = Kontext::TITEL_STUFEN[0];
        T::eq(Kontext::schriftStufe(str_repeat('ä', $g)), 0, "$g Umlaute (doppelt so viele Bytes) = Stufe 0");
        T::eq(Kontext::schriftStufe(str_repeat('ß', $g + 1)), 1, ($g + 1) . ' ß = Stufe 1');
        T::eq(Kontext::schriftStufe(str_repeat("\u{1F600}", $g)), 0, "$g Emoji (vier Bytes je Zeichen) = Stufe 0");
        T::eq(Kontext::schriftStufe("  \t" . str_repeat('ö', $g) . " \n "), 0, 'Leerraum am Rand wird abgezogen');
        // Dieselbe Randregel wie String.prototype.trim in lp.js (sonst springt die Stufe, sobald dort getippt wird): auch geschütztes Leerzeichen,
        // ideographisches Leerzeichen, Geviert und Byte-Order-Mark am Rand zählen nicht
        foreach (["\u{00A0}" => 'geschütztes Leerzeichen', "\u{3000}" => 'ideographisches Leerzeichen', "\u{2003}" => 'Geviert', "\u{FEFF}" => 'Byte-Order-Mark'] as $rand => $name) {
            T::eq(Kontext::schriftStufe($rand . str_repeat('ö', $g) . $rand), 0, "$g Zeichen mit $name an beiden Rändern = Stufe 0");
            T::eq(Kontext::schriftStufe($rand . str_repeat('ö', $g + 1) . $rand), 1, ($g + 1) . " Zeichen mit $name an beiden Rändern = Stufe 1");
        }
        T::eq(Kontext::schriftStufe(str_repeat('ö', $g) . "\u{00A0}x"), 1, 'Leerraum in der Mitte zählt mit');
        T::eq(Kontext::schriftStufe("\u{0085}\u{200B}" . str_repeat('ö', $g)), 1, 'was JS trim() nicht abzieht (NEL, Nullbreiten-Leerzeichen), zählt auch hier mit');
        T::eq(Kontext::schriftStufe("\xC3\x28" . str_repeat('ö', $g)), 1, 'ungültiges UTF-8 bricht die Zählung nicht ab (Rückfall auf den Originaltext)');
    },
    'TG03 gebaute Seite trägt Stufe und Schwellen im HTML (ohne Skript richtig, keine Inline-Styles)' => static function () use ($h1, $text): void {
        $erwartet = [20 => 0, 45 => 1, 80 => 3];
        foreach ($erwartet as $n => $stufe) {
            $e = $h1($text($n));
            T::eq($e->getAttribute('data-lp-groesse'), (string) $stufe, "$n Zeichen: data-lp-groesse");
            T::eq($e->getAttribute('data-lp-stufen'), implode(' ', Kontext::TITEL_STUFEN), "$n Zeichen: Schwellen aus derselben Konstante");
            T::eq($e->getAttribute('class'), 'lp-hero-titel', "$n Zeichen: Klasse unverändert");
            T::eq($e->hasAttribute('style'), false, "$n Zeichen: kein Inline-Style");
        }
        $e = $h1('Fünf Gründe für Ärger über Öl');
        T::eq($e->getAttribute('data-lp-groesse'), '0', 'kurzer Titel mit Umlauten bleibt bei Stufe 0');
    },
    'TG04 CSS: Stufen 1 bis 3 mit den Tokens, Untergrenze --fs-xl, Stufe 0 wie bisher' => static function () use ($css): void {
        $c = $css();
        T::ok(preg_match('/\.lp-hero-titel\s*\{\s*font-size:\s*var\(--fs-hero\);\s*line-height:\s*1\.1;\s*\}/', $c) === 1, 'Stufe 0 unverändert (--fs-hero, 1.1)');
        foreach ([1, 2, 3] as $stufe) {
            T::ok(preg_match('/\.lp-hero-titel\[data-lp-groesse="' . $stufe . '"\]\s*\{\s*font-size:\s*max\(var\(--fs-xl\),\s*calc\(var\(--fs-hero\)\s*\*\s*0?\.\d+\)\);\s*line-height:\s*1\.\d+;\s*\}/', $c) === 1,
                "Stufe $stufe: max(var(--fs-xl), calc(var(--fs-hero) * Faktor)) und eigene Zeilenhöhe");
        }
        T::ok(preg_match('/data-lp-groesse="[^"]*"\]\s*\{[^}]*font-size:\s*[\d.]+(px|rem|em)/', $c) !== 1, 'keine festen Größen in den Stufen');
    },
    'TG05 lp.js: dieselbe Regel beim Tippen — Schwellen aus data-lp-stufen, wirkt auch bei programmatischer Änderung' => static function (): void {
        $js = (string) file_get_contents(H::wurzel() . '/lp/lp.js');
        T::contains($js, "[data-lp-stufen]", 'sucht Titel mit Schwellen im Quelltext');
        T::contains($js, 'dataset.lpStufen', 'liest die Schwellen aus dem Quelltext');
        T::contains($js, 'dataset.lpGroesse', 'setzt die Stufe');
        T::contains($js, 'MutationObserver', 'sieht Tippen und Zurücksetzen (Escape) gleichermaßen');
        T::contains($js, 'Array.from(', 'zählt Zeichen wie mb_strlen, nicht UTF-16-Einheiten');
        T::notContains($js, implode(' ', Kontext::TITEL_STUFEN), 'keine zweite Liste der Schwellen im Skript');
    },
    'TG06 Dachzeile: zwei volle Zeilen erlaubt, Grenze im Katalog, sauberer Umbruch' => static function () use ($h1, $text, $css): void {
        $max = Katalog::laden()->baustein('hero')['felder']['dachzeile']['max'];
        T::eq($max, 100, 'Grenze der Dachzeile (zwei volle Zeilen in der Textspalte bei 1440 px)');
        $e = $h1('Gesund durch den Winter', mb_strtoupper($text($max)));
        T::ok($e !== null, 'Dachzeile mit der Grenze baut ohne Befund');
        $s = H::seite();
        $s['bausteine'][0]['dachzeile'] = $text($max + 1);
        $b = Bauer::bauen(H::ordner($s))->befunde;
        T::ok(H::texte($b) !== '', 'ein Zeichen mehr wird abgelehnt');
        preg_match('/\.lp-hero-dachzeile\s*\{([^}]*)\}/', $css(), $regel);
        T::contains($regel[1] ?? '', 'text-wrap: balance;', 'Dachzeile: ausgewogener Umbruch, kein einzelnes Wort in der zweiten Zeile');
        T::ok(preg_match('/line-height:\s*1\.\d+;/', $regel[1] ?? '') === 1, 'Dachzeile: eigene Zeilenhöhe für zwei Zeilen');
    },
];
