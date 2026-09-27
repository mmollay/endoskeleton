<?php
/** Lädt die Klassen der Landingpage-Fabrik (Endo\Lp\*) aus lp/src/. */

declare(strict_types=1);

spl_autoload_register(static function (string $klasse): void {
    if (str_starts_with($klasse, 'Endo\\Lp\\')) {
        $datei = __DIR__ . '/src/' . substr($klasse, 8) . '.php';
        if (is_file($datei)) {
            require $datei;
        }
    }
});
