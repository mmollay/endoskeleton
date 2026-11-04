<?php
/** Hilfen für die Vorlagen unter lp/bausteine/: Maskieren, Fließtext mit Links, Abschnitt öffnen, Bilder, Knöpfe, JSON-LD, Anrede. */

declare(strict_types=1);

namespace Endo\Lp;

final class Kontext
{
    /** Schwellen der Titelgröße in Zeichen: Stufe = Anzahl der Schwellen, die der Titel überschreitet (0 = Normalgröße). Einzige Liste — hero.php schreibt sie ins HTML, lp.js liest sie dort. */
    public const TITEL_STUFEN = [36, 52, 68];

    /**
     * Wie breit ein Bild auf der Seite höchstens steht (sizes zu srcset, 3.70.0) — je Verwendung, nicht je Bild. Der Inhalt ist höchstens
     * 75 rem breit (base.css --max-width) mit 1,5 rem Rand; „halb“ ist die Bildspalte neben dem Text (ab 60 rem zwei Spalten).
     */
    public const GROESSEN = [
        'voll' => '100vw',
        'spalte' => '(min-width: 75rem) 72rem, 100vw',
        'halb' => '(min-width: 75rem) 36rem, (min-width: 60rem) 48vw, 100vw',
        'klein' => '12rem',
    ];

    /** @var array<string, string> Zielpfad im Seitenordner (es/bilder/…) => Quelldatei */
    public array $kopien = [];

    /** @var array<string, string> Zielpfad im Seitenordner (es/groessen/…) => erzeugte Bytes (kleinere Fassungen der Bilder, 3.70.0) */
    public array $erzeugt = [];

    public function __construct(public readonly Profil $profil, public readonly array $meta, private readonly string $seite)
    {
    }

    public function e(string $s): string
    {
        return Text::e($s);
    }

    /** Fließtext in einer Zeile (Listenpunkt): **fett**, Adressen auf die link_hosts des Profils werden Links. Nicht für Überschriften, Knöpfe und Beschriftungen — dafür e(). */
    public function inline(string $s): string
    {
        return Text::inline($s, $this->profil->hosts());
    }

    /** Fließtext in Absätzen: wie inline(), dazu Leerzeile = neuer Absatz. Die einzige Stelle, an der die Hostliste in die Bausteine kommt. */
    public function absaetze(?string $s, string $klasse = ''): string
    {
        return Text::absaetze((string) $s, $klasse, $this->profil->hosts());
    }

    /** Text je nach Anrede des Profils. */
    public function du(string $du, string $sie): string
    {
        return ($this->profil->daten['anrede'] ?? 'du') === 'Sie' ? $sie : $du;
    }

    public function id(int $nr, string $name): string
    {
        return "lp-a$nr-$name";
    }

    /** <section> eines Bausteins mit Klassen, optionalem Anker und Bezug auf seine Überschrift. */
    public function oeffnen(array $b, int $nr): string
    {
        $typ = $b['typ'];
        $anker = isset($b['anker']) ? ' id="' . $this->e($b['anker']) . '"' : '';
        return sprintf('<section class="lp-block lp-%s lp-%s--%s"%s aria-labelledby="%s">', $typ, $typ, $this->e($b['variante']), $anker, $this->id($nr, 'titel'));
    }

    /** Stufe der Titelgröße nach Zeichenzahl (nicht Bytes), Leerraum am Rand abgezogen — wie String.trim in lp.js (auch NBSP, U+3000, BOM). */
    public static function schriftStufe(string $text): int
    {
        $rand = '[\p{Zs}\t-\r\x{2028}\x{2029}\x{FEFF}]+'; // genau der Leerraum, den JS trim() abzieht (nicht auch U+0085 und U+200B)
        $n = mb_strlen(preg_replace("/^$rand|$rand$/u", '', $text) ?? $text);
        return count(array_filter(self::TITEL_STUFEN, static fn (int $grenze): bool => $n > $grenze));
    }

    /** Attribute für einen Titel, dessen Größe der Textmenge folgt: die Stufe jetzt, die Schwellen für lp.js. */
    public function groesse(string $text): string
    {
        return sprintf(' data-lp-groesse="%d" data-lp-stufen="%s"', self::schriftStufe($text), implode(' ', self::TITEL_STUFEN));
    }

