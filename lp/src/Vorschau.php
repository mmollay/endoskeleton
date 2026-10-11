<?php
/**
 * Die Vorschaukarte beim Teilen des Seitenlinks (Open Graph, Twitter-Karte): welches Bild gezeigt wird, auf welcher
 * Adresse, und das Bild selbst als es/vorschau.jpg — JPEG, 1200×630 (1,91:1), höchstens 300 KB. JPEG, weil es das einzige
 * Format ist, das WhatsApp, Facebook, LinkedIn, Telegram, Slack, X und Messenger sicher lesen (WebP nicht überall); 300 KB,
 * weil WhatsApp größere Bilder oft gar nicht zeigt; die absolute Adresse braucht jeder Crawler, er löst nichts Relatives auf.
 *
 * Das Bild ist meta.vorschaubild, sonst das Bild des Kopfbereichs. Die Adresse (meta.adresse) setzt der Betreiber —
 * der Baukasten kennt seinen Host sonst nicht. Fehlt eines von beiden oder passt etwas nicht, gibt es eine Karte ohne Bild
 * oder gar keine, nie eine kaputte: Die Seite selbst baut immer. Ohne Netz, KI, Uhr und Zufall (gleiches Bild, gleiche Bytes).
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Vorschau
{
    public const DATEI = 'es/vorschau.jpg';
    public const BREITE = 1200;
    public const HOEHE = 630;
    public const MAX_BYTES = 300000;
    /** Darunter zeigen Facebook & Co. nur eine kleine Karte mit Bild am Rand statt der großen. */
    public const MIN_BREITE = 600;
    public const MIN_HOEHE = 315;
    /** Mehr Bildpunkte entpackt GD nicht in vertretbarem Speicher (Bau im Kindprozess mit 256 MB). */
    private const MAX_PIXEL = 16000000;
    private const QUALITAET = [85, 78, 70, 62, 54, 46, 38, 30];

    /**
     * @return array{og: ?array{url: string, bild: ?string, breite: int, hoehe: int, alt: string}, dateien: array<string, string>, befunde: list<Befund>}
     *         og = null: keine Karte (ohne gültige Adresse); bild = null: Karte ohne Bild
     */
    public static function bauen(array $plan, Profil $profil, string $seite, Katalog $katalog): array
    {
        $meta = $plan['meta'];
        $leer = ['og' => null, 'dateien' => [], 'befunde' => []];
        $adresse = $meta['adresse'] ?? null;
        if (!is_string($adresse)) {
            return isset($meta['vorschaubild'])
                ? ['befunde' => [Befund::hinweis('meta.vorschaubild', 'meta.vorschaubild ohne meta.adresse — kein og:image ausgegeben',
                    'Seiteneinstellungen: Das Vorschaubild erscheint beim Teilen des Links erst, wenn die Adresse der Seite feststeht.')]] + $leer
                : $leer;
        }
        if (!self::erlaubt($adresse, $profil)) {
            return ['befunde' => [Befund::hinweis('meta.adresse', "Adresse $adresse gehört nicht zu den freigegebenen Websites des Profils (link_hosts) — keine Vorschaukarte",
                'Seiteneinstellungen: Die Adresse der Seite gehört zu keiner freigegebenen Website des Kundenprofils — beim Teilen des Links erscheint keine Vorschaukarte.')]] + $leer;
        }
        $og = ['url' => $adresse, 'bild' => null, 'breite' => self::BREITE, 'hoehe' => self::HOEHE, 'alt' => ''];
        $quelle = self::quelle($plan, $profil, $seite);
        if ($quelle === null) {
            return ['og' => $og] + $leer;
        }
        $wo = $quelle['plan'] === 'meta.vorschaubild' ? 'Seiteneinstellungen' : 'Abschnitt 1 „' . ($katalog->baustein('hero')['name'] ?? 'hero') . '“';
        $jpeg = self::jpeg($quelle['pfad'], (string) Kontrast::hex($profil->tokens['--bg'] ?? ''));
        if ($jpeg === null) {
            return ['og' => $og, 'befunde' => [Befund::hinweis($quelle['plan'], "{$quelle['datei']} konnte nicht als Vorschaubild aufbereitet werden (kein lesbares WebP, PNG oder JPEG, zu groß oder zu fein)",
                "$wo: Das Bild konnte nicht für die Vorschaukarte aufbereitet werden — beim Teilen des Links erscheint die Karte ohne Bild.")]] + $leer;
        }
        $befunde = [];
        [$b, $h] = array_slice((array) @getimagesize($quelle['pfad']), 0, 2);
        if ($b < self::MIN_BREITE || $h < self::MIN_HOEHE) {
            $befunde[] = Befund::hinweis($quelle['plan'], sprintf('%s ist %d×%d Pixel — zu klein für die große Vorschaukarte (mindestens %d×%d, empfohlen %d×%d)',
                $quelle['datei'], $b, $h, self::MIN_BREITE, self::MIN_HOEHE, self::BREITE, self::HOEHE),
                "$wo: Das Bild ist zu klein für die große Vorschaukarte — beim Teilen erscheint es nur klein oder unscharf.");
        }
        $og['bild'] = $adresse . self::DATEI . '?v=' . substr(hash('sha256', $jpeg), 0, 10);
        $og['alt'] = $quelle['alt'];
        return ['og' => $og, 'dateien' => [self::DATEI => $jpeg], 'befunde' => $befunde];
    }

    /**
     * Das Bild der Karte: meta.vorschaubild, sonst das Bild des ersten Abschnitts (der Kopfbereich steht immer zuerst).
     * null = kein Bild vorgesehen oder die Datei fehlt (das meldet das Schema).
     *
     * @return ?array{datei: string, pfad: string, plan: string, alt: string}
     */
    public static function quelle(array $plan, Profil $profil, string $seite): ?array
    {
        $kopf = $plan['bausteine'][0]['bild']['datei'] ?? null;
        [$datei, $stelle] = isset($plan['meta']['vorschaubild']) ? [$plan['meta']['vorschaubild'], 'meta.vorschaubild'] : [$kopf, 'bausteine.0.bild'];
        if (!is_string($datei) || preg_match('#^(bestand|img)/([^/]+)$#', $datei, $m) !== 1) {
            return null;
        }
        $pfad = $m[1] === 'bestand' ? "$profil->ordner/bilder/$m[2]" : "$seite/img/$m[2]";
        return is_file($pfad) && !is_link($pfad) ? ['datei' => $datei, 'pfad' => $pfad, 'plan' => $stelle, 'alt' => self::alt($datei, $plan, $profil)] : null;
    }

    /** Die Adresse gehört zu einer freigegebenen Website des Profils: genau ein Eintrag aus link_hosts oder eine Unteradresse davon. */
    public static function erlaubt(string $adresse, Profil $profil): bool
    {
        $host = (string) parse_url($adresse, PHP_URL_HOST);
        foreach ($profil->hosts() as $h) {
            if ($host === $h || str_ends_with($host, ".$h")) {
                return true;
            }
        }
        return false;
    }

    /**
     * Quelle → JPEG-Bytes: aus der Mitte auf 1200:630 zugeschnitten, auf 1200×630 gebracht, Durchsichtiges auf die
     * Hintergrundfarbe des Profils gelegt, Qualität so hoch, wie 300 KB erlauben. GD schreibt keine Metadaten (kein Standort
     * aus Handyfotos). null = nicht möglich: nicht lesbar (auch bewegtes WebP), zu groß oder selbst bei niedrigster Qualität zu schwer.
     *
     * @throws BauFehler wenn GD fehlt — das ist die Umgebung, nicht das Bild
     */
    public static function jpeg(string $pfad, string $hintergrund): ?string
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagejpeg')) {
            throw new BauFehler('PHP-Erweiterung gd (mit WebP und JPEG) fehlt — das Vorschaubild kann nicht erzeugt werden');
        }
        $mass = @getimagesize($pfad);
        if ($mass === false || $mass[0] < 1 || $mass[1] < 1 || $mass[0] * $mass[1] > self::MAX_PIXEL) {
            return null;
        }
        $quelle = match ($mass[2]) {
            IMAGETYPE_WEBP => @imagecreatefromwebp($pfad),
            IMAGETYPE_PNG => @imagecreatefrompng($pfad),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($pfad),
            default => false,
        };
        if ($quelle === false) {
            return null;
        }
        [$b, $h] = [imagesx($quelle), imagesy($quelle)];
        // das größte Fenster im Format 1200:630, das ins Bild passt, aus der Mitte
        [$fb, $fh] = $b * self::HOEHE > $h * self::BREITE
            ? [max(1, min($b, intdiv($h * self::BREITE + intdiv(self::HOEHE, 2), self::HOEHE))), $h]
            : [$b, max(1, min($h, intdiv($b * self::HOEHE + intdiv(self::BREITE, 2), self::BREITE)))];
        $ziel = imagecreatetruecolor(self::BREITE, self::HOEHE);
        $farbe = sscanf($hintergrund !== '' ? $hintergrund : '#ffffff', '#%02x%02x%02x');
        imagefill($ziel, 0, 0, imagecolorallocate($ziel, ...$farbe));
        imagecopyresampled($ziel, $quelle, 0, 0, intdiv($b - $fb, 2), intdiv($h - $fh, 2), self::BREITE, self::HOEHE, $fb, $fh);
        foreach (self::QUALITAET as $q) {
            ob_start();
            imagejpeg($ziel, null, $q);
            $bytes = (string) ob_get_clean();
            if ($bytes !== '' && strlen($bytes) <= self::MAX_BYTES) {
                return $bytes;
            }
        }
        return null;
    }

    /** Beschreibung des Bildes für og:image:alt: aus dem Bauplan (Bild des Abschnitts), sonst aus dem Bildbestand; dekorativ = leer. */
    private static function alt(string $datei, array $plan, Profil $profil): string
    {
        foreach ($plan['bausteine'] as $b) {
            foreach (['bild', 'vorschau'] as $f) {
                if (is_array($b[$f] ?? null) && ($b[$f]['datei'] ?? null) === $datei) {
                    return ($b[$f]['dekorativ'] ?? false) === true ? '' : (trim((string) ($b[$f]['alt'] ?? '')) ?: self::bestandsAlt($datei, $profil));
                }
            }
        }
        return self::bestandsAlt($datei, $profil);
    }

    private static function bestandsAlt(string $datei, Profil $profil): string
    {
        return str_starts_with($datei, 'bestand/') ? trim((string) ($profil->bild(substr($datei, 8))['alt'] ?? '')) : '';
    }
}
