<?php
/** Eine Version, überall dieselbe — VERSION, CHANGELOG, Konfigurator, package.json (bis 3.50.1 widersprachen sie sich). */

declare(strict_types=1);

return (static function (): array {
    $root = dirname(__DIR__);
    $version = trim((string) file_get_contents("$root/VERSION"));

    return [
        'VERSION ist eine Versionsnummer' => static fn () => T::ok((bool) preg_match('/^\d+\.\d+\.\d+$/', $version), 'VERSION = ' . $version),
        'CHANGELOG beginnt mit VERSION' => static function () use ($root, $version): void {
            preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', (string) file_get_contents("$root/CHANGELOG.md"), $m);
            T::eq($m[1] ?? '', $version, 'oberste Überschrift im CHANGELOG');
        },
        'Konfigurator nennt VERSION' => static function () use ($root, $version): void {
            $k = (string) file_get_contents("$root/konfigurator.html");
            preg_match("/ENDO_VERSION = '([^']+)'/", $k, $m);
            T::eq($m[1] ?? '', $version, 'ENDO_VERSION');
            T::notContains($k, '?v=3.49.3', 'alte Versionsmarke im Konfigurator');
            // Auch die Stempel an den eigenen Dateien (css/…, js/…) laufen mit: sonst hält der Browser-Cache nach einem Marken-Sprung die alten (3.59.0 vergaß sie)
            preg_match_all('~(?:css|js)/[a-z-]+\.(?:css|js)\?v=([0-9.]+)~', $k, $stempel);
            T::ok(count($stempel[1]) >= 4, 'der Konfigurator hat Versionsstempel an css/ und js/ (gefunden: ' . count($stempel[1]) . ')');
            T::eq(array_values(array_unique($stempel[1])), [$version], 'alle ?v=-Stempel an css/ und js/ nennen VERSION');
        },
        'package.json nennt VERSION und hat keinen Install-Hook' => static function () use ($root, $version): void {
            $p = json_decode((string) file_get_contents("$root/package.json"), true);
            T::eq($p['version'] ?? '', $version, 'package.json version');
            T::ok(!isset($p['scripts']['postinstall']) && !isset($p['scripts']['preinstall']) && !isset($p['scripts']['install']),
                'kein install-Hook in package.json (Schadcode-Commit eb8a34d)');
        },
    ];
})();
