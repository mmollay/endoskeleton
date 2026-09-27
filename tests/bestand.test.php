<?php
/** Bestehende Websites nutzen base.css, shared.js und die Presets — die bleiben exakt wie in 3.50.1. */

declare(strict_types=1);

return (static function (): array {
    $root = dirname(__DIR__);
    $snap = json_decode((string) file_get_contents(__DIR__ . '/bestand-3.50.1.json'), true);

    return [
        'base.css unverändert seit 3.50.1' => static fn () => T::eq(hash_file('sha256', "$root/base.css"), $snap['sha256']['base.css'], 'Prüfsumme base.css'),
        'shared.js unverändert seit 3.50.1' => static fn () => T::eq(hash_file('sha256', "$root/shared.js"), $snap['sha256']['shared.js'], 'Prüfsumme shared.js'),
        'alle Preset-Dateien von 3.50.1 sind noch da' => static function () use ($root, $snap): void {
            T::ok(count($snap['dateien']) > 100, 'Schnappschuss enthält die Presets (' . count($snap['dateien']) . ')');
            foreach ($snap['dateien'] as $f) { T::ok(is_file("$root/$f"), "fehlt: $f"); }
        },
    ];
})();
