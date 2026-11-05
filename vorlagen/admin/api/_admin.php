<?php
/**
 * Sicherer Admin-Weg — gemeinsame Bibliothek (Vorlage aus dem SSI Endoskeleton, vorlagen/admin/). Eine Datei, kein Framework.
 *
 * Grundsatz: Der Browser hält nie einen Schlüssel. Wer sich auf der Anmeldeseite mit dem Passwort anmeldet, bekommt eine
 * Server-Sitzung (Kennzeichen $_SESSION['admin'], Cookie HttpOnly, Secure, SameSite=Strict). Jede schreibende Anfrage braucht
 * zusätzlich POST, die eigene Herkunft (Origin, ersatzweise Referer) und das CSRF-Token der Sitzung. Schlüssel für Fremddienste
 * stehen in der Konfiguration und werden nur auf dem Server verwendet ($admin->einstellung()).
 *
 * Konfiguration: eine PHP-Datei AUSSERHALB des DocumentRoot (Muster: konfig.beispiel.php). Ihren Pfad nennt die Konstante oder
 * die Umgebungsvariable ADMIN_KONFIG (z. B. „SetEnv ADMIN_KONFIG /home/KONTO/admin-konfig.php“). Fehlt sie oder ist sie ungültig,
 * antwortet jede Admin-Datei mit 404 — der Admin ist dann aus, nie offen.
 *
 * Diese Datei wird nur eingebunden (api/_admin.php, per „RedirectMatch 404 /api/_“ gesperrt); direkt aufgerufen antwortet sie 404.
 */

declare(strict_types=1);

if ((get_included_files()[0] ?? '') === __FILE__) { http_response_code(404); exit; }

/** Abbruch einer Admin-Anfrage mit HTTP-Status; AdminWeg::start() macht daraus die Antwort (Text oder JSON). */
final class AdminAbbruch extends RuntimeException
{
    /** @param array<string, string> $kopf zusätzliche Kopfzeilen, z. B. Retry-After */
    public function __construct(public readonly int $status, string $meldung, public readonly array $kopf = [])
    {
        parent::__construct($meldung);
    }
}

final class AdminWeg
{
    public const SITZUNG = '__Host-admin';

    /** Kein Inline-Skript, kein Inline-Stil, keine fremden Quellen, nicht einbettbar. */
    private const CSP = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; "
        . "form-action 'self'; base-uri 'none'; frame-ancestors 'none'";

    /** @var array<string, mixed> Verweis auf $_SESSION (in Tests ein gewöhnliches Array) */
    private array $sitzung;

    /**
     * Die Logik ohne Webserver: alle Werte werden eingesetzt (start() holt sie aus $_SERVER, $_POST und $_SESSION).
     *
     * @param array<string, mixed> $konfig aus konfigLaden()
     * @param array{methode: string, origin: string, referer: string, fetch_site: string, csrf: string, ip: string, zeit: int} $anfrage aus anfrageAus()
     * @param array<string, mixed> $sitzung
     * @param Closure(): void $erneuern neue Sitzungs-ID, die alte verwerfen (session_regenerate_id(true))
     */
    public function __construct(private readonly array $konfig, private readonly array $anfrage, array &$sitzung, private readonly Closure $erneuern)
    {
        $this->sitzung = &$sitzung;
    }

