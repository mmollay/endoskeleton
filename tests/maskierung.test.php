<?php
/**
 * Jedes Textfeld jedes Bausteins (aus dem Katalog abgeleitet) wird mit Angriffstext gefüllt: Er darf nie roh im
 * gebauten HTML oder JSON-LD ankommen.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;

const ANGRIFF = '<script>alert(1)</script>"onmouseover=x';

$k = Katalog::laden();

/** Setzt ANGRIFF in jedes zeile-/absatz-Feld eines Bausteins (auch in Listen und Knöpfen). */
$vergiften = static function (array $b, array $felder) use (&$vergiften): array {
    foreach ($felder as $name => $def) {
        if (!array_key_exists($name, $b)) {
            continue;
        }
        if (in_array($def['typ'], ['zeile', 'absatz'], true)) {
            $b[$name] = ANGRIFF;
        } elseif ($def['typ'] === 'knopf') {
            $b[$name]['text'] = ANGRIFF;
        } elseif ($def['typ'] === 'liste') {
            foreach ($b[$name] as $i => $x) {
                $b[$name][$i] = is_string($def['eintrag']['typ'] ?? null) ? ANGRIFF : $vergiften($x, $def['eintrag']);
            }
        }
    }
    return $b;
};

$tests = [];
foreach ($k->bausteine as $typ => $e) {
    $tests["$typ: kein Angriffstext roh im HTML"] = static function () use ($typ, $e, $k, $vergiften): void {
        $gift = $vergiften($e['beispiel'], $e['felder']);
        $s = H::seite(in_array($typ, ['hero', 'anmeldung'], true) ? [] : [$gift], ['titel' => ANGRIFF, 'beschreibung' => ANGRIFF]);
        if ($typ === 'hero') { $s['bausteine'][0] = $gift; }
        if ($typ === 'anmeldung') { $s['bausteine'][1] = $gift; }
        $o = H::ordner($s);
        $erg = Bauer::bauen($o);
        T::eq(H::texte($erg->befunde), '', 'Bau ohne Befunde');
        $h = (string) @file_get_contents("$o/index.html");
        T::ok($h !== '', 'Seite gebaut');
        T::ok(substr_count($h, ANGRIFF) === 0, 'Angriffstext nie roh');
        T::notContains($h, '<script>alert', 'kein eingeschleustes Skript');
        T::notContains($h, '"onmouseover', 'kein eingeschleustes Attribut');
        T::eq(substr_count($h, '<script'), substr_count($h, '</script>'), 'jedes Skript sauber geschlossen');
        T::ok(substr_count($h, '&lt;script&gt;alert(1)&lt;/script&gt;') + substr_count($h, '<script>alert(1)<\/script>') + substr_count($h, '<script>alert(1)</script>') > 0, 'Text kommt maskiert an');
    };
}
return $tests;
