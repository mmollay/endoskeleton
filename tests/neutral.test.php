<?php
/**
 * Das Endoskeleton-Repo ist öffentlich: nichts Kundenspezifisches unter lp/, ci/, musterbuch/ und in den Tests.
 * Hosts und E-Mail-Adressen nur aus einer Positivliste bzw. reservierten Domains (example.*, .invalid, .test).
 * Zusätzlich werden zur Laufzeit echte Kundenprofile gesucht (server7) — deren Namen, Domains und Markenfarben dürfen
 * nirgends vorkommen. Die Profile werden nur gelesen, nie gespeichert.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

const ERLAUBT = ['schema.org', 'www.youtube.com', 'www.youtube-nocookie.com', 'www.w3.org'];

$w = H::wurzel();
$dateien = [];
foreach (['lp', 'ci', 'musterbuch'] as $ordner) {
    if (!is_dir("$w/$ordner")) { continue; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$w/$ordner", FilesystemIterator::SKIP_DOTS)) as $f) {
        if (preg_match('/\.(php|json|md|css|js|txt|html|svg|htaccess)$/', $f->getFilename()) || $f->getFilename() === '.htaccess') { $dateien[] = $f->getPathname(); }
    }
}
$dateien = array_merge($dateien, glob("$w/tests/*.php") ?: [], glob("$w/tests/*.json") ?: []);
$reserviert = static fn (string $host): bool => in_array($host, ERLAUBT, true) || (bool) preg_match('/(^|\.)(example\.(com|org|net)|[a-z0-9-]+\.(invalid|test))$/', $host);

return [
    'Hosts nur aus Positivliste und reservierten Domains' => static function () use ($dateien, $reserviert, $w): void {
        T::ok(count($dateien) > 50, 'Dateien geprüft: ' . count($dateien));
        foreach ($dateien as $f) {
            preg_match_all('#https?://([a-z0-9.-]+)#i', (string) file_get_contents($f), $m);
            foreach (array_unique(array_map('strtolower', $m[1])) as $h) { T::ok($reserviert($h), 'fremder Host ' . $h . ' in ' . substr($f, strlen($w) + 1)); }
        }
    },
    'E-Mail-Adressen nur auf reservierten Domains' => static function () use ($dateien, $reserviert, $w): void {
        foreach ($dateien as $f) {
            preg_match_all('/[a-z0-9._%+-]+@([a-z0-9-]+(\.[a-z0-9-]+)+)/i', (string) file_get_contents($f), $m);
            foreach (array_unique(array_map('strtolower', $m[1])) as $d) { T::ok($reserviert($d), 'E-Mail-Domain ' . $d . ' in ' . substr($f, strlen($w) + 1)); }
        }
    },
    'keine Angaben echter Kundenprofile' => static function () use ($dateien, $w): void {
        $merkmale = [];
        foreach (glob('/home/pawbot/projects/websites/lp.*/_profil/profil.json') ?: [] as $pj) {
            $p = json_decode((string) file_get_contents($pj), true) ?: [];
            $merkmale[] = (string) ($p['firma']['name'] ?? '');
            $merkmale[] = (string) ($p['name'] ?? '');
            $merkmale[] = substr((string) strrchr((string) ($p['firma']['email'] ?? ''), '@'), 1);
            foreach ((array) ($p['link_hosts'] ?? []) as $h) { $merkmale[] = (string) preg_replace('/^www\./', '', (string) $h); }
            preg_match_all('/--(primary|primary-dark|primary-light|lp-akzent)\s*:\s*(#[0-9a-f]{3,6})/i', (string) @file_get_contents(dirname($pj) . '/profil.css'), $m);
            array_push($merkmale, ...$m[2]);
        }
        $merkmale = array_values(array_unique(array_filter($merkmale, static fn ($x) => mb_strlen($x) >= 4)));
        T::ok(true, 'Kundenprofile gefunden: ' . count($merkmale) . ' Merkmale');
        foreach ($dateien as $f) {
            $text = (string) file_get_contents($f);
            foreach ($merkmale as $x) { T::ok(stripos($text, $x) === false, 'Kundenmerkmal in ' . substr($f, strlen($w) + 1)); }
        }
    },
];
