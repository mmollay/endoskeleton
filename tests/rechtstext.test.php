<?php
/** Format „rechtstext“ für Impressum und Datenschutz (lp/src/Rechtstext.php). */

declare(strict_types=1);

use Endo\Lp\Rechtstext;

return [
    'Überschriften, Absätze, Listen' => static function (): void {
        $html = Rechtstext::html("## Verantwortlich\n\nBeispiel GmbH\nMusterweg 1\n\n### Kontakt\n\n- Telefon\n- E-Mail\n\nEnde **fett**.", []);
        T::eq($html, "<h2>Verantwortlich</h2>\n<p>Beispiel GmbH<br>\nMusterweg 1</p>\n<h3>Kontakt</h3>\n<ul>\n<li>Telefon</li>\n<li>E-Mail</li>\n</ul>\n<p>Ende <strong>fett</strong>.</p>", 'Aufbau');
    },
    'H1 ist dem Rahmen vorbehalten' => static function (): void {
        $html = Rechtstext::html("# Groß\n\nText", []);
        T::notContains($html, '<h1', 'keine H1 aus dem Text');
        T::contains($html, '<p># Groß</p>', 'bleibt als Text stehen');
    },
    'Links nur auf erlaubte Hosts und mailto' => static function (): void {
        $html = Rechtstext::html('Siehe [Seite](https://www.example.com/x) und [Mail](mailto:office@beispiel.invalid) und [Böse](https://evil.example.org) und [JS](javascript:alert(1)).', ['www.example.com']);
        T::contains($html, '<a href="https://www.example.com/x">Seite</a>', 'erlaubter Link');
        T::contains($html, '<a href="mailto:office@beispiel.invalid">Mail</a>', 'mailto');
        T::notContains($html, 'href="https://evil.example.org', 'fremder Host wird kein Link');
        T::notContains($html, 'href="javascript', 'javascript: wird kein Link');
        T::contains($html, '[Böse](https://evil.example.org)', 'fremder Link bleibt als Text');
    },
    'alles andere wird maskiert' => static function (): void {
        $html = Rechtstext::html('<b onclick="x">Fett</b> & <script>alert(1)</script>', []);
        T::notContains($html, '<b', 'kein rohes HTML');
        T::notContains($html, '<script', 'kein Skript');
        T::contains($html, '&lt;b onclick=&quot;x&quot;&gt;', 'maskiert');
    },
    'Platzhalter erkennen' => static function (): void {
        T::ok(Rechtstext::platzhalter("## Impressum\n\n{{PLATZHALTER: Text vom Kunden}}"), 'mit {{');
        T::ok(!Rechtstext::platzhalter("## Impressum\n\nBeispiel GmbH"), 'ohne');
    },
];
