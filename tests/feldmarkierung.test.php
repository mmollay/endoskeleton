<?php
/**
 * Feld-Markierung für „Direkt im Bild bearbeiten“ (3.74.0): Im Bau mit `--felder` trägt jedes Textstück, das aus einem Feld des Bauplans kommt,
 * data-lp-feld="bausteine.<Stelle>.<pfad>" (Punktelisten je Eintrag). Ohne `--felder` ist jede Seite Byte für Byte wie vorher.
 *
 *   FM01  ohne --felder: keine Markierung in keiner Seite des Musterbuchs (Golddateien bleiben gleich)
 *   FM02  mit --felder: allein die Markierungen kommen dazu — ohne sie ist die Seite Byte für Byte die ohne --felder; jeder Pfad führt zu einem Text des Bauplans
 *   FM03  die Stellen: Dachzeile, Titel, Text, Kopf-Knopf, Ort, Punkte je Eintrag, Formular-Knopf, Dank — gleicher Text auf zwei Knöpfen, zwei Pfade
 *   FM04  Prüfung: pruefen mit --felder lässt den markierten Bau durch; ohne --felder sperrt sie ihn (er ist nicht der Bau des Bauplans für die Auslieferung)
 *   FM05  Kommandozeile: bauen.php/pruefen.php kennen --felder; Unbekanntes bleibt ein Aufruffehler
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;

$w = H::wurzel();
$muster = array_values(array_filter(glob("$w/musterbuch/*", GLOB_ONLYDIR) ?: [], static fn (string $d): bool => is_file("$d/seite.json")));
/** Alle Markierungen einer Seite: Liste [Pfad, Element-Name, Text]. */
$marken = static function (string $html): array {
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $aus = [];
    foreach ((new DOMXPath($d))->query('//*[@data-lp-feld]') as $e) {
        $aus[] = [$e->getAttribute('data-lp-feld'), $e->nodeName, trim((string) preg_replace('/\s+/u', ' ', $e->textContent))];
    }
    return $aus;
};
$lesen = static function (array $plan, string $pfad): mixed {
    foreach (explode('.', $pfad) as $teil) {
        if (!is_array($plan) || !array_key_exists(ctype_digit($teil) ? (int) $teil : $teil, $plan)) { return null; }
        $plan = $plan[ctype_digit($teil) ? (int) $teil : $teil];
    }
    return $plan;
};
$k = Katalog::laden();
$gleich = 'Jetzt anmelden';
$seite = H::seite([$k->baustein('termin')['beispiel'], $k->baustein('themen')['beispiel']]);
$seite['bausteine'][0]['knopf']['text'] = $gleich;
$seite['bausteine'][0]['dachzeile'] = 'Gratis Online-Abend';
$seite['bausteine'][3]['knopf_text'] = $gleich;

