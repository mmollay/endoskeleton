<?php
/**
 * Testläufer der Landingpage-Fabrik — ohne Abhängigkeiten (Vorbild: GRIDKit tests/run.php).
 *
 *   php tests/run.php            alles
 *   php tests/run.php schema     nur tests/schema.test.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (is_file(__DIR__ . '/../lp/autoload.php')) {
    require __DIR__ . '/../lp/autoload.php';
}

final class T
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    public static string $current = '';
    /** @var list<string> */
    private static array $tmp = [];

    public static function ok(bool $cond, string $what): void
    {
        if ($cond) { self::$passed++; return; }
        self::$failures[] = self::$current . ' — ' . $what;
    }

    public static function eq(mixed $actual, mixed $expected, string $what): void
    {
        self::ok($actual === $expected, sprintf('%s (erwartet %s, bekommen %s)', $what,
            var_export($expected, true), var_export($actual, true)));
    }

    public static function contains(string $haystack, string $needle, string $what): void
    {
        self::ok(str_contains($haystack, $needle), $what . ' — fehlt: ' . $needle);
    }

    public static function notContains(string $haystack, string $needle, string $what): void
    {
        self::ok(!str_contains($haystack, $needle), $what . ' — sollte fehlen: ' . $needle);
    }

    /** Ein leerer Temp-Ordner; wird am Ende des Laufs gelöscht. */
    public static function tmp(): string
    {
        $d = sys_get_temp_dir() . '/lp-test-' . bin2hex(random_bytes(6));
        mkdir($d, 0700, true);
        self::$tmp[] = $d;
        return $d;
    }

    public static function aufraeumen(): void
    {
        foreach (self::$tmp as $d) { self::loeschen($d); }
    }

    private static function loeschen(string $pfad): void
    {
        if (is_link($pfad) || is_file($pfad)) { unlink($pfad); return; }
        if (!is_dir($pfad)) { return; }
        foreach (scandir($pfad) ?: [] as $e) {
            if ($e !== '.' && $e !== '..') { self::loeschen("$pfad/$e"); }
        }
        rmdir($pfad);
    }
}

$filter = $argv[1] ?? '';
$files  = glob(__DIR__ . '/*.test.php') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file, '.test.php');
    if ($filter !== '' && $name !== $filter) { continue; }
    try {
        $tests = require $file;
    } catch (\Throwable $e) {
        T::$failures[] = "$name — Testdatei nicht ladbar: " . get_class($e) . ': ' . $e->getMessage();
        continue;
    }
    foreach ($tests as $label => $fn) {
        T::$current = "$name/$label";
        try {
            $fn();
        } catch (\Throwable $e) {
            T::$failures[] = T::$current . ' — warf ' . get_class($e) . ': ' . $e->getMessage();
        }
    }
}
T::aufraeumen();

echo "\n";
if (T::$failures) {
    echo 'FEHLER — ' . count(T::$failures) . ' Problem(e), ' . T::$passed . " Prüfung(en) bestanden\n\n";
    foreach (T::$failures as $f) { echo "  ✗ $f\n"; }
    echo "\n";
    exit(1);
}
echo 'ok — ' . T::$passed . " Prüfungen bestanden\n\n";
exit(0);
