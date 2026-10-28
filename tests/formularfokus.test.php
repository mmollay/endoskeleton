<?php
/** Der Sprung zum Formular setzt den Cursor ins erste Feld (3.59.0) — die Regeln im Skript; das Verhalten prüft ci/browser.js. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

$js = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.js');

return [
    'FF01 lp.js: Fokus erst nach dem Klick auf einen Anker, ohne Scrollen, nie in den Honigtopf, nie in gesperrte Felder' => static function () use ($js): void {
        $s = $js();
        T::contains($s, "addEventListener('click'", 'wartet auf den Klick, nicht auf das Laden');
        T::contains($s, 'a[href^="#"]', 'nur Anker-Links');
        T::contains($s, 'ereignis.defaultPrevented', 'ein abgefangener Klick (Bearbeiten-Modus des Panels) tut nichts');
        T::contains($s, 'ereignis.button', 'nur die Haupttaste ...');
        T::contains($s, 'ereignis.metaKey', '... ohne Umschalttasten (neuer Tab bleibt neuer Tab)');
        T::contains($s, 'preventScroll: true', 'der Fokus scrollt nicht selbst (sonst bricht er das sanfte Scrollen ab)');
        T::contains($s, "closest('.form-hp')", 'der Honigtopf ist nie das erste Feld');
        T::contains($s, "matches(':disabled')", 'gesperrte Felder (auch im gesperrten Fieldset) zählen nicht');
        T::contains($s, 'getClientRects().length', 'ausgeblendete Felder (type="hidden", hidden, display: none) zählen nicht');
        T::contains($s, 'scrollBehavior', 'sanftes Scrollen wird abgewartet, bei „weniger Bewegung“ nicht');
        T::contains($s, "ziel.matches('.lp-anmeldung')", 'Formularziel ist nur der Anmeldeabschnitt selbst (nicht <main id="inhalt">, den der Skip-Link anspringt)');
        T::notContains($s, "ziel.closest('form", 'kein Aufstieg aus dem Formular heraus');
    },
    'FF02 lp.js: der automatische Fokus ist keine Statistik-Meldung „formular“' => static function () use ($js): void {
        $s = $js();
        T::contains($s, 'selbstFokus = true', 'die Marke wird vor dem Fokus gesetzt ...');
        T::contains($s, 'selbstFokus = false', '... und danach wieder gelöscht');
        T::contains($s, '!selbstFokus', 'die Statistik wertet sie aus');
        T::ok(preg_match("/\\['focusin', 'input', 'click'\\]/", $s) === 1, 'formular zählt beim ersten Fokus durch den Besucher, beim Tippen und beim Anklicken (auch im schon aktiven Feld)');
    },
    'FF03 kein autofocus im Quelltext der Bausteine: der Cursor kommt nie vom Laden der Seite' => static function (): void {
        foreach (glob(H::wurzel() . '/lp/bausteine/*.php') ?: [] as $datei) {
            T::notContains((string) file_get_contents($datei), 'autofocus', basename($datei) . ': kein autofocus');
        }
    },
];
