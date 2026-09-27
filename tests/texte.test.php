<?php
/** Texte, die Kunden sehen: echte Umlaute, keine Platzhalter (allgemeiner Teil; Heilversprechen prüft das Kundenprofil). */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;

return [
    'Katalogtexte ohne ASCII-Umlaute und Platzhalter' => static function (): void {
        $texte = [];
        $k = json_decode((string) file_get_contents(H::wurzel() . '/lp/bausteine.json'), true);
        array_walk_recursive($k, static function ($w, $s) use (&$texte): void {
            if (is_string($w) && !in_array($s, ['typ', 'seit', 'geaendert', 'version', 'muster', 'kennung', 'datei', 'ziel', 'quelle', 'liste', 'anker', 'anbieter', 'variante', 'art'], true) && !str_starts_with($w, '--') && !str_starts_with($w, 'lp-')) {
                $texte[] = $w;
            }
        });
        T::ok(count($texte) > 80, 'Texte gefunden: ' . count($texte));
        foreach ($texte as $t) {
            T::ok(!preg_match(Pruefer::ASCII, $t, $m), "ASCII-Umlaut „" . ($m[0] ?? '') . "\" in „{$t}\"");
            T::ok(!preg_match('/lorem ipsum|\{\{|\bTODO\b/i', $t), "Platzhalter in „{$t}\"");
        }
    },
    'sichtbarer Text der gebauten Seite ohne ASCII-Umlaute' => static function (): void {
        $k = Katalog::laden();
        $o = H::ordner(H::seite(array_map(static fn ($t) => $k->baustein($t)['beispiel'], ['termin', 'themen', 'video', 'erfahrungen', 'faq', 'abschluss'])));
        Bauer::bauen($o);
        foreach (['index.html', 'impressum.html', 'datenschutz.html'] as $f) {
            $text = html_entity_decode(strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#s', '', (string) file_get_contents("$o/$f"))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            T::ok(!preg_match(Pruefer::ASCII, $text, $m), "$f: ASCII-Umlaut „" . ($m[0] ?? '') . '"');
            T::ok((bool) preg_match('/[äöüßÄÖÜ]/u', $text), "$f: echte Umlaute");
        }
    },
];
