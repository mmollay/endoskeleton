<?php
/**
 * Was der Webserver sehen könnte: Der Endoskeleton-Hauptordner ist DocumentRoot von skeleton.ssi.at (mod_php).
 * Jedes PHP-Skript unter lp/, ci/, tests/ hat die CLI-Wache; lp/, tests/, ci/ antworten mit 404 — nie „denied“.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

const WACHE = "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }";

return [
    'CLI-Wache in jedem ausführbaren PHP-Skript' => static function (): void {
        $w = H::wurzel();
        $dateien = array_merge(glob("$w/lp/*.php") ?: [], glob("$w/lp/bausteine/*.php") ?: [], glob("$w/ci/*.php") ?: [], ["$w/tests/run.php", "$w/tests/hilfe.php"]);
        T::ok(count($dateien) >= 15, 'Skripte gefunden: ' . count($dateien));
        foreach ($dateien as $f) {
            if (basename($f) === 'autoload.php') { continue; }
            $kopf = implode("\n", array_slice(file($f, FILE_IGNORE_NEW_LINES) ?: [], 0, 15));
            T::contains($kopf, WACHE, 'Wache in ' . substr($f, strlen($w) + 1));
        }
    },
    'Klassen unter lp/src tun beim Laden nichts' => static function (): void {
        foreach (glob(H::wurzel() . '/lp/src/*.php') ?: [] as $f) {
            $code = (string) file_get_contents($f);
            T::ok((bool) preg_match('/^<\?php\s+\/\*\*.*?\*\/\s+declare\(strict_types=1\);\s+namespace Endo\\\\Lp;\s+(use [^;]+;\s+)*final class \w+/s', $code), basename($f) . ': nur Klassendefinition');
        }
    },
    '.htaccess sperrt als 404, nie mit Require/Deny' => static function (): void {
        foreach (['lp', 'tests', 'ci'] as $ordner) {
            $h = (string) @file_get_contents(H::wurzel() . "/$ordner/.htaccess");
            T::contains($h, 'RedirectMatch 404 ".*"', "$ordner/.htaccess sperrt alles als 404");
            T::ok(!preg_match('/^\s*(Require|Deny|Order)\b/mi', $h), "$ordner/.htaccess ohne Require/Deny");
        }
    },
];
