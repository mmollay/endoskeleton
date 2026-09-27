<?php
/**
 * Schreibt die .htaccess eines Sammelordners (lp.<kundendomain>/, auch musterbuch/): kein Cache, nicht indexieren,
 * Baupläne, Profile und Marken als 404. Nie „Require all denied": fail2ban auf server7 sperrt bei „client denied"
 * die aufrufende Adresse für eine Stunde.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Cli;
use Endo\Lp\Dateien;

const HTACCESS = <<<'TXT'
# Erzeugt von lp/sammelordner.php (SSI Endoskeleton) — wird bei Bedarf neu geschrieben, nicht von Hand ändern.
# Gesperrtes antwortet mit 404 (RedirectMatch), nie mit „Require all denied" (fail2ban zählt jedes „client denied").
ErrorDocument 404 default
Options -Indexes
<IfModule mod_expires.c>
ExpiresActive Off
</IfModule>
<IfModule mod_headers.c>
Header always set Cache-Control "no-store"
Header always set X-Robots-Tag "noindex"
</IfModule>
RedirectMatch 404 "(?i)\.(json|md|php)$"
RedirectMatch 404 "(?i)/_profil(/|$)"
RedirectMatch 404 "(?i)/profil(/|$)"
RedirectMatch 404 "(?i)/ENDOSKELETON$"

TXT;

Cli::lauf($argv, 'php lp/sammelordner.php <sammelordner>', 1, static function (array $a): array {
    Dateien::schreiben(Cli::ordner($a[0]) . '/.htaccess', HTACCESS);
    return [];
});
