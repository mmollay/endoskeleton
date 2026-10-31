<?php
/** scripts/version-ablegen.sh: getaggter Stand schreibgeschützt ablegen; bestehende Seiten bauen mit ihrer Marke. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Dateien;

$sh = static function (string $befehl): array {
    exec($befehl . ' 2>&1', $aus, $rc);
    return [$rc, implode("\n", $aus)];
};

return [
    'legt einen Tag ab, verweigert Doppeltes und Falsches, der abgelegte Stand baut' => static function () use ($sh): void {
        $repo = T::tmp() . '/repo';
        $versionen = T::tmp() . '/versionen';
        foreach (['lp', 'base.css', 'scripts/version-ablegen.sh', 'scripts/modell-summen.txt'] as $f) { Dateien::kopieren(H::wurzel() . "/$f", "$repo/$f"); }
        file_put_contents("$repo/VERSION", "9.9.9\n");
        $git = 'git -C ' . escapeshellarg($repo) . ' -c user.name=Test -c user.email=test@example.com ';
        [$rc, $aus] = $sh("{$git}init -q && {$git}add -A && {$git}commit -qm Stand && {$git}tag v9.9.9 && {$git}tag v9.9.8");
        T::eq($rc, 0, 'Testrepo angelegt: ' . $aus);
        $ablegen = static fn (string $v) => $sh('ENDO_VERSIONEN=' . escapeshellarg($versionen) . ' bash ' . escapeshellarg("$repo/scripts/version-ablegen.sh") . ' ' . escapeshellarg($v));
        [$rc, $aus] = $ablegen('9.9.9');
        T::eq($rc, 0, 'abgelegt: ' . $aus);
        foreach (['lp/bauen.php', 'lp/bausteine.json', 'lp/lp.css', 'VERSION', 'base.css'] as $f) { T::ok(is_file("$versionen/9.9.9/$f"), "enthält $f"); }
        T::ok(!is_dir("$versionen/9.9.9/tests") && !is_dir("$versionen/9.9.9/musterbuch"), 'nur, was ein Bau braucht');
        // Die Prüfsummenliste der Modelldateien gehört dazu (3.64.0): Bewegung::modell liest sie im Stand der Marke; ohne sie meldete der Prüfer jede Datei in modell/ als fremd.
        T::ok(is_file("$versionen/9.9.9/scripts/modell-summen.txt") && file_get_contents("$versionen/9.9.9/scripts/modell-summen.txt") === file_get_contents(H::wurzel() . '/scripts/modell-summen.txt'),
            'enthält scripts/modell-summen.txt unverändert');
        T::ok(!file_exists("$versionen/9.9.9/scripts/version-ablegen.sh") && !file_exists("$versionen/9.9.9/scripts/modell-holen.sh"), 'aus scripts/ nur die Prüfsummenliste');
        $modell = T::tmp() . '/seite-modell';
        mkdir("$modell/modell", 0777, true);
        file_put_contents("$modell/modell/vision_bundle.js", 'nicht das echte Skript');
        $befund = \Endo\Lp\Bewegung::modell($modell, "$versionen/9.9.9")[0][0] ?? '';
        T::ok(str_contains($befund, 'falsche Prüfsumme'), 'der abgelegte Stand kennt die Modelldateien (falsche Prüfsumme statt „gehört nicht dazu“): ' . $befund);
        T::ok(!is_writable("$versionen/9.9.9/lp/bauen.php") && !is_writable("$versionen/9.9.9/lp"), 'schreibgeschützt');
        [$rc, $aus] = $ablegen('9.9.9');
        T::ok($rc === 2 && str_contains($aus, 'nie überschrieben'), 'vorhandener Ordner wird nie überschrieben: ' . $aus);
        T::eq($ablegen('9.9.7')[0], 2, 'ohne Tag verweigert');
        T::eq($ablegen('9.9')[0], 2, 'ungültige Nummer verweigert');
        T::eq($ablegen('9.9.8')[0], 2, 'VERSION im Tag passt nicht zur Nummer');
        T::ok(!file_exists("$versionen/9.9.8"), 'dabei nichts liegen gelassen');
        T::eq(glob("$versionen/.ablegen-*") ?: [], [], 'kein Zwischenordner zurückgelassen');
        // Ein älterer Stand ohne Prüfsummenliste (vor 3.64.0) lässt sich weiter ablegen, ohne die Datei.
        file_put_contents("$repo/VERSION", "9.9.6\n");
        [$rc, $aus] = $sh("{$git}rm -q scripts/modell-summen.txt && {$git}add -A && {$git}commit -qm Alt && {$git}tag v9.9.6");
        T::eq($rc, 0, 'alter Stand ohne Prüfsummenliste getaggt: ' . $aus);
        T::eq($ablegen('9.9.6')[0], 0, 'ein Stand ohne Prüfsummenliste wird trotzdem abgelegt');
        T::ok(is_file("$versionen/9.9.6/lp/bauen.php") && !file_exists("$versionen/9.9.6/scripts"), '… ohne scripts/');
        $o = H::ordner(H::seite(), '9.9.9');
        [$rc, $aus] = $sh(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$versionen/9.9.9/lp/bauen.php") . ' ' . escapeshellarg($o));
        T::eq($rc, 0, 'Seite mit Marke 9.9.9 baut mit dem abgelegten Stand: ' . $aus);
        T::contains((string) @file_get_contents("$o/index.html"), 'SSI Endoskeleton 9.9.9', 'Versionsmarke der abgelegten Version');
        $sh('chmod -R u+w ' . escapeshellarg($versionen));   // damit die Tests aufräumen können
    },
];
