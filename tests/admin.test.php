<?php
/**
 * Admin-Vorlage (vorlagen/admin/): sicherer Admin-Weg ohne Schlüssel im Browser — Konfiguration nur außerhalb des DocumentRoot
 * (sonst 404), Anmeldung mit neuer Sitzungs-ID, Herkunftsprüfung, CSRF-Token, Bremse, sichere Kopfzeilen, Sperren als 404.
 * Die Logik (AdminWeg) läuft hier ohne Webserver mit eingesetzten Werten; ein Durchlauf über php -S prüft die echten Einstiege.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vorlagen/admin/api/_admin.php';

return (static function (): array {
    $wurzel = dirname(__DIR__);
    $v = "$wurzel/vorlagen/admin";
    $passwort = 'pw-' . bin2hex(random_bytes(8));          // zur Laufzeit erzeugt: kein Passwort-Literal im Repo
    $hash = password_hash($passwort, PASSWORD_BCRYPT, ['cost' => 4]);
    $herkunft = 'https://example.com';
    $jetzt = 1_800_000_000;

    /** Konfigurationsdatei und Datenordner in einem eigenen Temp-Ordner; $mehr überschreibt Werte. @return array{0: string, 1: string} */
    $umgebung = static function (array $mehr = []) use ($hash, $herkunft): array {
        $d = T::tmp();
        mkdir("$d/daten", 0700);
        file_put_contents("$d/konfig.php", '<?php return ' . var_export($mehr + ['passwort_hash' => $hash, 'herkuenfte' => [$herkunft], 'ordner' => "$d/daten"], true) . ';');

        return ["$d/konfig.php", $d];
    };
    $konfig = static fn (array $mehr = []): array => AdminWeg::konfigLaden($umgebung($mehr)[0]) ?? throw new RuntimeException('Testkonfiguration ungültig');
    $anfrage = static fn (array $mehr = []): array => $mehr + ['methode' => 'POST', 'origin' => $herkunft, 'referer' => '', 'fetch_site' => 'same-origin',
        'csrf' => '', 'ip' => '192.0.2.1', 'zeit' => $jetzt];
    $nichts = static function (): void {};
    /** Status des Abbruchs, 0 = kein Abbruch. */
    $abbruch = static function (callable $f): int {
        try { $f(); } catch (AdminAbbruch $e) { return $e->status; }

        return 0;
    };
    /** Fehlerprotokoll während $f (error_log in eine Temp-Datei). */
    $protokoll = static function (callable $f): string {
        $alt = (string) ini_get('error_log');
        ini_set('error_log', $datei = T::tmp() . '/fehler.log');
        try { $f(); } finally { ini_set('error_log', $alt); }

        return (string) @file_get_contents($datei);
    };
    $ohneKommentar = static fn (string $php): string => implode('', array_map(
        static fn (array|string $t): string => is_array($t) ? (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $t[1]) : $t, token_get_all($php)));

    return [
        'ohne gültige Konfiguration ist der Admin aus, mit gültiger an' => static function () use ($umgebung, $protokoll, $v): void {
            $log = $protokoll(static function () use ($umgebung, $v): void {
                T::eq(AdminWeg::konfigLaden('/gibt/es/nicht/admin-konfig.php'), null, 'fehlende Datei → aus');
                T::eq(AdminWeg::konfigLaden("$v/konfig.beispiel.php"), null, 'konfig.beispiel.php (Platzhalter statt Hash) → aus');
                [$datei, $d] = $umgebung();
                T::eq(AdminWeg::konfigLaden($datei, $d), null, 'Konfiguration im DocumentRoot → aus');
                T::eq(AdminWeg::konfigLaden($datei, "$d/daten"), null, 'Datenordner im DocumentRoot → aus');
                T::ok(is_array(AdminWeg::konfigLaden($datei, T::tmp())), 'außerhalb des DocumentRoot → an');
                foreach ([
                    'Klartext statt Hash' => ['passwort_hash' => 'geheim'],
                    'keine Herkunft' => ['herkuenfte' => []],
                    'Herkunft ohne Schema' => ['herkuenfte' => ['example.com']],
                    'Herkunft mit Pfad' => ['herkuenfte' => ['https://example.com/admin']],
                    'Herkunft mit Zeilenende' => ['herkuenfte' => ["https://example.com\n"]],
                    'Ordner relativ' => ['ordner' => 'daten'],
                    'Ordner fehlt' => ['ordner' => '/gibt/es/nicht'],
                    'Sitzungsdauer 0' => ['sitzung_minuten' => 0],
                    'Bremse als Text' => ['bremse_versuche' => '5'],
                ] as $fall => $mehr) {
                    T::eq(AdminWeg::konfigLaden($umgebung($mehr)[0]), null, "ungültig ($fall) → aus");
                }
                file_put_contents($kaputt = T::tmp() . '/kaputt.php', '<?php return [');
                T::eq(AdminWeg::konfigLaden($kaputt), null, 'Syntaxfehler → aus');
                $k = AdminWeg::konfigLaden($umgebung(['herkuenfte' => ['HTTPS://Example.com/', 'https://www.example.com'], 'dienst_schluessel' => 'x'])[0]) ?? [];
                T::eq($k['herkuenfte'] ?? null, ['https://example.com', 'https://www.example.com'], 'Herkünfte klein, ohne Schrägstrich am Ende');
                T::eq([$k['sitzung_minuten'] ?? 0, $k['bremse_versuche'] ?? 0, $k['bremse_minuten'] ?? 0], [30, 5, 15], 'Vorgaben');
                T::eq($k['dienst_schluessel'] ?? null, 'x', 'eigene Werte bleiben für einstellung() erhalten');
                if (defined('PASSWORD_ARGON2ID')) {
                    $argon = password_hash('x', PASSWORD_ARGON2ID, ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
                    T::ok(is_array(AdminWeg::konfigLaden($umgebung(['passwort_hash' => $argon])[0])), 'Argon2id-Hash wird angenommen');
                }
            });
            T::contains($log, 'liegt im DocumentRoot', 'Grund im Fehlerprotokoll');
            T::contains($log, 'passwort_hash fehlt', 'Grund im Fehlerprotokoll');
        },

        'Anmeldung: falsches Passwort abgelehnt, richtiges meldet an — neue Sitzungs-ID, neues Token, Ablauf nach Untätigkeit' => static function () use ($konfig, $anfrage, $passwort, $jetzt): void {
            $k = $konfig();
            $s = [];
            $id = 'alte-id';
            $erneuern = static function () use (&$id): void { $id = bin2hex(random_bytes(8)); };
            $token = (new AdminWeg($k, $anfrage(['methode' => 'GET']), $s, $erneuern))->csrfToken();
            T::ok((bool) preg_match('/^[0-9a-f]{64}$/', $token), 'CSRF-Token: 32 zufällige Byte');
            T::eq($s['admin_csrf'] ?? null, $token, 'Token liegt in der Sitzung');
            $a = new AdminWeg($k, $anfrage(['csrf' => $token]), $s, $erneuern);
            T::eq($a->anmelden('falsch'), false, 'falsches Passwort abgelehnt');
            T::eq([$a->angemeldet(), $id, isset($s['admin'])], [false, 'alte-id', false], 'nicht angemeldet, Sitzungs-ID unverändert');
            T::eq($a->anmelden(''), false, 'leeres Passwort abgelehnt');
            T::eq($a->anmelden($passwort . str_repeat('x', 2000)), false, 'überlanges Passwort abgelehnt');
            T::eq($a->anmelden($passwort), true, 'richtiges Passwort meldet an');
            T::ok($a->angemeldet() && ($s['admin'] ?? null) === AdminWeg::kennung($k), 'Sitzungskennzeichen admin gesetzt (an diese Installation gebunden)');
            T::ok($id !== 'alte-id', 'neue Sitzungs-ID (session_regenerate_id)');
            T::ok(is_string($s['admin_csrf'] ?? null) && $s['admin_csrf'] !== $token, 'neues CSRF-Token nach der Anmeldung');
            $spaeter = static function (int $sekunden) use ($k, $anfrage, &$s, $erneuern, $jetzt): AdminWeg {
                return new AdminWeg($k, $anfrage(['methode' => 'GET', 'zeit' => $jetzt + $sekunden]), $s, $erneuern);
            };
            T::ok($spaeter(29 * 60)->angemeldet(), 'nach 29 Minuten noch angemeldet');
            T::ok($spaeter(59 * 60)->angemeldet(), 'jede Prüfung verlängert die Sitzung');
            T::ok(!$spaeter(89 * 60 + 1)->angemeldet(), 'nach über 30 Minuten Untätigkeit abgemeldet');
            T::ok(!isset($s['admin']) && !$spaeter(89 * 60 + 2)->angemeldet(), 'Kennzeichen entfernt, bleibt abgemeldet');
        },

        'Sitzung gehört zur Installation: fremde Seite, neues Passwort, leeres Passwort, eigener Sitzungsordner (Prüfung 3.71.0)' => static function () use ($konfig, $umgebung, $protokoll, $anfrage, $passwort, $jetzt, $nichts, $v, $ohneKommentar): void {
            $k = $konfig();
            $s = [];
            $a = new AdminWeg($k, $anfrage(['csrf' => (new AdminWeg($k, $anfrage(['methode' => 'GET']), $s, $nichts))->csrfToken()]), $s, $nichts);
            T::ok($a->anmelden($passwort), 'auf Seite A angemeldet');
            $fremd = $s;
            $andere = $konfig();   // zweite Kundenseite desselben Servers: eigener Datenordner, sogar dasselbe Passwort
            T::ok(!(new AdminWeg($andere, $anfrage(['methode' => 'GET']), $fremd, $nichts))->angemeldet(), 'die Sitzung von Seite A gilt auf Seite B nicht');
            T::ok(!isset($fremd['admin']), 'das fremde Kennzeichen wird dabei entfernt');
            $neu = $s;
            $neuesPasswort = ['passwort_hash' => password_hash('anderes-' . $passwort, PASSWORD_BCRYPT, ['cost' => 4])] + $k;
            T::ok(!(new AdminWeg($neuesPasswort, $anfrage(['methode' => 'GET']), $neu, $nichts))->angemeldet(), 'ein neues Passwort meldet laufende Sitzungen ab');
            T::ok((new AdminWeg($k, $anfrage(['methode' => 'GET', 'zeit' => $jetzt + 60]), $s, $nichts))->angemeldet(), 'auf Seite A weiter angemeldet');
            $log = $protokoll(static function () use ($umgebung): void {
                T::eq(AdminWeg::konfigLaden($umgebung(['passwort_hash' => password_hash('', PASSWORD_BCRYPT, ['cost' => 4])])[0]), null, 'Hash eines leeren Passworts → Admin aus');
            });
            T::contains($log, 'Hash eines leeren Passworts', '… mit Grund im Fehlerprotokoll');
            $b = password_hash($passwort, PASSWORD_BCRYPT, ['cost' => 4]);
            T::ok(is_array(AdminWeg::konfigLaden($umgebung(['passwort_hash' => '$2b$' . substr($b, 4)])[0])), 'bcrypt-Hash mit $2b$ (andere Werkzeuge) wird angenommen');
            $quelle = $ohneKommentar((string) file_get_contents("$v/api/_admin.php"));
            T::contains($quelle, "'save_path' => \$sitzungen", 'Sitzungen im eigenen Ordner der Installation');
            T::contains($quelle, "\$konfig['ordner'] . '/sitzungen'", '… im Datenordner außerhalb des DocumentRoot');
        },

        'Bremse zählt IPv6 je /64-Netz und IPv4 in IPv6 wie IPv4 (Prüfung 3.71.0)' => static function (): void {
            T::eq(AdminWeg::bremsSchluessel('2001:db8::1'), AdminWeg::bremsSchluessel('2001:db8::ffff:2'), 'zwei Adressen aus demselben /64 zählen gemeinsam');
            T::ok(AdminWeg::bremsSchluessel('2001:db8::1') !== AdminWeg::bremsSchluessel('2001:db8:0:1::1'), 'ein anderes /64 zählt getrennt');
            T::eq(AdminWeg::bremsSchluessel('::ffff:192.0.2.1'), AdminWeg::bremsSchluessel('192.0.2.1'), 'IPv4 in IPv6 wie IPv4');
            T::ok(AdminWeg::bremsSchluessel('192.0.2.1') !== AdminWeg::bremsSchluessel('192.0.2.2'), 'IPv4 je Adresse');
        },

        'schreibende Anfrage: nur POST, nur eigene Herkunft, nur mit CSRF-Token, nur angemeldet' => static function () use ($konfig, $anfrage, $abbruch, $nichts, $jetzt): void {
            $k = $konfig(['herkuenfte' => ['https://example.com', 'https://www.example.com']]);
            $token = bin2hex(random_bytes(32));
            $status = static function (array $mehr, ?array $sitzung = null) use ($k, $anfrage, $abbruch, $nichts, $token, $jetzt): int {
                $s = $sitzung ?? ['admin' => AdminWeg::kennung($k), 'admin_zuletzt' => $jetzt, 'admin_csrf' => $token];
                $a = new AdminWeg($k, $anfrage($mehr + ['csrf' => $token]), $s, $nichts);

                return $abbruch(static fn () => $a->verlangeSchreiben());
            };
            T::eq($status([]), 0, 'POST, eigene Herkunft, richtiges Token, angemeldet → erlaubt');
            T::eq($status(['origin' => 'https://www.example.com']), 0, 'zweite erlaubte Herkunft');
            T::eq($status(['methode' => 'GET']), 405, 'GET → 405');
            foreach (['ohne Origin und Referer' => '', 'fremde Seite' => 'https://angreifer.invalid', 'Origin null' => 'null', 'anderes Schema' => 'http://example.com',
                      'Vorsilbe' => 'https://example.com.angreifer.invalid', 'anderer Port' => 'https://example.com:8443'] as $fall => $origin) {
                T::eq($status(['origin' => $origin]), 403, "Herkunft: $fall → 403");
            }
            T::eq($status(['origin' => '', 'referer' => 'https://example.com/admin/index.php']), 0, 'ohne Origin genügt der Referer der eigenen Seite');
            T::eq($status(['origin' => '', 'referer' => 'https://angreifer.invalid/https://example.com/']), 403, 'Referer einer fremden Seite → 403');
            T::eq($status(['fetch_site' => 'cross-site']), 403, 'Sec-Fetch-Site cross-site → 403');
            T::eq($status(['fetch_site' => 'same-site']), 403, 'Sec-Fetch-Site same-site (Nachbar-Subdomain) → 403');
            T::eq($status(['fetch_site' => '']), 0, 'ältere Browser ohne Sec-Fetch-Site: Origin genügt');
            T::eq($status(['csrf' => '']), 403, 'ohne CSRF-Token → 403');
            T::eq($status(['csrf' => bin2hex(random_bytes(32))]), 403, 'falsches CSRF-Token → 403');
            T::eq($status(['csrf' => substr($token, 0, 63)]), 403, 'gekürztes CSRF-Token → 403');
            T::eq($status([], []), 403, 'Sitzung ohne Token → 403');
            T::eq($status([], ['admin_csrf' => $token]), 401, 'nicht angemeldet → 401');
            T::eq($status([], ['admin' => 'ja', 'admin_zuletzt' => $jetzt, 'admin_csrf' => $token]), 401, 'nur das Kennzeichen dieser Installation zählt');
            T::eq($status([], ['admin' => true, 'admin_zuletzt' => $jetzt, 'admin_csrf' => $token]), 401, 'das alte Kennzeichen true zählt nicht mehr');
            T::eq($status([], ['admin' => AdminWeg::kennung($k), 'admin_zuletzt' => $jetzt - 31 * 60, 'admin_csrf' => $token]), 401, 'abgelaufene Sitzung → 401');
            T::eq(AdminWeg::anfrageAus(['REQUEST_METHOD' => 'post', 'HTTP_X_CSRF_TOKEN' => 'kopf'], ['csrf' => 'feld'])['csrf'], 'kopf', 'Token aus X-CSRF-Token vor dem Formularfeld');
            T::eq(AdminWeg::anfrageAus(['REQUEST_METHOD' => 'post'], ['csrf' => 'feld'])['csrf'], 'feld', 'sonst aus dem Formularfeld csrf');
            T::eq(AdminWeg::anfrageAus(['REQUEST_METHOD' => 'post'], ['csrf' => ['feld']])['csrf'], '', 'Formularfeld als Liste zählt nicht');
            T::eq(AdminWeg::anfrageAus(['REQUEST_METHOD' => 'post'], [])['methode'], 'POST', 'Methode groß');
        },

        'Anmelden und Abmelden verlangen ebenfalls POST, Herkunft und CSRF-Token' => static function () use ($konfig, $anfrage, $abbruch, $passwort, $jetzt): void {
            $k = $konfig();
            $token = bin2hex(random_bytes(32));
            $n = 0;
            $erneuern = static function () use (&$n): void { $n++; };
            $weg = static function (array $mehr, array $s) use ($k, $anfrage, $erneuern, $token): AdminWeg {
                return new AdminWeg($k, $anfrage($mehr + ['csrf' => $token]), $s, $erneuern);
            };
            $vorher = ['admin_csrf' => $token];
            T::eq($abbruch(static fn () => $weg(['methode' => 'GET'], $vorher)->anmelden($passwort)), 405, 'Anmelden per GET → 405');
            T::eq($abbruch(static fn () => $weg(['origin' => 'https://angreifer.invalid'], $vorher)->anmelden($passwort)), 403, 'Anmelden von fremder Seite → 403 (Login-CSRF)');
            T::eq($abbruch(static fn () => $weg(['csrf' => ''], $vorher)->anmelden($passwort)), 403, 'Anmelden ohne CSRF-Token → 403');
            T::eq($n, 0, 'abgewiesen: keine neue Sitzung');
            T::ok(!is_dir($k['ordner'] . '/bremse'), 'abgewiesene Anfragen zählen nicht für die Bremse');
            $s = ['admin' => AdminWeg::kennung($k), 'admin_zuletzt' => $jetzt, 'admin_csrf' => $token, 'anderes' => 1];
            T::eq($abbruch(static fn () => $weg(['methode' => 'GET'], $s)->abmelden()), 405, 'Abmelden per GET → 405');
            T::eq($abbruch(static fn () => $weg(['csrf' => bin2hex(random_bytes(32))], $s)->abmelden()), 403, 'Abmelden mit falschem Token → 403');
            $a = new AdminWeg($k, $anfrage(['csrf' => $token]), $s, $erneuern);
            $a->abmelden();
            T::eq([$s, $n, $a->angemeldet()], [[], 1, false], 'abgemeldet: Sitzung leer, neue Sitzungs-ID');
        },

        'Bremse: nach N Fehlversuchen 429 mit Retry-After, je Adresse, gleitendes Fenster, IP nur als HMAC' => static function () use ($konfig, $anfrage, $passwort, $nichts, $jetzt): void {
            $k = $konfig(['bremse_versuche' => 3, 'bremse_minuten' => 10]);
            $token = bin2hex(random_bytes(32));
            $versuch = static function (string $pw, int $sekunden, string $ip = '192.0.2.1') use ($k, $anfrage, $token, $nichts, $jetzt): bool|AdminAbbruch {
                $s = ['admin_csrf' => $token];
                try {
                    return (new AdminWeg($k, $anfrage(['csrf' => $token, 'ip' => $ip, 'zeit' => $jetzt + $sekunden]), $s, $nichts))->anmelden($pw);
                } catch (AdminAbbruch $e) {
                    return $e;
                }
            };
            $gebremst = static fn (bool|AdminAbbruch $r): string => $r instanceof AdminAbbruch ? $r->status . ' Retry-After ' . ($r->kopf['Retry-After'] ?? '-') : var_export($r, true);
            T::eq([$versuch('a', 0), $versuch('b', 60), $versuch('c', 120)], [false, false, false], 'drei Fehlversuche werden abgelehnt');
            T::eq($gebremst($versuch($passwort, 180)), '429 Retry-After 420', 'vierter Versuch → 429, auch mit richtigem Passwort; Retry-After bis der älteste Versuch aus dem Fenster fällt');
            T::eq($versuch('d', 180, '192.0.2.2'), false, 'eine andere Adresse hat ihren eigenen Zähler');
            T::eq($versuch('e', 601), false, 'gleitendes Fenster: der älteste Versuch ist herausgefallen');
            T::eq($gebremst($versuch('f', 602)), '429 Retry-After 58', 'drei Versuche in den letzten 10 Minuten → wieder 429');
            T::eq($versuch($passwort, 661), true, 'danach meldet das richtige Passwort an');
            $dateien = glob($k['ordner'] . '/bremse/*') ?: [];
            T::eq(count($dateien), 1, 'nach der Anmeldung ist der Zähler dieser Adresse gelöscht, der der anderen bleibt');
            foreach ($dateien as $f) {
                T::ok((bool) preg_match('/^[0-9a-f]{64}\.json$/', basename($f)), 'Dateiname ist ein Hash: ' . basename($f));
                T::notContains((string) file_get_contents($f), '192.0.2', 'keine IP im Zähler');
                T::ok(basename($f, '.json') !== hash('sha256', '192.0.2.2'), 'kein bloßes SHA-256 der IP (ließe sich zurückrechnen)');
            }
        },

        'sichere Kopfzeilen: no-store, nosniff, same-origin, Content-Security-Policy ohne Inline' => static function (): void {
            $kopf = AdminWeg::kopfzeilen();
            T::eq([$kopf['Cache-Control'] ?? null, $kopf['X-Content-Type-Options'] ?? null, $kopf['Referrer-Policy'] ?? null], ['no-store', 'nosniff', 'same-origin'], 'Kopfzeilen');
            $csp = $kopf['Content-Security-Policy'] ?? '';
            foreach (["default-src 'none'", "script-src 'self'", "style-src 'self'", "connect-src 'self'", "form-action 'self'", "frame-ancestors 'none'", "base-uri 'none'"] as $teil) {
                T::contains($csp, $teil, 'Content-Security-Policy');
            }
            T::ok(!preg_match('/unsafe-|data:|\*|https?:/', $csp), 'Content-Security-Policy ohne unsafe-inline/unsafe-eval, data:, * und fremde Quellen');
        },

        'Quelltext: kein Schlüssel und kein Token-Literal im Browser, kein Inline-Skript/-Stil, Änderungen nur per POST' => static function () use ($v, $ohneKommentar): void {
            $js = (string) preg_replace(['~/\*.*?\*/~s', '~(?<!:)//[^\n]*~'], '', (string) file_get_contents("$v/admin/admin.js"));
            $seite = (string) file_get_contents("$v/admin/index.php");
            foreach (['admin/admin.js' => $js, 'admin/index.php' => $ohneKommentar($seite)] as $name => $code) {
                T::ok(strlen($code) > 500, "$name ist da");
                T::ok(!preg_match('/\$(2[aby]|argon2i?d?)\$/', $code), "$name: kein Passwort-Hash");
                T::ok(!preg_match('/api[_-]?key|authorization|bearer|secret|geheim|schl(ue|ü)ssel|passwor[dt]\s*[:=]/i', $code), "$name: kein Schlüssel, kein Authorization-Kopf, kein Passwort");
                preg_match_all('/[A-Za-z0-9+\/_=-]{20,}/', $code, $m);
                T::eq(array_values(array_filter($m[0], static fn (string $w): bool => preg_match('/\d/', $w) && preg_match('/[a-z]/i', $w))), [], "$name: keine schlüsselartige Zeichenkette");
                T::ok(!preg_match('/localStorage|sessionStorage|document\.cookie|indexedDB/', $code), "$name: keine Berechtigung im Web-Speicher");
            }
            T::contains($js, "credentials: 'same-origin'", 'admin.js: fetch mit credentials same-origin');
            T::contains($js, 'meta[name="csrf-token"]', 'admin.js: Token aus <meta name="csrf-token">');
            T::contains($js, "'X-CSRF-Token': meta.content", 'admin.js: Token im Kopf X-CSRF-Token');
            T::ok(str_starts_with(trim($js), '(function () {') && str_contains($js, "'use strict';"), 'admin.js: eigene Funktion mit use strict, keine globale Variable');
            T::ok(!preg_match('/innerHTML|outerHTML|eval\(|new Function/', $js), 'admin.js: kein innerHTML, kein eval');
            T::ok(!preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $seite), 'index.php: kein Inline-Skript');
            T::ok(!preg_match('/<style\b|\sstyle\s*=/i', $seite), 'index.php: kein Inline-Stil');
            T::ok(!preg_match('/\son[a-z]+\s*=|javascript:/i', $seite), 'index.php: keine Ereignis-Attribute, kein javascript:');
            T::contains($seite, '<meta name="csrf-token" content="<?= $h($csrf) ?>">', 'index.php: Token nur aus der Sitzung');
            preg_match_all('/<form\b[^>]*>/i', $seite, $formulare);
            T::ok(count($formulare[0]) === 3 && count(preg_grep('/method="post"/', $formulare[0])) === 3, 'index.php: jedes Formular per POST');
            foreach (['api/_admin.php', 'api/aktion.php', 'admin/index.php', 'konfig.beispiel.php'] as $datei) {
                $code = $ohneKommentar((string) file_get_contents("$v/$datei"));
                T::ok((bool) preg_match('/^<\?php\s+declare\(strict_types=1\);/', $code), "$datei: declare(strict_types=1) am Anfang");
                T::ok(!preg_match('/\$_(GET|REQUEST|COOKIE)\b/', $code), "$datei: liest weder \$_GET noch \$_REQUEST noch \$_COOKIE");
            }
            foreach (['api/aktion.php' => 'AdminWeg::start(json: true);', 'admin/index.php' => 'AdminWeg::start();'] as $datei => $start) {
                $vorher = strstr($ohneKommentar((string) file_get_contents("$v/$datei")), $start, true);
                T::ok($vorher !== false && !str_contains($vorher, '?>') && !preg_match('/\b(echo|print|header)\b/', $vorher), "$datei: AdminWeg::start() vor jeder Ausgabe");
            }
            foreach (glob("$v/api/*.php") ?: [] as $f) {
                $code = (string) file_get_contents($f);
                T::ok(str_starts_with(basename($f), '_')
                    ? str_contains($code, "if ((get_included_files()[0] ?? '') === __FILE__) { http_response_code(404); exit; }")
                    : str_contains($code, "\$admin = AdminWeg::start(json: true);\n\$admin->verlangeSchreiben();"),
                    'api/' . basename($f) . ': Hilfsdatei _*.php (direkt aufgerufen 404) oder Endpunkt, der zuerst verlangeSchreiben() prüft');
            }
            $beispiel = (string) file_get_contents("$v/konfig.beispiel.php");
            T::ok(!preg_match('/\$(2[aby]|argon2i?d?)\$/', $beispiel), 'konfig.beispiel.php: kein echter Hash');
            T::contains($beispiel, "php -r 'echo password_hash(", 'konfig.beispiel.php: wie man den Hash erzeugt');
        },

        '.htaccess: ganz vorlagen/ als 404, Hilfsdateien api/_* als 404, nie Require/Deny' => static function () use ($wurzel, $v): void {
            $oben = (string) @file_get_contents("$wurzel/vorlagen/.htaccess");
            $admin = (string) @file_get_contents("$v/.htaccess");
            T::contains($oben, 'RedirectMatch 404 ".*"', 'vorlagen/.htaccess sperrt alles als 404 (skeleton.ssi.at)');
            T::contains($admin, 'RedirectMatch 404 "/api/_"', 'Hilfsdateien api/_* als 404');
            foreach (['vorlagen/.htaccess' => $oben, 'vorlagen/admin/.htaccess' => $admin] as $name => $h) {
                T::ok(!preg_match('/^\s*(Require|Deny|Order|Allow)\b/mi', $h), "$name ohne Require/Deny/Order/Allow");
            }
            preg_match_all('/^RedirectMatch 404 "([^"]+)"$/m', $admin, $m);
            $gesperrt = static fn (string $pfad): bool => array_filter($m[1], static fn (string $r): bool => (bool) preg_match('~' . $r . '~', $pfad)) !== [];
            foreach (['/api/_admin.php' => true, '/api/_weitere.php' => true, '/konfig.beispiel.php' => true,
                      '/admin/' => false, '/admin/index.php' => false, '/admin/admin.js' => false, '/api/aktion.php' => false] as $pfad => $soll) {
                T::eq($gesperrt($pfad), $soll, $pfad . ($soll ? ' gesperrt' : ' erreichbar'));
            }
        },

        'nur Beispiel-Domains, keine Zugangsdaten-Dateien in der Vorlage' => static function () use ($wurzel): void {
            $dateien = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$wurzel/vorlagen", FilesystemIterator::SKIP_DOTS)), false);
            T::ok(count($dateien) >= 8, 'Dateien der Vorlage: ' . count($dateien));
            $reserviert = static fn (string $host): bool => (bool) preg_match('/(^|\.)(example\.(com|org|net)|[a-z0-9-]+\.(invalid|test))$/', $host);
            foreach ($dateien as $f) {
                $text = (string) file_get_contents($f->getPathname());
                $name = substr($f->getPathname(), strlen($wurzel) + 1);
                preg_match_all('#https?://(?:[^/@\s"\']*@)?([a-z0-9.-]+)#i', $text, $hosts);
                preg_match_all('/[a-z0-9._%+-]+@([a-z0-9-]+(\.[a-z0-9-]+)+)/i', $text, $post);
                foreach (array_unique(array_map('strtolower', array_merge($hosts[1], $post[1]))) as $h) { T::ok($reserviert($h), "fremder Host $h in $name"); }
                T::ok(!preg_match('/^\.env|\.(key|pem|p12)$|^konfig\.php$|^admin-konfig\.php$/', $f->getFilename()), "keine Zugangsdaten-Datei: $name");
            }
        },

        'Durchlauf über php -S: ohne Konfiguration 404, Anmeldung, Aktion, Abmeldung' => static function () use ($v, $passwort, $hash, $herkunft): void {
            $d = T::tmp();
            mkdir("$d/daten", 0700);
            mkdir("$d/sitzungen", 0700);
            $konfigDatei = "$d/admin-konfig.php";          // noch nicht da: der Admin ist aus
            $frei = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('kein freier Port');
            $port = (int) substr((string) strrchr((string) stream_socket_get_name($frei, false), ':'), 1);
            fclose($frei);
            $server = proc_open([PHP_BINARY, '-d', "session.save_path=$d/sitzungen", '-d', "error_log=$d/fehler.log", '-d', 'log_errors=1', '-d', 'display_errors=0',
                '-S', "127.0.0.1:$port", '-t', $v], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $rohre, $v, ['ADMIN_KONFIG' => $konfigDatei] + getenv());
            if (!is_resource($server)) { T::ok(false, 'php -S startet nicht'); return; }
            try {
                $bereit = false;
                for ($i = 0; $i < 100 && !$bereit; $i++) {
                    $probe = @stream_socket_client("tcp://127.0.0.1:$port", $nr, $meldung, 0.2);
                    if ($probe) { fclose($probe); $bereit = true; } else { usleep(50_000); }
                }
                T::ok($bereit, 'php -S läuft');
                if (!$bereit) { return; }
                $holen = static function (string $methode, string $pfad, array $kopf = [], array $felder = []) use ($port): array {
                    $koerper = $methode === 'POST' ? http_build_query($felder) : '';
                    $s = stream_socket_client("tcp://127.0.0.1:$port", $nr, $meldung, 5) ?: throw new RuntimeException("php -S: $meldung");
                    stream_set_timeout($s, 10);
                    $kopf = array_merge(['Host: example.com'], $kopf, $methode === 'POST' ? ['Content-Type: application/x-www-form-urlencoded', 'Content-Length: ' . strlen($koerper)] : []);
                    fwrite($s, "$methode $pfad HTTP/1.0\r\n" . implode("\r\n", $kopf) . "\r\n\r\n" . $koerper);
                    [$kopfteil, $inhalt] = explode("\r\n\r\n", (string) stream_get_contents($s), 2) + ['', ''];
                    fclose($s);
                    $zeilen = explode("\r\n", $kopfteil);

                    return ['status' => (int) (explode(' ', $zeilen[0])[1] ?? 0), 'kopf' => implode("\n", array_slice($zeilen, 1)), 'inhalt' => $inhalt];
                };
                $keks = static fn (array $r): string => preg_match_all('/^Set-Cookie: (__Host-admin=[^;]+)/mi', $r['kopf'], $m) ? (string) end($m[1]) : '';
                $token = static fn (array $r): string => preg_match('/<meta name="csrf-token" content="([0-9a-f]{64})">/', $r['inhalt'], $m) ? $m[1] : '';
                $o = "Origin: $herkunft";

                foreach (['/admin/index.php', '/admin/', '/api/aktion.php', '/api/_admin.php'] as $pfad) {
                    T::eq($holen('GET', $pfad)['status'], 404, "ohne Konfiguration: GET $pfad → 404");
                }
                T::eq($holen('POST', '/api/aktion.php', [$o], ['text' => 'x'])['status'], 404, 'ohne Konfiguration: POST api/aktion.php → 404');
                file_put_contents($konfigDatei, '<?php return ' . var_export(['passwort_hash' => $hash, 'herkuenfte' => [$herkunft], 'ordner' => "$d/daten"], true) . ';');
                T::eq($holen('GET', '/api/_admin.php')['status'], 404, 'mit Konfiguration: die Bibliothek direkt aufgerufen → 404');

                $seite = $holen('GET', '/admin/index.php');
                T::eq($seite['status'], 200, 'Anmeldeseite');
                T::contains($seite['inhalt'], 'name="passwort"', 'Passwortfeld');
                foreach (AdminWeg::kopfzeilen() as $name => $wert) { T::contains($seite['kopf'], "$name: $wert", 'Kopfzeile der Anmeldeseite'); }
                $zeile = preg_match('/^Set-Cookie: __Host-admin=.*$/mi', $seite['kopf'], $m) ? strtolower($m[0]) : '';
                foreach (['; path=/', '; secure', '; httponly', '; samesite=strict'] as $teil) { T::contains($zeile, $teil, 'Sitzungs-Cookie __Host-admin'); }
                T::notContains($zeile, 'domain=', 'Sitzungs-Cookie ohne Domain');
                $k1 = $keks($seite);
                $t1 = $token($seite);
                T::ok($k1 !== '' && $t1 !== '', 'Cookie und CSRF-Token der Anmeldeseite');

                $anmelden = static fn (string $keks, string $herkunftKopf, string $pw, string $tok): array
                    => $holen('POST', '/admin/index.php', ["Cookie: $keks", $herkunftKopf], ['aktion' => 'anmelden', 'csrf' => $tok, 'passwort' => $pw]);
                $r = $anmelden($k1, $o, 'falsch', $t1);
                T::eq([$r['status'], str_contains($r['inhalt'], 'Anmeldung fehlgeschlagen.')], [403, true], 'falsches Passwort → 403 mit Meldung');
                T::eq($anmelden($k1, 'Origin: https://angreifer.invalid', $passwort, $t1)['status'], 403, 'Anmeldung von fremder Seite → 403');
                T::eq($anmelden($k1, $o, $passwort, '')['status'], 403, 'Anmeldung ohne CSRF-Token → 403');
                $r = $anmelden($k1, $o, $passwort, $t1);
                T::eq($r['status'], 303, 'richtiges Passwort → 303 weiter');
                $k2 = $keks($r);
                T::ok($k2 !== '' && $k2 !== $k1, 'neue Sitzungs-ID nach der Anmeldung');
                $seite = $holen('GET', '/admin/index.php', ["Cookie: $k2"]);
                $t2 = $token($seite);
                T::contains($seite['inhalt'], 'action="../api/aktion.php"', 'angemeldet: Beispiel-Formular');
                T::ok($t2 !== '' && $t2 !== $t1, 'neues CSRF-Token nach der Anmeldung');
                T::contains($holen('GET', '/admin/index.php', ["Cookie: $k1"])['inhalt'], 'name="passwort"', 'die alte Sitzungs-ID ist nicht angemeldet');

                $aktion = static fn (array $kopf, string $text = 'x'): array => $holen('POST', '/api/aktion.php', $kopf, ['text' => $text]);
                $text = 'Grüße – gespeichert über den Admin-Weg';
                $r = $aktion(["Cookie: $k2", $o, "X-CSRF-Token: $t2"], $text);
                T::eq([$r['status'], json_decode($r['inhalt'], true)['ok'] ?? null], [200, true], 'Aktion mit Sitzung, Herkunft und Token: ' . $r['inhalt']);
                T::eq((string) @file_get_contents("$d/daten/text.txt"), $text, 'Text serverseitig im Datenordner gespeichert');
                T::contains($holen('GET', '/admin/index.php', ["Cookie: $k2"])['inhalt'], "$text</textarea>", 'Text steht wieder im Formular');
                T::eq($aktion(["Cookie: $k2", 'Origin: https://angreifer.invalid', "X-CSRF-Token: $t2"])['status'], 403, 'Aktion von fremder Seite → 403');
                T::eq($aktion(["Cookie: $k2", "Referer: $herkunft/admin/index.php", "X-CSRF-Token: $t2"])['status'], 200, 'ohne Origin genügt der Referer der eigenen Seite');
                T::eq($aktion(["Cookie: $k2", $o])['status'], 403, 'Aktion ohne CSRF-Token → 403');
                T::eq($aktion(["Cookie: $k2", $o, 'X-CSRF-Token: ' . str_repeat('0', 64)])['status'], 403, 'Aktion mit falschem CSRF-Token → 403');
                T::eq($aktion(["Cookie: $k2", $o, "X-CSRF-Token: $t2"], str_repeat('a', 10001))['status'], 422, 'zu langer Text → 422');
                $r = $holen('GET', '/api/aktion.php', ["Cookie: $k2"]);
                T::eq($r['status'], 405, 'Aktion per GET → 405');
                T::contains($r['kopf'], 'Content-Type: application/json', 'Fehler des Endpunkts als JSON');

                $r = $holen('POST', '/admin/index.php', ["Cookie: $k2", $o], ['aktion' => 'abmelden', 'csrf' => $t2]);
                T::eq($r['status'], 303, 'Abmelden → 303');
                T::eq($aktion(["Cookie: $k2", $o, "X-CSRF-Token: $t2"])['status'], 403, 'nach dem Abmelden gilt die alte Sitzung nicht mehr');
                $k3 = $keks($r);
                $t3 = $token($holen('GET', '/admin/index.php', ["Cookie: $k3"]));
                T::eq($aktion(["Cookie: $k3", $o, "X-CSRF-Token: $t3"])['status'], 401, 'neue Sitzung ohne Anmeldung → 401');

                $log = (string) @file_get_contents("$d/fehler.log");
                T::ok(!preg_match('/PHP (Warning|Notice|Deprecated|Fatal|Parse)/', $log), 'keine PHP-Warnungen im Durchlauf: ' . substr($log, 0, 600));
                T::contains($log, 'Admin-Weg aus', 'die fehlende Konfiguration steht im Fehlerprotokoll');
            } finally {
                proc_terminate($server);
                proc_close($server);
            }
        },
    ];
})();
