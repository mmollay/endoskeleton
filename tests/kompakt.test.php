<?php
/**
 * Kompaktere Abstände (3.54.2 Handy, 3.54.3 auch ab 48 rem): lp/lp.css verkleinert die Blockabstände — unter 48 rem auf 2 rem, darüber auf 3 rem.
 *
 * Anlass: Martin, 19.10.2026, Bildschirmfoto einer Seite auf dem Handy: „die Abstände sind zu groß“. Jeder Block hatte 4 rem Innenabstand oben und unten (8 rem Leere
 * zwischen zwei Blöcken auf gleichem Grund) und 1,5 rem Raster-Abstand. Gemessen an der MUSTER-Seite bei 390 px: 3.433 px Höhe vorher, 3.017 px nachher; bei 768 px unverändert. 3.54.3 (Martin, gleicher Tag, Bildschirmfoto am Computer: „auch hier die Abstände besser“): ab 48 rem 3 rem statt 4 rem.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

/** Der Rumpf des ersten @media-Blocks mit dieser Bedingung (Klammern gezählt) — oder null. */
$medienRumpf = static function (string $css, string $bedingung): ?string {
    $start = strpos($css, '@media (' . $bedingung . ')');
    if ($start === false) { return null; }
    $auf = strpos($css, '{', $start);
    if ($auf === false) { return null; }
    $tiefe = 0;
    for ($i = $auf, $n = strlen($css); $i < $n; $i++) {
        if ($css[$i] === '{') { $tiefe++; }
        if ($css[$i] === '}' && --$tiefe === 0) { return substr($css, $auf + 1, $i - $auf - 1); }
    }
    return null;
};

return [
    'der Block steht unter 48 rem und trägt die kleineren Abstände' => static function () use ($medienRumpf): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        $handy = $medienRumpf($css, 'max-width: 47.99rem');
        T::ok($handy !== null, 'lp.css hat einen @media-Block (max-width: 47.99rem) — knapp unter dem Breitenpunkt 48 rem der Spalten');
        $handy = (string) $handy;
        T::ok(preg_match('/section\.lp-block,\s*section\.lp-hero--bild-hinten\s*\{\s*padding:\s*var\(--space-xl\) 0;\s*\}/', $handy) === 1, 'Block-Innenabstand 2 rem (--space-xl), auch beim Kopfbereich mit Bild dahinter');
        T::ok(preg_match('/\.lp-block \.lp-block-innen\s*\{\s*gap:\s*var\(--space-md\);\s*\}/', $handy) === 1, 'Raster-Abstand 1 rem (--space-md)');
        T::ok(preg_match('/\.lp-hero \.lp-block-innen\s*\{\s*gap:\s*var\(--space-lg\);\s*\}/', $handy) === 1, 'zwischen Text und Bild des Kopfbereichs 1,5 rem (--space-lg)');
        T::ok(preg_match('/\.lp-block \.lp-block-innen > h1,\s*\.lp-block \.lp-block-innen > h2\s*\{\s*margin-bottom:\s*var\(--space-sm\);\s*\}/', $handy) === 1, 'die Überschrift behält 0,5 rem Abstand nach unten');
        T::notContains($handy, 'px', 'nur Abstandsstufen und rem, keine festen Pixel (die Voreinstellungen compact/spacious skalieren mit)');
    },
    'die Abstandsstufen, die der Block benutzt, gibt es' => static function (): void {
        $base = (string) file_get_contents(H::wurzel() . '/base.css');
        foreach (['--space-sm', '--space-md', '--space-lg', '--space-xl'] as $stufe) {
            T::ok(preg_match('/' . preg_quote($stufe, '/') . ':\s*[0-9.]+rem;/', $base) === 1, "base.css definiert {$stufe}");
        }
    },
    'ab 48 rem gelten 3 rem je Block (seit 3.54.3, vorher 4 rem), der Rest bleibt, wie er war' => static function () use ($medienRumpf): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        $handy = (string) $medienRumpf($css, 'max-width: 47.99rem');
        $ohne = str_replace($handy, '', $css);
        T::ok(preg_match('/section\.lp-block\s*\{\s*padding:\s*var\(--space-2xl\) 0;/', $ohne) === 1, 'außerhalb des Blocks gilt 3 rem Innenabstand (--space-2xl)');
        T::ok(preg_match('/section\.lp-hero--bild-hinten\s*\{[^}]*padding:\s*var\(--space-2xl\) 0;/', $ohne) === 1, 'auch der Kopfbereich mit Bild dahinter hat 3 rem');
        T::notContains($ohne, 'var(--space-3xl)', 'die 4 rem (--space-3xl) kommen im Stylesheet nicht mehr vor');
        T::ok(preg_match('/\.lp-block-innen\s*\{\s*display:\s*grid;\s*gap:\s*var\(--space-lg\);/', $ohne) === 1, 'außerhalb des Blocks gilt weiter 1,5 rem Raster-Abstand');
        T::ok(preg_match('/\.lp-hero \.lp-block-innen\s*\{\s*align-items:\s*center;\s*gap:\s*var\(--space-xl\);/', $ohne) === 1, 'außerhalb des Blocks gilt weiter 2 rem im Kopfbereich');
    },
    'die Reihenfolge stimmt: der Block steht nach den Regeln, die er überschreibt' => static function (): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        $block = strpos($css, '@media (max-width: 47.99rem)');
        T::ok($block !== false, 'der Block ist da');
        foreach (['section.lp-block {', 'section.lp-hero--bild-hinten {', '.lp-hero .lp-block-innen {', '.lp-block-innen {', '.lp-block p,'] as $regel) {
            $stelle = strpos($css, "\n" . $regel);
            T::ok($stelle !== false && $block !== false && $stelle < $block, "„{$regel}“ steht vor dem Block (gleiche Spezifität: die spätere gewinnt)");
        }
    },
];
