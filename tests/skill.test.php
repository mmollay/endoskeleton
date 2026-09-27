<?php
/** Skill und Werkstatt-Regeln werden aus dem Katalog erzeugt und sind aktuell (php ci/skill-bauen.php). */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Katalog;
use Endo\Lp\Skill;

$k = Katalog::laden();
$w = H::wurzel();

return [
    'SKILL.md ist aktuell' => static fn () => T::ok(@file_get_contents("$w/lp/SKILL.md") === Skill::skill($k, (string) file_get_contents("$w/lp/skill-kopf.md")), 'lp/SKILL.md = Erzeugnis (php ci/skill-bauen.php)'),
    'werkstatt.md ist aktuell' => static fn () => T::ok(@file_get_contents("$w/lp/werkstatt.md") === Skill::werkstatt($k), 'lp/werkstatt.md = Erzeugnis (php ci/skill-bauen.php)'),
    'der Skill nennt jeden Baustein, jede Variante, jedes Feld' => static function () use ($k): void {
        $s = Skill::skill($k, '');
        foreach ($k->bausteine as $typ => $e) {
            T::contains($s, "### `$typ` — {$e['name']}", "Abschnitt $typ");
            foreach ($e['varianten'] as $v) { T::contains($s, "`$v`", "$typ: Variante $v"); }
            foreach (array_keys($e['felder']) as $f) { T::contains($s, "`$f`", "$typ: Feld $f"); }
        }
        T::contains(Skill::werkstatt($k), 'seite.json', 'Werkstatt-Regeln nennen den Bauplan');
    },
    'die Beispiele im Skill sind gültiges JSON' => static function () use ($k): void {
        preg_match_all('/```json\n(.*?)\n```/s', Skill::skill($k, ''), $m);
        T::ok(count($m[1]) >= count($k->bausteine), 'je Baustein ein Beispiel');
        foreach ($m[1] as $j) { T::ok(is_array(json_decode($j, true)), 'gültiges JSON'); }
    },
];
