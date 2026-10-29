<?php
/**
 * Baustein „Newsletter-Anmeldung“ (modules/newsletter.js + newsletter.css): Verträge nach außen (Panel panel.ssi.at/lp/anmelden),
 * keine Inline-Stile, Farben nur aus Tokens, Barrierefreiheit im Quelltext, nichts Kundenspezifisches.
 * Das Verhalten im Browser prüft ci/browser-newsletter.js.
 */

declare(strict_types=1);

return (static function (): array {
    $root = dirname(__DIR__);
    $lese = static fn (string $f): string => (string) @file_get_contents("$root/$f");
    $js   = $lese('modules/newsletter.js');
    $css  = $lese('modules/newsletter.css');
    $doku = $lese('pages/_newsletter.html');
    $alle = $js . "\n" . $css . "\n" . $doku;

    return [
        'Baustein, Stile und Referenz sind da' => static function () use ($js, $css, $doku): void {
            T::ok(strlen($js) > 500, 'modules/newsletter.js fehlt oder ist leer');
            T::ok(strlen($css) > 200, 'modules/newsletter.css fehlt oder ist leer');
            T::ok(strlen($doku) > 200, 'pages/_newsletter.html (Referenz für die Werkstatt) fehlt oder ist leer');
        },
        'keine Inline-Stile — Design steht im Stylesheet' => static function () use ($js): void {
            T::ok(!preg_match('/\bstyle\s*=|\.style\b|cssText|setAttribute\(\s*[\'"]style/i', $js), 'Inline-Stil im Baustein');
        },
        'Absenden per normalem Formular-POST an das Panel — kein fetch, keine eigene API-Adresse' => static function () use ($js): void {
            T::contains($js, 'https://panel.ssi.at/lp/anmelden', 'Formularziel');
            T::contains($js, 'method="post"', 'POST');
            T::ok(!preg_match('/\bfetch\s*\(|XMLHttpRequest|sendBeacon|newsletterApiUrl|newsletterApiKey|Authorization/', $js), 'fetch/Ajax/API-Schlüssel im Baustein');
        },
        'alle Felder des Panels, Honigtopf und Kennung der Liste' => static function () use ($js): void {
            foreach (['vorname', 'nachname', 'email'] as $feld) { T::contains($js, "feld('$feld'", "Feld $feld"); }
            foreach (['einwilligung', 'website', 'liste', 'einwilligung_kurz'] as $feld) { T::contains($js, 'name="' . $feld . '"', "Feld $feld"); }
            T::contains($js, 'value="ja"', 'Einwilligung hat den Wert ja');
            T::contains($js, 'class="form-hp"', 'Honigtopf (Klasse aus base.css)');
            T::contains($js, 'id="anmeldung"', 'Sprungmarke, auf die das Panel zurückleitet');
        },
        'Kurzsumme: erste 12 Hex-Zeichen von SHA-256 des Einwilligungstexts (wie das Panel)' => static function () use ($js): void {
            T::contains($js, "'SHA-256'", 'SHA-256');
            T::contains($js, 'slice(0, 12)', '12 Zeichen');
        },
        'Rückmeldung ok und fehler, beide mit dem festen Zusatzsatz; keine eigene Meldung „schon eingetragen“' => static function () use ($js): void {
            T::contains($js, 'Falls Sie schon angemeldet waren, bekommen Sie keine neue E-Mail.', 'Zusatzsatz');
            T::contains($js, 'anmeldung', 'liest ?anmeldung=');
            T::contains($js, 'fehler', 'Meldung fehler');
            T::contains($js, "'aria-live', 'polite'", 'Meldung ist eine Live-Region');
            T::contains($js, "'role', 'status'", 'Meldung hat die Rolle status');
            T::ok(!preg_match('/schon eingetragen|bereits (eingetragen|angemeldet)\./i', $js), 'verrät, ob eine Adresse schon auf der Liste steht');
        },
        'Barrierefreiheit: Labels, Fehlertexte am Feld, Autocomplete' => static function () use ($js): void {
            T::ok(substr_count($js, '<label for="') >= 3, 'Label zu jedem Feld (Eingaben, Einwilligung, Honigtopf)');
            foreach (['given-name', 'family-name', "'email', 'email', 80", 'aria-invalid', 'aria-describedby', 'tabindex="-1"', 'novalidate'] as $x) {
                T::contains($js, $x, $x);
            }
        },
        'Farben nur aus Tokens, keine festen Farbwerte im Stylesheet' => static function () use ($css): void {
            T::ok(!preg_match('/#[0-9a-f]{3,8}\b|\b(rgb|rgba|hsl|hsla)\s*\(/i', $css), 'fester Farbwert in newsletter.css');
            T::contains($css, 'var(--', 'benutzt Tokens');
        },
        'jede vom Baustein gesetzte Klasse newsletter-… hat im Stylesheet eine Regel' => static function () use ($js, $css): void {
            preg_match_all('/newsletter-[a-z0-9-]+/', $js, $m);
            $klassen = array_unique(array_filter($m[0], static fn (string $k): bool => !str_ends_with($k, '-')));
            T::ok(count($klassen) >= 3, 'Klassen im Baustein: ' . implode(', ', $klassen));
            foreach ($klassen as $k) {
                if (preg_match('/^newsletter-(titel|website|einwilligung-fehler)$/', $k)) { continue; }   // nur IDs
                T::contains($css, '.' . $k, "Regel für .$k");
            }
            foreach (['ok', 'fehler'] as $art) { T::contains($css, ".newsletter-meldung--$art", "Regel für die Meldung $art"); }
        },
        'echte Umlaute, kein ASCII-Ersatz' => static function () use ($alle): void {
            T::ok(!preg_match('/\b(fuer|moechte|koennen|ueber|waehlen|pruefen|zurueck|loeschen|aendern|oeffnen|Gruesse)\b/i', $alle), 'ASCII-Ersatz für Umlaute');
            T::contains($alle, 'möchte', 'Umlaut ö');
            T::contains($alle, 'Datenschutzerklärung', 'Umlaut ä');
        },
        'nichts Kundenspezifisches, nur das Panel als fremder Host' => static function () use ($alle): void {
            T::ok(!preg_match('/survival|mollay/i', $alle), 'Kundenname im Baustein');
            preg_match_all('#https?://([a-z0-9.-]+)#i', $alle, $m);
            foreach (array_unique(array_map('strtolower', $m[1])) as $h) { T::ok(in_array($h, ['panel.ssi.at'], true), "fremder Host $h"); }
            preg_match_all('/[a-z0-9._%+-]+@([a-z0-9-]+(\.[a-z0-9-]+)+)/i', $alle, $e);
            foreach (array_unique(array_map('strtolower', $e[1])) as $d) { T::ok(preg_match('/(^|\.)(example\.(com|org|net)|[a-z0-9-]+\.(invalid|test))$/', $d) === 1, "E-Mail-Domain $d"); }
        },
        'Referenz für die Werkstatt: Einbau, Attribute, Gruppe und Absender stehen im Panel' => static function () use ($doku): void {
            foreach (['modules', 'SITE_CONFIG', 'newsletter', 'liste', 'einwilligung', 'Panel', 'Gruppe', 'Absender', 'id="anmeldung"', 'Datenschutz', 'form-action'] as $x) {
                T::contains($doku, $x, "Referenz nennt $x");
            }
        },
    ];
})();