    /** Für den Webserver: sichere Kopfzeilen, Konfiguration laden (sonst 404 und Ende), Sitzung starten. $json: Fehler als JSON (Endpunkte). */
    public static function start(bool $json = false): self
    {
        foreach (self::kopfzeilen() as $name => $wert) { header("$name: $wert"); }
        $datei = defined('ADMIN_KONFIG') ? (string) constant('ADMIN_KONFIG') : (string) (($_SERVER['ADMIN_KONFIG'] ?? getenv('ADMIN_KONFIG')) ?: '');
        $konfig = $datei === '' ? null : self::konfigLaden($datei, (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($konfig === null) {
            http_response_code(404);
            exit;
        }
        set_exception_handler(static function (Throwable $e) use ($json): void { self::abbruchSenden($e, $json); });
        // Eigener Sitzungsordner je Installation (im Datenordner, außerhalb des DocumentRoot): Sitzungen anderer Seiten desselben Servers
        // liegen nie im selben Speicher — eine dort vergebene Sitzungs-ID kennt diese Installation nicht (use_strict_mode verwirft sie).
        $sitzungen = $konfig['ordner'] . '/sitzungen';
        if (!is_dir($sitzungen) && !@mkdir($sitzungen, 0700) && !is_dir($sitzungen)) { throw new AdminAbbruch(500, 'Sitzung nicht verfügbar.'); }
        session_start([
            'save_path' => $sitzungen,
            'gc_probability' => 1,            // eigener Ordner: der Cron der Distribution räumt ihn nicht auf, PHP selbst tut es
            'gc_divisor' => 100,
            'name' => self::SITZUNG,          // __Host-: nur mit Secure, Path=/ und ohne Domain gültig
            'cookie_lifetime' => 0,
            'cookie_path' => '/',
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,        // fremde, nie vergebene Sitzungs-IDs werden verworfen
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'cache_limiter' => '',            // Cache-Control kommt aus kopfzeilen()
            'gc_maxlifetime' => $konfig['sitzung_minuten'] * 60,
        ]) || throw new AdminAbbruch(500, 'Sitzung nicht verfügbar.');

        return new self($konfig, self::anfrageAus($_SERVER, $_POST), $_SESSION, static function (): void { session_regenerate_id(true); });
    }

    /** @return array<string, string> die Kopfzeilen jeder Admin-Antwort */
    public static function kopfzeilen(): array
    {
        return [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Content-Security-Policy' => self::CSP,
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }

    /**
     * Die Anfrage als einfache Werte; das CSRF-Token aus der Kopfzeile X-CSRF-Token (fetch) oder dem Formularfeld „csrf“.
     *
     * @param array<string, mixed> $server $_SERVER
     * @param array<string, mixed> $felder $_POST
     * @return array{methode: string, origin: string, referer: string, fetch_site: string, csrf: string, ip: string, zeit: int}
     */
    public static function anfrageAus(array $server, array $felder): array
    {
        $text = static fn (mixed $w): string => is_string($w) ? $w : '';
        $kopfToken = $text($server['HTTP_X_CSRF_TOKEN'] ?? null);

        return [
            'methode' => strtoupper($text($server['REQUEST_METHOD'] ?? null)),
            'origin' => $text($server['HTTP_ORIGIN'] ?? null),
            'referer' => $text($server['HTTP_REFERER'] ?? null),
            'fetch_site' => $text($server['HTTP_SEC_FETCH_SITE'] ?? null),
            'csrf' => $kopfToken !== '' ? $kopfToken : $text($felder['csrf'] ?? null),
            'ip' => $text($server['REMOTE_ADDR'] ?? null),
            'zeit' => time(),
        ];
    }

    /**
     * Liest und prüft die Konfiguration; null = Admin aus (der Grund steht im Fehlerprotokoll).
     * Datei und Datenordner dürfen nicht im DocumentRoot liegen; Platzhalter statt Passwort-Hash gilt als ungültig.
     *
     * @return array<string, mixed>|null
     */
    public static function konfigLaden(string $datei, string $docroot = ''): ?array
    {
        $echt = realpath($datei);
        if ($echt === false || !is_file($echt) || !is_readable($echt)) { return self::ungueltig($datei, 'fehlt oder ist nicht lesbar'); }
        if (self::innerhalb($echt, $docroot)) { return self::ungueltig($datei, 'liegt im DocumentRoot'); }
        try {
            $k = (static fn (string $d): mixed => require $d)($echt);
        } catch (Throwable $e) {
            return self::ungueltig($datei, 'nicht ladbar: ' . $e->getMessage());
        }
        if (!is_array($k)) { return self::ungueltig($datei, 'gibt kein Array zurück'); }
        $hash = $k['passwort_hash'] ?? null;
        $bcrypt = is_string($hash) && preg_match('#^\$2[aby]\$\d\d\$[./A-Za-z0-9]{53}$#D', $hash) === 1;   // $2y$ (PHP), $2a$/$2b$ (andere Werkzeuge)
        if (!is_string($hash) || (!$bcrypt && !in_array(password_get_info($hash)['algoName'], ['argon2i', 'argon2id'], true))) {
            return self::ungueltig($datei, 'passwort_hash fehlt (mit password_hash() erzeugen)');
        }
        $herkuenfte = array_map(static fn (mixed $h): string => is_string($h) ? strtolower(rtrim($h, '/')) : '', (array) ($k['herkuenfte'] ?? []));
        if ($herkuenfte === [] || preg_grep('#^https?://[a-z0-9.-]+(:\d{1,5})?$#D', $herkuenfte, PREG_GREP_INVERT) !== []) {
            return self::ungueltig($datei, 'herkuenfte: Liste wie [\'https://example.com\'] — Schema und Host, ohne Pfad');
        }
        $ordner = $k['ordner'] ?? null;
        if (!is_string($ordner) || !str_starts_with($ordner, '/') || !is_dir($ordner) || !is_writable($ordner) || self::innerhalb($ordner, $docroot)) {
            return self::ungueltig($datei, 'ordner: bestehender, beschreibbarer Ordner außerhalb des DocumentRoot');
        }
        $zahlen = [];
        foreach (['sitzung_minuten' => 30, 'bremse_versuche' => 5, 'bremse_minuten' => 15] as $name => $vorgabe) {
            $wert = $k[$name] ?? $vorgabe;
            if (!is_int($wert) || $wert < 1 || $wert > 1440) { return self::ungueltig($datei, "$name: ganze Zahl von 1 bis 1440"); }
            $zahlen[$name] = $wert;
        }

        return ['passwort_hash' => $hash, 'herkuenfte' => array_values(array_unique($herkuenfte)), 'ordner' => rtrim($ordner, '/')] + $zahlen + $k;
    }

    public function methode(): string
    {
        return $this->anfrage['methode'];
    }

    /** Angemeldet und nicht zu lange untätig; jede Prüfung verlängert die Sitzung, eine abgelaufene wird abgemeldet. */
    public function angemeldet(): bool
    {
        $marke = $this->sitzung['admin'] ?? null;
        if (!is_string($marke) || !hash_equals(self::kennung($this->konfig), $marke)) {
            unset($this->sitzung['admin'], $this->sitzung['admin_zuletzt']);   // fremde Installation, altes Passwort oder Unsinn: abgemeldet

            return false;
        }
        $zuletzt = $this->sitzung['admin_zuletzt'] ?? null;
        if (!is_int($zuletzt) || $this->anfrage['zeit'] - $zuletzt > $this->konfig['sitzung_minuten'] * 60) {
            unset($this->sitzung['admin'], $this->sitzung['admin_zuletzt']);

            return false;
        }
        $this->sitzung['admin_zuletzt'] = $this->anfrage['zeit'];

        return true;
    }

    /**
     * Das Kennzeichen „angemeldet“ in der Sitzung: gebunden an diese Installation (Datenordner) und an den Passwort-Hash. Eine Sitzung einer
     * anderen Seite desselben Servers gilt hier nie; ein neues Passwort meldet alle laufenden Sitzungen ab.
     */
    public static function kennung(array $konfig): string
    {
        return hash_hmac('sha256', 'admin-sitzung|' . $konfig['ordner'], $konfig['passwort_hash']);
    }

    /** Das CSRF-Token der Sitzung (zufällig, 32 Byte), für das versteckte Formularfeld „csrf“ und <meta name="csrf-token">. */
    public function csrfToken(): string
    {
        $token = $this->sitzung['admin_csrf'] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = $this->sitzung['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $token;
    }

    /**
     * Anmeldung: POST, eigene Herkunft, CSRF-Token, gebremst je Adresse. true = angemeldet mit neuer Sitzungs-ID und neuem
     * CSRF-Token; false = falsches Passwort (der Versuch zählt für die Bremse).
     */
    public function anmelden(string $passwort): bool
    {
        $this->schreibendPruefen();
        $this->bremsen();
        if ($passwort === '' || strlen($passwort) > 1024 || !password_verify($passwort, $this->konfig['passwort_hash'])) { return false; }
        if (is_file($this->bremsDatei())) { unlink($this->bremsDatei()); }
        ($this->erneuern)();
        $this->sitzung['admin'] = self::kennung($this->konfig);
        $this->sitzung['admin_zuletzt'] = $this->anfrage['zeit'];
        $this->sitzung['admin_csrf'] = bin2hex(random_bytes(32));

        return true;
    }

    /** Vor jeder Änderung: POST (sonst 405), eigene Herkunft und CSRF-Token (sonst 403), angemeldet (sonst 401). */
    public function verlangeSchreiben(): void
    {
        $this->schreibendPruefen();
        if (!$this->angemeldet()) { throw new AdminAbbruch(401, 'Nicht angemeldet — bitte neu anmelden.'); }
    }

    /** Abmelden (POST, Herkunft, CSRF-Token): Sitzung leeren, neue Sitzungs-ID — die alte gilt nicht mehr. */
    public function abmelden(): void
    {
        $this->schreibendPruefen();
        $this->sitzung = [];
        ($this->erneuern)();
    }

    /** Eigener Wert aus der Konfiguration, z. B. der Schlüssel eines Fremddienstes — nur serverseitig verwenden, nie ausgeben. */
    public function einstellung(string $name): mixed
    {
        return $this->konfig[$name] ?? null;
    }

    /** Pfad einer Datei im Datenordner (außerhalb des DocumentRoot); nur der Dateiname zählt. */
    public function datei(string $name): string
    {
        return $this->konfig['ordner'] . '/' . basename($name);
    }

    /** Ein Formularfeld als Text ('' wenn es fehlt oder kein Text ist). */
    public static function feld(string $name): string
    {
        return is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
    }

    /** Nach einem erfolgreichen POST weiter (303, Post/Redirect/Get). */
    public static function weiter(string $ziel): never
    {
        header('Location: ' . $ziel, true, 303);
        exit;
    }

    /** @param array<string, mixed> $daten */
    public static function json(array $daten, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    private function schreibendPruefen(): void
    {
        if ($this->anfrage['methode'] !== 'POST') { throw new AdminAbbruch(405, 'Nur POST.', ['Allow' => 'POST']); }
        $herkunft = $this->anfrage['origin'] !== '' ? strtolower($this->anfrage['origin']) : self::herkunftAus($this->anfrage['referer']);
        $fetch = $this->anfrage['fetch_site'];
        if (($fetch !== '' && $fetch !== 'same-origin') || !in_array($herkunft, $this->konfig['herkuenfte'], true)) {
            throw new AdminAbbruch(403, 'Fremde Herkunft.');
        }
        $soll = $this->sitzung['admin_csrf'] ?? null;
        if (!is_string($soll) || $soll === '' || !hash_equals($soll, $this->anfrage['csrf'])) {
            throw new AdminAbbruch(403, 'CSRF-Token fehlt oder ist falsch — Seite neu laden.');
        }
    }

    /** Schema, Host und Port einer Adresse („https://example.com“), '' wenn sie keine ist. */
    private static function herkunftAus(string $adresse): string
    {
        $teile = parse_url($adresse);
        if (!is_array($teile) || !isset($teile['scheme'], $teile['host'])) { return ''; }

        return strtolower($teile['scheme'] . '://' . $teile['host'] . (isset($teile['port']) ? ':' . $teile['port'] : ''));
    }

    /** Zählt diesen Anmeldeversuch je Adresse (gleitendes Fenster, unter Dateisperre, also auch bei gleichzeitigen Versuchen); zu viele → 429. */
    private function bremsen(): void
    {
        $ordner = $this->konfig['ordner'] . '/bremse';
        if (!is_dir($ordner) && !mkdir($ordner, 0700) && !is_dir($ordner)) { throw new RuntimeException("Bremse: $ordner nicht anlegbar"); }
        $f = fopen($this->bremsDatei(), 'c+') ?: throw new RuntimeException('Bremse: Zähler nicht lesbar');
        try {
            flock($f, LOCK_EX);
            $jetzt = $this->anfrage['zeit'];
            $fenster = $this->konfig['bremse_minuten'] * 60;
            $zeiten = array_values(array_filter((array) json_decode((string) stream_get_contents($f), true),
                static fn (mixed $t): bool => is_int($t) && $t > $jetzt - $fenster));
            if (count($zeiten) >= $this->konfig['bremse_versuche']) {
                throw new AdminAbbruch(429, 'Zu viele Anmeldeversuche — bitte später noch einmal.', ['Retry-After' => (string) max(1, min($zeiten) + $fenster - $jetzt)]);
            }
            $zeiten[] = $jetzt;
            ftruncate($f, 0);
            rewind($f);
            fwrite($f, json_encode($zeiten, JSON_THROW_ON_ERROR));
        } finally {
            flock($f, LOCK_UN);
            fclose($f);
        }
    }

    /**
     * Zählerdatei je Adresse — die IP nie im Klartext: HMAC mit dem Passwort-Hash als Schlüssel (er liegt außerhalb des DocumentRoot).
     * Ein bloßes SHA-256 einer IPv4-Adresse ließe sich in Minuten zurückrechnen.
     */
    private function bremsDatei(): string
    {
        return $this->konfig['ordner'] . '/bremse/' . hash_hmac('sha256', self::bremsSchluessel($this->anfrage['ip']), $this->konfig['passwort_hash']) . '.json';
    }

    /**
     * Wofür die Bremse zählt: IPv4 je Adresse, IPv6 je /64-Netz (ein Anschluss bekommt meist ein ganzes /64 — sonst wären aus einem Netz
     * unbegrenzt viele Versuche möglich), IPv4 in IPv6 („::ffff:192.0.2.1“) wie IPv4.
     */
    public static function bremsSchluessel(string $ip): string
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return 'anders:' . $ip;
        }
        if (strlen($bin) === 16 && str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
            $bin = substr($bin, 12);
        }

        return strlen($bin) === 4 ? 'v4:' . inet_ntop($bin) : 'v6:' . bin2hex(substr($bin, 0, 8));
    }

    private static function innerhalb(string $pfad, string $docroot): bool
    {
        $wurzel = $docroot === '' ? false : realpath($docroot);
        $echt = realpath($pfad);

        return $wurzel !== false && $echt !== false && ($echt === $wurzel || str_starts_with($echt, rtrim($wurzel, '/') . '/'));
    }

    private static function ungueltig(string $datei, string $grund): ?array
    {
        error_log("Admin-Weg aus — Konfiguration $datei: $grund");

        return null;
    }

    /** Antwort auf einen Abbruch (oder 500 bei jedem anderen Fehler, ohne Einzelheiten nach außen). */
    private static function abbruchSenden(Throwable $e, bool $json): void
    {
        $abbruch = $e instanceof AdminAbbruch;
        if (!$abbruch) { error_log('Admin-Weg: ' . $e); }
        if (!headers_sent()) {
            http_response_code($abbruch ? $e->status : 500);
            foreach ($abbruch ? $e->kopf : [] as $name => $wert) { header("$name: $wert"); }
            header('Content-Type: ' . ($json ? 'application/json' : 'text/plain') . '; charset=utf-8');
        }
        $meldung = $abbruch ? $e->getMessage() : 'Interner Fehler.';
        echo $json ? json_encode(['ok' => false, 'fehler' => $meldung], JSON_UNESCAPED_UNICODE) : $meldung;
    }
}
