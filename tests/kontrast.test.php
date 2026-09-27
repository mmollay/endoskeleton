<?php
/** WCAG-Kontrast wie gkContrast() in GRIDKit: Text ≥ 4,5:1, große Schrift ≥ 3:1 (lp/src/Kontrast.php). */

declare(strict_types=1);

use Endo\Lp\Kontrast;

return [
    'Verhältnisse bekannter Paare' => static function (): void {
        T::eq(round(Kontrast::verhaeltnis('#000000', '#ffffff'), 2), 21.0, 'Schwarz auf Weiß');
        T::eq(round(Kontrast::verhaeltnis('#fff', '#000'), 2), 21.0, 'Kurzform und Reihenfolge egal');
        T::eq(round(Kontrast::verhaeltnis('#777777', '#ffffff'), 2), 4.48, '#777 auf Weiß');
        T::eq(round(Kontrast::verhaeltnis('#949494', '#ffffff'), 2), 3.03, 'Hellgrau auf Weiß');
        T::eq(round(Kontrast::verhaeltnis('#3b7d3b', '#ffffff'), 2), 5.02, 'Grün auf Weiß');
    },
    'Hex normalisieren' => static function (): void {
        T::eq(Kontrast::hex('#ABC'), '#aabbcc', 'Kurzform');
        T::eq(Kontrast::hex(' #A1b2C3 '), '#a1b2c3', 'Leerraum und Großbuchstaben');
        T::eq(Kontrast::hex('rgb(0,0,0)'), null, 'rgb() ist kein Hex');
        T::eq(Kontrast::hex('#abcd'), null, 'vierstellig ist kein Hex');
    },
    'Farbpaare eines Profils prüfen' => static function (): void {
        $tokens = ['--text' => '#777777', '--bg' => '#ffffff', '--heading' => '#777777', '--primary' => '#3b7d3b'];
        $b = Kontrast::pruefe($tokens, [['--text', '--bg', false], ['--heading', '--bg', true], ['--primary', '--bg', false]], 'bausteine.hero', 'Abschnitt „Kopfbereich“');
        T::eq(count($b), 1, 'nur der Fließtext fällt durch (große Schrift reicht 3:1)');
        T::eq($b[0]->art ?? '', 'sperrend', 'sperrend');
        T::contains($b[0]->technik ?? '', '--text auf --bg', 'Technik nennt das Paar');
        T::contains($b[0]->kunde ?? '', '4,48:1', 'Kundentext nennt das Verhältnis mit Komma');
        T::contains($b[0]->kunde ?? '', '4,5:1', 'Kundentext nennt die Grenze');
    },
    'fehlendes Token ist sperrend' => static function (): void {
        $b = Kontrast::pruefe(['--bg' => '#ffffff'], [['--text', '--bg', false]], 'p', 'w');
        T::eq(count($b), 1, 'ein Befund');
        T::contains($b[0]->technik ?? '', '--text', 'nennt das fehlende Token');
    },
];
