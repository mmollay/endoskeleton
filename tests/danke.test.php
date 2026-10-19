<?php
/**
 * Die Danke-Meldung der Anmeldung (3.54.4): Haken, Dank und Zusatzsatz getrennt, „E-Mail“ ohne Umbruch.
 *
 * Anlass: Martin, 19.10.2026, Bildschirmfoto der Meldung nach der Anmeldung: „auch hier bitte, besser formatieren“. Der Text blieb, die Form ändert sich: Der Kasten ist weiter ein
 * <p role="status"> (lp.js zeigt und fokussiert ihn), die zwei Sätze stehen in eigenen Spannen, und „E-Mail“ trägt eine Klasse, die den Umbruch am Bindestrich verhindert.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

/** Der Rumpf einer CSS-Regel mit genau diesem Selektor (erste Fundstelle) — oder null. */
$regel = static function (string $css, string $selektor): ?string {
    $start = strpos($css, "\n" . $selektor . ' {');
    if ($start === false) { return null; }
    $auf = strpos($css, '{', $start);
    $zu = strpos($css, '}', (int) $auf);
    return $auf === false || $zu === false ? null : substr($css, $auf + 1, $zu - $auf - 1);
};

return [
    'die Meldung ist ein <p> mit zwei Spannen, E-Mail bleibt zusammen' => static function (): void {
        $quelle = (string) file_get_contents(H::wurzel() . '/lp/bausteine/anmeldung.php');
        T::ok(preg_match('~<p class="lp-anmeldung-danke" role="status" tabindex="-1" hidden><span class="lp-anmeldung-danke-text">.*?</span> <span class="lp-anmeldung-danke-nachsatz">.*?</span></p>~s', $quelle) === 1,
            'der Baustein: <p class="lp-anmeldung-danke" role="status" …> mit den Spannen -text und -nachsatz (Leerzeichen dazwischen, damit der Text derselbe bleibt)');
        T::contains($quelle, "str_replace('E-Mail', '<span class=\"lp-ohne-umbruch\">E-Mail</span>', \$k->e(\$t))", '„E-Mail“ wird erst NACH dem Maskieren umhüllt — der Text selbst bleibt maskiert');
    },
    'die Regeln: Haken im Kreis, Dank größer, Zusatz klein, nowrap' => static function () use ($regel): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        $kasten = $regel($css, '.lp-formular .lp-anmeldung-danke');
        $raster = $regel($css, '.lp-formular .lp-anmeldung-danke:not([hidden])');
        T::ok($kasten !== null && str_contains($kasten, 'background: var(--lp-akzent)') && !str_contains($kasten, 'display') && !str_contains($kasten, 'px'),
            'Kasten: Akzentfläche, KEIN display (sonst höbe es „hidden“ auf), keine festen Pixel');
        T::ok($raster !== null && str_contains($raster, 'display: grid') && str_contains($raster, 'grid-template-columns: auto 1fr'),
            'Raster nur für die sichtbare Meldung (:not([hidden]))');
        $haken = $regel($css, '.lp-formular .lp-anmeldung-danke::before');
        T::ok($haken !== null && str_contains($haken, 'content: "✓" / "";') && str_contains($haken, 'background: var(--primary)') && str_contains($haken, 'color: var(--text-on-primary)') && str_contains($haken, 'border-radius: 50%'),
            'Haken: rund, Hauptfarbe, für Vorleseprogramme ohne Text (content: "✓" / "")');
        T::ok(str_contains((string) $regel($css, '.lp-anmeldung-danke-nachsatz'), 'font-weight: 400') && str_contains((string) $regel($css, '.lp-anmeldung-danke-text'), 'font-size: var(--fs-md)'),
            'Dank in --fs-md (fett aus der Grundregel), Zusatzsatz klein und normal');
        T::ok(str_contains((string) $regel($css, '.lp-ohne-umbruch'), 'white-space: nowrap'), '.lp-ohne-umbruch: white-space: nowrap');
        T::ok(strpos($css, "\n.lp-formular .lp-anmeldung-danke {", (int) strpos($css, "\n.lp-formular .lp-anmeldung-danke,")) > 0, 'die neue Regel steht NACH der gemeinsamen Regel von Danke, Fehler und Voll (gleiche Spezifität: die spätere gewinnt)');
    },
    'versteckt bleibt versteckt: kein display auf der Meldung ohne :not([hidden])' => static function (): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        T::ok(preg_match('~\.lp-seite \[hidden\]\s*\{\s*display:\s*none;~', $css) === 1, '.lp-seite [hidden] { display: none } gilt');
        // Jede Regel, die display auf .lp-anmeldung-danke setzt, muss :not([hidden]) tragen — gleiche Spezifität wie .lp-seite [hidden], und die spätere gewinnt.
        preg_match_all('~\n(\.lp-formular \.lp-anmeldung-danke[^{,]*)\{([^}]*)\}~', $css, $m, PREG_SET_ORDER);
        $schlecht = array_filter($m, static fn (array $r): bool => str_contains($r[2], 'display:') && !str_contains($r[1], ':not([hidden])') && !str_contains($r[1], '::'));
        T::ok($m !== [] && $schlecht === [], 'keine Regel setzt display auf die Danke-Meldung, ohne :not([hidden]) zu nennen');
    },
];
