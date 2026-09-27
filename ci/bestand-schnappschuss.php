<?php
/**
 * Einmalig: Schnappschuss des Bestands von Commit a14e789 (= 3.50.1; der Tag v3.50.1 fehlt).
 *   php ci/bestand-schnappschuss.php [commit]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$commit = $argv[1] ?? 'a14e789';
$git = static fn (string $args): string => (string) shell_exec('git -C ' . escapeshellarg($root) . ' ' . $args);
$ordner = ['layouts', 'heroes', 'colors', 'buttons', 'fonts', 'softness', 'spacing', 'animation', 'widths', 'navlayouts', 'modules', 'pages'];

$dateien = [];
foreach (preg_split('/\R/', trim($git('ls-tree -r --name-only ' . escapeshellarg($commit)))) ?: [] as $f) {
    if (in_array(explode('/', $f)[0], $ordner, true)) { $dateien[] = $f; }
}
sort($dateien);
$sha = [];
foreach (['base.css', 'shared.js'] as $f) {
    $sha[$f] = hash('sha256', $git('show ' . escapeshellarg("$commit:$f")));
}
file_put_contents(__DIR__ . '/../tests/bestand-3.50.1.json',
    json_encode(['commit' => $commit, 'sha256' => $sha, 'dateien' => $dateien], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($dateien), " Dateien, Prüfsummen geschrieben\n";