    public function titel(int $nr, string $text): string
    {
        return '<h2 class="lp-titel" id="' . $this->id($nr, 'titel') . '">' . $this->e($text) . '</h2>';
    }

    /**
     * <img> mit Maßen aus der Datei. bestand/<name> wird nach es/bilder/ kopiert, img/<name> bleibt, wo es ist.
     * $stumm: das Bild steht in einem beschrifteten Bedienelement und bekommt kein eigenes alt.
     * $groesse (3.70.0): Schlüssel aus GROESSEN — wie breit das Bild hier höchstens steht; ist das Bild breit genug, kommen kleinere
     * Fassungen (Bildgroessen) mit srcset und sizes dazu, das Original bleibt die größte.
     */
    public function bild(array $bild, string $klasse, bool $wichtig = false, bool $stumm = false, string $groesse = 'spalte'): string
    {
        [$src, $alt, $mass, $quelle] = $this->bildDaten($bild);
        if ($stumm || ($bild['dekorativ'] ?? false) === true) {
            $alt = '';
        }
        $laden = $wichtig ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"';
        return sprintf('<img class="%s" src="%s"%s alt="%s" width="%d" height="%d"%s>', $klasse, $this->e($src), $this->srcset($src, $quelle, $mass, $groesse),
            $this->e($alt), $mass[0], $mass[1], $laden);
    }

    /** srcset und sizes eines WebP-Bildes (3.70.0); leer, wenn es keine kleinere Fassung gibt. */
    private function srcset(string $src, string $quelle, array $mass, string $groesse): string
    {
        $sizes = self::GROESSEN[$groesse] ?? throw new \LogicException("unbekannte Bildgröße $groesse");
        if (($mass[2] ?? null) !== IMAGETYPE_WEBP) {
            return '';
        }
        $satz = [];
        foreach (Bildgroessen::breiten((int) $mass[0]) as $breite) {
            $ziel = Bildgroessen::pfad($src, $breite);
            $daten = $this->erzeugt[$ziel] ?? Bildgroessen::erzeugen($quelle, $breite);
            if ($daten !== null) {
                $this->erzeugt[$ziel] = $daten;
                $satz[] = "$ziel {$breite}w";
            }
        }
        if ($satz === []) {
            return '';
        }
        $satz[] = "$src {$mass[0]}w";
        return sprintf(' srcset="%s" sizes="%s"', $this->e(implode(', ', $satz)), $this->e($sizes));
    }

    /**
     * Adresse im Seitenordner, Alt-Text, Maße und Quelldatei eines Bildes des Bauplans; bestand/<name> wird dabei zum Kopieren nach es/bilder/ vorgemerkt.
     *
     * @return array{0: string, 1: string, 2: array, 3: string} [src, alt, getimagesize, Quelldatei]
     */
    private function bildDaten(array $bild): array
    {
        $datei = (string) $bild['datei'];
        $name = substr($datei, strpos($datei, '/') + 1);
        if (str_starts_with($datei, 'bestand/')) {
            $quelle = "$this->seite/profil/bilder/$name";
            $src = "es/bilder/$name";
            $this->kopien[$src] = $quelle;
            $alt = $bild['alt'] ?? (string) ($this->profil->bild($name)['alt'] ?? '');
        } else {
            $quelle = "$this->seite/img/$name";
            $src = "img/$name";
            $alt = (string) ($bild['alt'] ?? '');
        }
        $mass = @getimagesize($quelle) ?: throw new BauFehler("Bildmaße nicht lesbar: $datei");
        return [$src, $alt, $mass, $quelle];
    }

    public function knopf(array $knopf): string
    {
        return '<a class="btn btn-primary lp-knopf" href="' . $this->e((string) $knopf['ziel']) . '">' . $this->e((string) $knopf['text']) . '</a>';
    }

