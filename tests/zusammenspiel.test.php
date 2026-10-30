<?php
/**
 * Marke 3.58.0 (Zusammenführung von 3.54.5 „geschlossen“ und 3.55.0–3.57.0 „zwei Häkchen + Statistik“): Keine Seite darf der anderen
 * etwas wegnehmen. Die Zweige liefen von 3.54.4 aus auseinander und änderten dieselben Dateien (lp.js, anmeldung.php, bausteine.json);
 * jeder Zweig hat seine eigenen Tests, aber nur dieser prüft, dass BEIDE Stände im selben Bau stehen. Das Verhalten im Browser
 * (geschlossen + zweites Häkchen + Beacon) prüft ci/browser.js, Block „Zusammenspiel“.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;

/** Baut eine Seite und gibt [html, xpath, ordner] zurück. */
$bau = static function (array $seite): array {
    $o = H::ordner($seite);
    $e = Bauer::bauen($o);
    T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$o/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d), $o];
};

return [
    'Zusammenspiel: die Anmeldung trägt „geschlossen“ UND das zweite Häkchen — nur mit "newsletter": true' => static function () use ($bau): void {
        $s = H::seite();
        $s['bausteine'][1]['newsletter'] = true;
        [$h, $x] = $bau($s);
        $form = '//form[contains(@class,"lp-formular")]';
        // 3.54.5: die Meldung „geschlossen“ liegt versteckt bereit, vor den Feldern; der Fehlertext hat den Zusatz
        $zu = $x->query("$form/p[contains(@class,\"lp-anmeldung-geschlossen\")][@hidden][@role=\"status\"][@tabindex=\"-1\"]");
        T::eq($zu->length, 1, 'geschlossen: genau eine versteckte Meldung im Formular');
        T::eq($x->query("$form/p[contains(@class,\"lp-anmeldung-geschlossen\")]/following-sibling::fieldset")->length, 1, 'geschlossen: Meldung steht vor den Feldern');
        T::contains($h, 'Klappt es wieder nicht, melde dich bitte direkt bei uns.', 'fehler: Zusatz „direkt melden“ (3.54.5)');
        // 3.55.0: das freiwillige Häkchen steht in denselben Feldern (die lp.js bei „geschlossen“ ausblendet) und ist nie Pflicht
        $nl = $x->query("$form/fieldset//input[@name=\"newsletter\"]");
        T::eq($nl->length, 1, 'zweites Häkchen: im ausgeblendeten Feldersatz');
        T::ok($nl->length === 1 && !$nl->item(0)->hasAttribute('required') && !$nl->item(0)->hasAttribute('checked'), 'zweites Häkchen: weder Pflicht noch vorangehakt');
        T::eq($x->query("$form/fieldset//input[@type=\"hidden\"][@name=\"newsletter_kurz\"]")->length, 1, 'zweites Häkchen: eigene Kurzsumme');
        T::contains($h, '(freiwillig)', 'zweites Häkchen: als „freiwillig“ beschriftet');
        // ohne "newsletter": true bleibt es beim einen Häkchen — „geschlossen“ ist trotzdem da
        [$h, $x] = $bau(H::seite());
        T::eq($x->query('//input[@name="newsletter"]')->length, 0, 'ohne "newsletter": true — kein zweites Häkchen');
        T::eq($x->query('//p[contains(@class,"lp-anmeldung-geschlossen")]')->length, 1, 'ohne "newsletter": true — „geschlossen“ liegt trotzdem bereit');
    },

    'Zusammenspiel: lp.js hat „geschlossen“ und den Beacon — Rückkehrseite wird nicht gezählt, absenden nur nach der Prüfung' => static function () use ($bau): void {
        [, , $o] = $bau(H::seite());
        $js = (string) file_get_contents("$o/es/lp.js");
        T::eq($js, (string) file_get_contents(H::wurzel() . '/lp/lp.js'), 'der gebaute Seiten-Ordner trägt lp.js unverändert');
        // 3.54.5
        T::contains($js, "searchParams.get('grund') === 'geschlossen'", 'geschlossen: nur der eine Wert grund=geschlossen wirkt');
        T::contains($js, "form.querySelector(geschlossen ? '.lp-anmeldung-geschlossen' : meldungen[zustand])", 'geschlossen: die eigene Meldung ersetzt den Fehlertext');
        T::contains($js, "if (zustand !== 'fehler' || geschlossen) { form.querySelector('fieldset').hidden = true; }", 'geschlossen: die Felder (mit dem zweiten Häkchen) verschwinden');
        // 3.56.0 / 3.57.0
        foreach (['aufruf', 'formular', 'absenden', 'verlassen', 'da'] as $art) {
            T::contains($js, "melden('$art'", "Beacon: Art „{$art}“ wird gesendet");
        }
        T::contains($js, "if (!rueckmeldung) { document.addEventListener('visibilitychange', sichtbar); sichtbar(); }", 'Beacon: die Rückkehrseite einer Rückmeldung (auch „geschlossen“) zählt keinen aufruf');
        T::contains($js, "if (seit === null) { return; }", 'Beacon: ohne gezählten aufruf auch kein verlassen');
        T::contains($js, "if (!ereignis.defaultPrevented) { melden('absenden'); }", 'Beacon: absenden zählt nur, wenn die Prüfung das Absenden durchgelassen hat');
        T::eq(substr_count($js, "melden('absenden'"), 1, 'Beacon: absenden an genau einer Stelle (kein Absenden ohne Absenden)');
        // Reihenfolge: „geschlossen“ und die Rückmeldung sind festgelegt, bevor der Beacon sie liest
        T::ok(strpos($js, 'var geschlossen =') !== false && strpos($js, 'var geschlossen =') < strpos($js, 'if (!rueckmeldung) { document.addEventListener'), 'Reihenfolge: die Rückmeldung steht vor dem Beacon-Block');
    },

    'Zusammenspiel: Baukasten-Katalog und CHANGELOG kennen beide Stände' => static function (): void {
        $katalog = json_decode((string) file_get_contents(H::wurzel() . '/lp/bausteine.json'), true);
        $klassen = $katalog['bausteine']['anmeldung']['klassen'] ?? [];
        T::eq($klassen['lp-anmeldung-geschlossen'] ?? null, '3.54.5', 'Klasse lp-anmeldung-geschlossen seit 3.54.5');
        T::eq($klassen['lp-einwilligung--freiwillig'] ?? null, '3.55.0', 'Klasse lp-einwilligung--freiwillig seit 3.55.0');
        T::eq($klassen['lp-freiwillig'] ?? null, '3.55.0', 'Klasse lp-freiwillig seit 3.55.0');
        T::ok(isset($katalog['bausteine']['anmeldung']['felder']['newsletter']), 'Feld newsletter im Baustein anmeldung');
        $log = (string) file_get_contents(H::wurzel() . '/CHANGELOG.md');
        preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', $log, $m);
        $oben = array_slice($m[1], (int) array_search('3.58.0', $m[1], true), 6);
        T::eq($oben, ['3.58.0', '3.57.0', '3.56.0', '3.55.0', '3.54.5', '3.54.4'], 'CHANGELOG: die Einträge beider Zweige stehen in Versionsfolge unter 3.58.0');
    },
];
