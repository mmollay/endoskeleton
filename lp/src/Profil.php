<?php
/**
 * Ein Kundenprofil: profil.json (Firma, Anrede, Logo, Einwilligung, Formularziel, erlaubte Hosts, Sperrliste),
 * profil.css (nur :root mit den Pflicht-Tokens und lokale @font-face), Logo, Rechtstexte, Bildbestand.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Profil
{
    /** Farb-Tokens, die base.css in den genutzten Klassen und lp.css lesen — alle als Hex. */
    public const FARBEN = ['--primary', '--primary-dark', '--primary-light', '--text-on-primary', '--bg', '--bg-alt',
        '--card-bg', '--border', '--heading', '--text', '--text-muted', '--focus', '--error', '--success', '--lp-akzent'];
    public const SCHRIFTEN = ['--font-body', '--font-heading'];

    private const SCHLUESSEL = ['format', 'name', 'version', 'sprache', 'anrede', 'vorschlag', 'firma', 'logo', 'einwilligung',
        'formularziel', 'link_hosts', 'sperrliste', 'grenzwoerter'];
    private const FIRMA = ['name', 'strasse', 'plz_ort', 'land', 'email', 'telefon', 'uid', 'firmenbuch'];
    private const RECHTSTEXTE = ['impressum' => 'Das Impressum', 'datenschutz' => 'Die Datenschutzerklärung'];
    private const WO = 'Kundenprofil';

    /**
     * @param array<string, string>        $tokens
     * @param array<string, array>         $bilder  datei => Eintrag aus bilder/bilder.json
     */
    private function __construct(
        public readonly string $ordner,
        public readonly array $daten,
        public readonly string $css,
        public readonly array $tokens,
        public readonly array $bilder,
    ) {
    }

    public static function laden(string $ordner): self
    {
        foreach (['profil.json', 'profil.css'] as $f) {
            if (!is_file("$ordner/$f")) {
                throw new \RuntimeException("Profil unvollständig: $f fehlt in $ordner");
            }
        }
        $daten = json_decode((string) file_get_contents("$ordner/profil.json"), true);
        if (!is_array($daten) || array_is_list($daten)) {
            throw new \RuntimeException("profil.json in $ordner ist kein gültiges JSON-Objekt");
        }
        $css = (string) file_get_contents("$ordner/profil.css");
        $tokens = [];
        preg_match_all('/:root\s*\{([^{}]*)\}/', self::ohneKommentare($css), $bloecke);
        foreach ($bloecke[1] as $block) {
            preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $block, $m, PREG_SET_ORDER);
            foreach ($m as [, $name, $wert]) {
                $tokens[$name] = trim($wert);
            }
        }
        $bilder = [];
        if (is_file("$ordner/bilder/bilder.json")) {
            $liste = json_decode((string) file_get_contents("$ordner/bilder/bilder.json"), true);
            foreach (is_array($liste) ? $liste : [] as $b) {
                if (is_array($b) && is_string($b['datei'] ?? null)) {
                    $bilder[$b['datei']] = $b;
                }
            }
        }
        return new self($ordner, $daten, $css, $tokens, $bilder);
    }

    /** Bild aus dem Bestand (nur wenn die Datei da ist). */
    public function bild(string $datei): ?array
    {
        return isset($this->bilder[$datei]) && is_file("$this->ordner/bilder/$datei") ? $this->bilder[$datei] : null;
    }

    public function rechtstext(string $name): string
    {
        $f = "$this->ordner/rechtliches/$name.txt";
        return is_file($f) ? (string) file_get_contents($f) : '';
    }

    /** @return list<string> */
    public function hosts(): array
    {
        return array_values(array_map('strtolower', array_filter((array) ($this->daten['link_hosts'] ?? []), 'is_string')));
    }

    public function sprache(): string
    {
        return (string) ($this->daten['sprache'] ?? 'de-AT');
    }

    /** @return list<Befund> */
    public function pruefen(): array
    {
        return array_merge($this->pruefeJson(), $this->pruefeCss(), $this->pruefeBilder(), $this->pruefeRechtstexte());
    }

    /** @return list<Befund> */
    private function pruefeJson(): array
    {
        $d = $this->daten;
        $f = [];
        $fehler = static function (string $pfad, string $technik, string $kunde) use (&$f): void {
            $f[] = Befund::sperrend("profil.$pfad", $technik, self::WO . ": $kunde");
        };
        foreach (array_diff(array_keys($d), self::SCHLUESSEL) as $s) {
            $fehler((string) $s, "unbekannter Schlüssel $s in profil.json", "Die Angabe „{$s}“ ist unbekannt.");
        }
        if (($d['format'] ?? null) !== 1) {
            $fehler('format', 'format muss 1 sein', 'Das Profilformat wird nicht unterstützt.');
        }
        if (!is_string($d['name'] ?? null) || !preg_match('/^[\p{L}0-9][\p{L}0-9 ._-]{0,59}$/u', $d['name'])) {
            $fehler('name', 'name fehlt oder enthält Sonderzeichen (nur Buchstaben, Ziffern, Leerzeichen, . _ -)', 'Der Profilname fehlt oder enthält Sonderzeichen.');
        }
        if (!is_string($d['version'] ?? null) || !preg_match('/^\d+\.\d+\.\d+$/', $d['version'])) {
            $fehler('version', 'version muss X.Y.Z sein', 'Die Profilversion fehlt oder hat die falsche Form.');
        }
        if (!in_array($d['sprache'] ?? null, ['de-AT', 'de-DE'], true)) {
            $fehler('sprache', 'sprache muss de-AT oder de-DE sein', 'Die Sprache fehlt.');
        }
        if (!in_array($d['anrede'] ?? null, ['du', 'Sie'], true)) {
            $fehler('anrede', 'anrede muss du oder Sie sein', 'Die Anrede muss „du“ oder „Sie“ sein.');
        }
        if (isset($d['vorschlag']) && !is_bool($d['vorschlag'])) {
            $fehler('vorschlag', 'vorschlag muss true/false sein', 'Die Kennzeichnung „Vorschlag“ hat die falsche Form.');
        }
        $firma = is_array($d['firma'] ?? null) ? $d['firma'] : [];
        foreach (array_diff(array_keys($firma), self::FIRMA) as $s) {
            $fehler("firma.$s", "unbekannter Schlüssel firma.$s", "Die Firmenangabe „{$s}“ ist unbekannt.");
        }
        foreach (['name' => 'Firmenname', 'strasse' => 'Straße', 'plz_ort' => 'PLZ und Ort', 'email' => 'E-Mail-Adresse'] as $s => $label) {
            if (!self::text($firma[$s] ?? null)) {
                $fehler("firma.$s", "firma.$s fehlt", "$label der Firma fehlt.");
            }
        }
        if (self::text($firma['email'] ?? null) && filter_var($firma['email'], FILTER_VALIDATE_EMAIL) === false) {
            $fehler('firma.email', 'firma.email ist keine E-Mail-Adresse', 'Die E-Mail-Adresse der Firma ist ungültig.');
        }
        $logo = is_array($d['logo'] ?? null) ? $d['logo'] : [];
        if (!is_string($logo['datei'] ?? null) || !preg_match('/^logo\.(svg|png|webp)$/', $logo['datei']) || !is_file("$this->ordner/{$logo['datei']}")) {
            $fehler('logo.datei', 'logo.datei muss logo.svg|png|webp sein und existieren', 'Das Logo fehlt.');
        } elseif (str_ends_with($logo['datei'], '.svg')) {
            // Ein SVG wird auch direkt aufgerufen (es/logo.svg) — dann liefe ein Skript darin auf der Seiten-Domain.
            $svg = (string) file_get_contents("$this->ordner/{$logo['datei']}");
            if (preg_match('/<script|<foreignObject|<!ENTITY|<\?xml-stylesheet|\son[a-z]+\s*=|(xlink:)?href\s*=\s*["\'](?!#)/i', $svg)) {
                $fehler('logo.datei', 'logo.svg enthält Skript, Ereignis-Attribut, fremden Verweis oder Entität', 'Das Logo enthält unzulässige Bestandteile.');
            }
        } elseif (!in_array(@getimagesize("$this->ordner/{$logo['datei']}")[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $fehler('logo.datei', 'logo.datei ist kein lesbares PNG/WebP', 'Das Logo ist beschädigt.');
        }
        if (!self::text($logo['alt'] ?? null)) {
            $fehler('logo.alt', 'logo.alt fehlt', 'Der Alternativtext zum Logo fehlt.');
        }
        foreach (['breite', 'hoehe'] as $s) {
            if (!is_int($logo[$s] ?? null) || $logo[$s] < 1 || $logo[$s] > 2000) {
                $fehler("logo.$s", "logo.$s muss eine Zahl von 1 bis 2000 sein", 'Die Maße des Logos fehlen.');
            }
        }
        if (!self::text($d['einwilligung'] ?? null) || mb_strlen($d['einwilligung']) < 20 || mb_strlen($d['einwilligung']) > 600) {
            $fehler('einwilligung', 'einwilligung muss 20–600 Zeichen haben', 'Der Einwilligungstext für das Formular fehlt oder ist zu lang.');
        }
        $ziel = $d['formularziel'] ?? null;
        if (!is_string($ziel) || ($ziel !== '' && !Text::https($ziel, $this->hosts()))) {
            $fehler('formularziel', 'formularziel muss leer oder https auf einen Host aus link_hosts sein (ohne Benutzer, ohne Port)', 'Das Formularziel muss leer oder eine sichere Adresse (https) auf einer freigegebenen Website sein.');
        }
        if (!is_array($d['link_hosts'] ?? null) || !array_is_list($d['link_hosts'])) {
            $fehler('link_hosts', 'link_hosts muss eine Liste sein', 'Die Liste erlaubter Link-Adressen fehlt.');
        } else {
            foreach ($d['link_hosts'] as $i => $h) {
                if (!is_string($h) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $h)) {
                    $fehler("link_hosts.$i", 'link_hosts: nur Hostnamen in Kleinbuchstaben, ohne https://', 'Eine erlaubte Link-Adresse hat die falsche Form.');
                }
            }
        }
        foreach (['sperrliste' => 'Sperrliste', 'grenzwoerter' => 'Liste der Grenzwörter'] as $s => $label) {
            if (!is_array($d[$s] ?? null) || !array_is_list($d[$s]) || array_filter($d[$s], static fn ($w) => !self::text($w))) {
                $fehler($s, "$s muss eine Liste von Texten sein", "Die $label hat die falsche Form.");
            }
        }
        return $f;
    }

    /** @return list<Befund> */
    private function pruefeCss(): array
    {
        $f = [];
        $fehler = static function (string $technik, string $kunde) use (&$f): void {
            $f[] = Befund::sperrend('profil.css', $technik, self::WO . ": $kunde");
        };
        // Streng, damit nichts an den Prüfungen vorbei in den Browser kommt: kein Backslash (Escapes wie u\72l),
        // keine Kommentarzeichen in Texten (sonst verstecken sie Regeln vor der Kommentar-Entfernung).
        if (str_contains($this->css, '\\')) {
            $fehler('Backslash im CSS (Escape) nicht erlaubt', 'Die Profilgestaltung enthält unzulässige Zeichen.');
            return $f;
        }
        if (preg_match('/(["\'])[^"\']*(\/\*|\*\/)[^"\']*\1/', $this->css)) {
            $fehler('Kommentarzeichen innerhalb eines Textes nicht erlaubt', 'Die Profilgestaltung enthält unzulässige Zeichen.');
            return $f;
        }
        $css = self::ohneKommentare($this->css);
        if (stripos($css, '@import') !== false) {
            $fehler('@import ist nicht erlaubt', 'Die Profilgestaltung darf keine fremden Dateien laden.');
            $css = (string) preg_replace('/@import[^;]*;?/i', '', $css);   // nur einmal melden
        }
        $rest = trim((string) preg_replace(['/:root\s*\{[^{}]*\}/', '/@font-face\s*\{[^{}]*\}/i'], '', $css));
        if ($rest !== '') {
            $fehler('nur :root und @font-face erlaubt, gefunden: ' . mb_substr($rest, 0, 60), 'Die Profilgestaltung enthält mehr als Farben und Schriften.');
        }
        // Jedes url( muss genau url(schrift/<name>.woff2) sein, wahlweise in Anführungszeichen.
        $gueltig = preg_match_all('/url\(\s*([\'"]?)(schrift\/[a-z0-9_-]+(\.[a-z0-9_-]+)*\.woff2)\1\s*\)/i', $css, $urls);
        if (substr_count(strtolower($css), 'url(') !== $gueltig) {
            $fehler('url() nicht erlaubt — nur url(schrift/<name>.woff2)', 'Schriften müssen im Profil liegen (keine fremden Adressen).');
        }
        foreach ($urls[2] as $u) {
            if (!is_file("$this->ordner/$u")) {
                $fehler("Schriftdatei $u fehlt", 'Eine Schriftdatei fehlt.');
            }
        }
        foreach (array_merge(self::FARBEN, self::SCHRIFTEN) as $t) {
            if (!isset($this->tokens[$t]) || $this->tokens[$t] === '') {
                $fehler("Pflicht-Token $t fehlt", "Die Angabe $t fehlt.");
            } elseif (in_array($t, self::FARBEN, true) && Kontrast::hex($this->tokens[$t]) === null) {
                $fehler("Token $t ist keine Hex-Farbe: {$this->tokens[$t]}", "Die Farbe $t muss als #rrggbb angegeben sein.");
            }
        }
        return $f;
    }

    /** @return list<Befund> */
    private function pruefeBilder(): array
    {
        $f = [];
        foreach ($this->bilder as $datei => $b) {
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*(\.[a-z0-9_-]+)*\.webp$/', $datei) || !is_file("$this->ordner/bilder/$datei")) {
                $f[] = Befund::sperrend("profil.bilder.$datei", "Bild $datei fehlt oder ist kein WebP mit einfachem Namen", self::WO . ": Das Bild „{$datei}“ fehlt.");
            } elseif ((@getimagesize("$this->ordner/bilder/$datei")[2] ?? null) !== IMAGETYPE_WEBP) {
                $f[] = Befund::sperrend("profil.bilder.$datei", "Bild $datei ist kein lesbares WebP", self::WO . ": Das Bild „{$datei}“ ist beschädigt.");
            }
            if (!self::text($b['alt'] ?? null)) {
                $f[] = Befund::sperrend("profil.bilder.$datei.alt", "Alt-Text zu $datei fehlt", self::WO . ": Zum Bild „{$datei}“ fehlt die Beschreibung (Alternativtext).");
            }
        }
        return $f;
    }

    /** @return list<Befund> */
    private function pruefeRechtstexte(): array
    {
        $f = [];
        foreach (self::RECHTSTEXTE as $name => $label) {
            if (!is_file("$this->ordner/rechtliches/$name.txt") || trim((string) file_get_contents("$this->ordner/rechtliches/$name.txt")) === '') {
                $f[] = Befund::sperrend("profil.rechtliches.$name", "rechtliches/$name.txt fehlt oder ist leer", self::WO . ": $label fehlt.");
            }
        }
        return $f;
    }

    private static function text(mixed $w): bool
    {
        return is_string($w) && trim($w) !== '';
    }

    private static function ohneKommentare(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }
}