    /**
     * Video mit zwei Klicks: Rahmen (16:9) mit dem Startbild in einem Knopf, dazu der Ersatzlink — der Baustein „video“ und der Kopfbereich
     * mit Video (3.61.0) schreiben ihn hier. Bis zum Klick gibt es keine Anfrage an YouTube; lp.js setzt das iframe erst danach ein.
     */
    public function videoStart(string $kennung, string $titel, array $bild, bool $wichtig = false, string $groesse = 'spalte'): string
    {
        $beschriftung = 'Video abspielen: ' . $titel . ' (lädt Inhalte von YouTube)';
        return '<div class="lp-video-rahmen">' . "\n"
            . sprintf('<button type="button" class="lp-video-start" data-lp-kennung="%s" data-lp-titel="%s" aria-label="%s">', $this->e($kennung), $this->e($titel), $this->e($beschriftung)) . "\n"
            . $this->bild($bild, 'lp-video-bild', $wichtig, true, $groesse) . "\n"
            . "</button>\n</div>\n"
            . '<p class="lp-video-link">' . $this->du('Mit dem Klick auf das Bild lädst du das Video von YouTube. Du kannst es auch', 'Mit dem Klick auf das Bild laden Sie das Video von YouTube. Sie können es auch')
            . ' <a href="https://www.youtube.com/watch?v=' . $this->e($kennung) . '">auf YouTube ansehen</a>.</p>';
    }

    /**
     * Eigenes Video (3.62.0): <video> mit Bedienleiste im 16:9-Rahmen, das Bild ist das Startbild (poster). preload="none": nichts lädt, bis der
     * Besucher startet; nichts startet von selbst (kein autoplay, kein muted, kein loop). Kein Drittanbieter — die Datei liegt im Seitenordner.
     * Die Datei wird hier noch einmal geprüft (Videodatei): ein Bauplan am Prüfer vorbei bekommt nie ein Video, das es nicht geben darf.
     */
    public function videoDatei(string $datei, string $titel, array $poster): string
    {
        if (($f = Videodatei::fehler($this->seite, $datei)) !== null) {
            throw new BauFehler("Videodatei $datei: $f[0]");
        }
        [$start] = $this->bildDaten($poster);
        return '<div class="lp-video-rahmen">' . "\n"
            . sprintf('<video class="lp-video-datei" controls preload="none" playsinline poster="%s" aria-label="%s">', $this->e($start), $this->e('Video: ' . $titel)) . "\n"
            . sprintf('<source src="%s" type="video/mp4">', $this->e($datei)) . "\n"
            . "</video>\n</div>\n"
            . '<p class="lp-video-link">' . $this->du('Lässt sich das Video nicht abspielen? Du kannst es auch', 'Lässt sich das Video nicht abspielen? Sie können es auch')
            . ' <a href="' . $this->e($datei) . '">als Datei öffnen</a>.</p>';
    }

    /**
     * Die Bewegungsdatei eines Bausteins (3.64.0): wird hier noch einmal geprüft (Bewegung), ein Bauplan am Prüfer vorbei bekommt nie eine Datei, die
     * es nicht geben darf. Liefert das Bildverhältnis und die Maße der Leinwand (lange Seite 640 Punkte, so wie lp/bewegung.js sie auch setzt) und
     * merkt den Player zum Kopieren vor (live: dazu das Live-Skript, das erst nach dem Klick geladen wird).
     *
     * @return array{verhaeltnis: float, breite: int, hoehe: int}
     */
    public function bewegungsDatei(string $pfad, bool $live): array
    {
        if (($f = Bewegung::fehler($this->seite, $pfad)) !== null) {
            throw new BauFehler("Bewegungsdatei $pfad: $f[0]");
        }
        $v = (float) json_decode((string) file_get_contents("$this->seite/$pfad"), false, 8, JSON_THROW_ON_ERROR)->verhaeltnis;
        $this->kopien['es/bewegung.js'] = dirname(__DIR__) . '/bewegung.js';
        if ($live) {
            $this->kopien['es/bewegung-live.js'] = dirname(__DIR__) . '/bewegung-live.js';
        }
        return ['verhaeltnis' => $v, 'breite' => (int) round($v >= 1 ? 640 : 640 * $v), 'hoehe' => (int) round($v >= 1 ? 640 / $v : 640)];
    }

    /** JSON-LD, sicher gegen </script> und & im Text. */
    public function jsonLd(array $daten): string
    {
        $json = json_encode(['@context' => 'https://schema.org'] + $daten,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    public function datum(string $zeitpunkt): string
    {
        return Text::datum($zeitpunkt, $this->profil->sprache());
    }

    public function iso(string $zeitpunkt, int $plusMinuten = 0): string
    {
        return Text::iso($zeitpunkt, $plusMinuten);
    }
}