return [
    'FM01 ohne --felder: keine Markierung im Musterbuch' => static function () use ($muster): void {
        T::ok(count($muster) > 30, 'Musterbuch gefunden: ' . count($muster));
        foreach ($muster as $d) {
            T::notContains((string) file_get_contents("$d/index.html"), 'data-lp-feld', basename($d) . ': ohne Markierung');
        }
    },
    'FM02 mit --felder: nur die Markierungen kommen dazu, jeder Pfad trifft einen Text' => static function () use ($muster, $marken, $lesen): void {
        $gesamt = 0;
        foreach ($muster as $d) {
            $name = basename($d);
            $ohne = Bauer::bauen($d, false)->dateien['index.html'] ?? '';
            $mit = Bauer::bauen($d, false, null, felder: true)->dateien['index.html'] ?? '';
            T::eq(preg_replace('/ data-lp-feld="[^"]*"/', '', $mit), $ohne, "$name: ohne Markierungen Byte für Byte gleich");
            T::eq(Bauer::bauen($d, false)->dateien['impressum.html'] ?? '', Bauer::bauen($d, false, null, felder: true)->dateien['impressum.html'] ?? '', "$name: Impressum ohne Markierung");
            $plan = json_decode((string) file_get_contents("$d/seite.json"), true);
            $m = $marken($mit);
            T::ok($m !== [], "$name: mindestens eine Markierung");
            foreach ($m as [$pfad]) {
                $gesamt++;
                T::ok(preg_match('/^bausteine\.\d+(\.[a-z_]+|\.\d+)+$/', $pfad) === 1 && is_string($lesen($plan, $pfad)), "$name: $pfad ist ein Text des Bauplans");
            }
        }
        T::ok($gesamt > 150, "Markierungen insgesamt: $gesamt");
    },
    'FM03 die Stellen: zwei Knöpfe mit gleichem Text haben zwei Pfade' => static function () use ($seite, $marken, $gleich): void {
        $mit = Bauer::bauen(H::ordner($seite), false, null, felder: true)->dateien['index.html'] ?? '';
        $m = [];
        foreach ($marken($mit) as [$pfad, $el, $text]) { $m[$pfad][] = [$el, $text]; }
        T::eq($m['bausteine.0.dachzeile'] ?? null, [['p', 'Gratis Online-Abend']], 'Dachzeile');
        T::eq($m['bausteine.0.titel'][0][0] ?? null, 'h1', 'Titel des Kopfes');
        T::eq($m['bausteine.0.text'][0][0] ?? null, 'p', 'Text des Kopfes');
        T::eq($m['bausteine.0.knopf.text'] ?? null, [['a', $gleich]], 'Kopf-Knopf');
        T::eq($m['bausteine.3.knopf_text'] ?? null, [['button', $gleich]], 'Formular-Knopf: gleicher Text, eigener Pfad');
        T::eq($m['bausteine.1.ort'][0][0] ?? null, 'p', 'Ort');
        T::eq($m['bausteine.1.titel'][0][0] ?? null, 'h2', 'Titel eines Abschnitts');
        T::eq(array_column($m['bausteine.2.punkte.1'] ?? [], 0), ['li'], 'Punkt 2 der Themen');
        T::eq($m['bausteine.3.danke'][0][0] ?? null, 'span', 'Dank nach dem Absenden');
        T::ok(isset($m['bausteine.1.wenn_leer']), 'Text ohne Termin');
        T::eq(count(array_filter(array_keys($m), static fn (string $p): bool => str_starts_with($p, 'bausteine.2.punkte.'))), count($seite['bausteine'][2]['punkte']), 'jeder Punkt für sich');
        T::notContains($mit, 'data-lp-feld="meta.', 'nichts aus meta');
    },
    'FM04 Prüfung: mit --felder durch, ohne gesperrt' => static function () use ($seite): void {
        $o = H::ordner($seite);
        Bauer::bauen($o, true, null, felder: true);
        T::contains((string) file_get_contents("$o/index.html"), 'data-lp-feld="bausteine.3.knopf_text"', 'markiert geschrieben');
        T::eq(H::sperrend(Pruefer::pruefen($o, '2026-01-01T10:00', null, felder: true)), [], 'mit --felder: nichts sperrt');
        T::contains(H::texte(H::sperrend(Pruefer::pruefen($o, '2026-01-01T10:00'))), 'nicht auf dem Stand ihres Bauplans', 'ohne --felder: der markierte Bau ist nicht der Bau des Bauplans');
    },
    'FM05 Kommandozeile: --felder' => static function () use ($w, $seite): void {
        $o = H::ordner($seite);
        $php = escapeshellarg(PHP_BINARY);
        exec("$php " . escapeshellarg("$w/lp/bauen.php") . ' ' . escapeshellarg($o) . ' --felder 2>&1', $aus, $rc);
        T::eq($rc, 0, 'bauen.php --felder: ' . implode(' ', $aus));
        T::contains((string) file_get_contents("$o/index.html"), 'data-lp-feld=', 'markiert');
        exec("$php " . escapeshellarg("$w/lp/pruefen.php") . ' ' . escapeshellarg($o) . ' --felder --heute 2026-01-01T10:00 2>&1', $aus2, $rc);
        T::eq($rc, 0, 'pruefen.php --felder: ' . implode(' ', $aus2));
        exec("$php " . escapeshellarg("$w/lp/bauen.php") . ' ' . escapeshellarg($o) . ' 2>&1', $aus3, $rc);
        T::ok($rc === 0 && !str_contains((string) file_get_contents("$o/index.html"), 'data-lp-feld'), 'ohne --felder wieder ohne Markierung');
        exec("$php " . escapeshellarg("$w/lp/bauen.php") . ' ' . escapeshellarg($o) . ' --falsch 2>&1', $aus4, $rc);
        T::eq($rc, 2, 'unbekannte Angabe: Aufruffehler');
    },
];
