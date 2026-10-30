<?php
/**
 * Die Bewegungsdatei einer Seite (3.64.0, Format „bewegung v1“, docs/bewegung-format.md): ein JSON mit den Körperpunkten einer Bewegung, kein Kamerabild,
 * kein Gesicht. Im Bauplan steht nur der Pfad bew/<name>.bewegung (Endung .bewegung, weil der Sammelordner *.json mit 404 sperrt); die Datei liegt im
 * Seitenordner. Eine einzige Prüfung für Schema (Bauplan), Kontext (beim Bauen noch einmal) und Prüfer — die Regeln des Inhalts stehen
 * zusätzlich in lp/bewegung.js (gleiche Reihenfolge, gleiche Kennungen; der Test „paritaet“ hält beide gleich). Dazu die Modelldateien des Live-Bausteins.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Bewegung
{
    /** Einziger erlaubter Pfad. \z statt $ — ein Zeilenumbruch am Ende fiele bei $ durch. */
    public const MUSTER = '#^bew/[a-z0-9][a-z0-9-]{0,39}\.bewegung\z#';
    /** Größte erlaubte Datei: 300 000 Byte (rund 10 s bei 30 Bildern je Sekunde). */
    public const MAX_BYTES = 300000;
    public const MAX_DAUER_MS = 10000;
    public const MIN_RAHMEN = 2;
    public const MAX_RAHMEN = 600;
    public const QUELLEN = ['eigene-aufnahme', 'einwilligung', 'synthetisch'];
    /** Die 33 Punkte des MediaPipe-Pose-Modells in dessen Reihenfolge. */
    public const PUNKTE = [
        'nase', 'auge-links-innen', 'auge-links', 'auge-links-aussen', 'auge-rechts-innen', 'auge-rechts', 'auge-rechts-aussen',
        'ohr-links', 'ohr-rechts', 'mund-links', 'mund-rechts', 'schulter-links', 'schulter-rechts', 'ellbogen-links', 'ellbogen-rechts',
        'handgelenk-links', 'handgelenk-rechts', 'kleiner-finger-links', 'kleiner-finger-rechts', 'zeigefinger-links', 'zeigefinger-rechts',
        'daumen-links', 'daumen-rechts', 'huefte-links', 'huefte-rechts', 'knie-links', 'knie-rechts', 'knoechel-links', 'knoechel-rechts',
        'ferse-links', 'ferse-rechts', 'fussspitze-links', 'fussspitze-rechts',
    ];
    /** Augen und Mund werden nie gespeichert (kein Gesichtsmerkmal); Nase und Ohren bleiben als Kopfhaltung. */
    public const GESICHT = [1, 2, 3, 4, 5, 6, 9, 10];
    private const SCHLUESSEL = ['v', 'name', 'quelle', 'verhaeltnis', 'fps', 'dim', 'dauer_ms', 'punkte', 'rahmen'];
    /** Kundentext je Kennung. */
    public const TEXTE = [
        'groesse' => 'Die Bewegungsdatei ist zu groß.', 'json' => 'Die Bewegungsdatei ist kein gültiges JSON.', 'form' => 'Die Bewegungsdatei hat die falsche Form.',
        'schluessel' => 'In der Bewegungsdatei fehlt eine Angabe oder es steht eine unbekannte darin.', 'v' => 'Die Bewegungsdatei hat ein unbekanntes Format.',
        'name' => 'Der Name der Bewegung ist nicht in Ordnung.', 'quelle' => 'Der Ursprung der Bewegung (quelle) fehlt oder ist unbekannt.',
        'verhaeltnis' => 'Das Bildverhältnis der Bewegung ist nicht in Ordnung.', 'fps' => 'Die Bildrate der Bewegung ist nicht in Ordnung.',
        'dim' => 'Die Zahl der Achsen der Bewegung ist nicht in Ordnung.', 'punkte' => 'Die Liste der Körperpunkte stimmt nicht.',
        'rahmen.anzahl' => 'Die Bewegung hat zu wenige oder zu viele Bilder.', 'rahmen.laenge' => 'Ein Bild der Bewegung hat die falsche Zahl an Werten.',
        'wert' => 'Die Bewegung enthält einen Wert, der keine Zahl ist.', 'bereich' => 'Die Bewegung enthält einen Wert außerhalb des erlaubten Bereichs.',
        'stellen' => 'Die Bewegung enthält Werte mit mehr als drei Nachkommastellen.', 'unsichtbar' => 'Ein unsichtbarer Körperpunkt hat Werte ungleich null.',
        'gesicht' => 'Die Bewegung enthält Gesichtspunkte (Augen oder Mund), die nicht gespeichert werden dürfen.', 'dauer' => 'Die Dauer der Bewegung passt nicht zu Bildzahl und Bildrate oder ist zu lang.',
    ];
    /** Name der Prüfsummen-Datei der Modelldateien (sha256sum-Format), einzige Quelle für die erlaubten Dateien. */
    public const SUMMEN = 'scripts/modell-summen.txt';

    /**
     * Die gültig benannten Bewegungspfade eines Bauplans (ohne Doppelte) — damit kopiert das Musterbuch die Probedateien zu den Fixtures.
     *
     * @return list<string>
     */
    public static function imPlan(array $plan): array
    {
        $pfade = [];
        array_walk_recursive($plan, static function ($w) use (&$pfade): void {
            if (is_string($w) && preg_match(self::MUSTER, $w)) {
                $pfade[$w] = true;
            }
        });
        return array_keys($pfade);
    }

    /** @return array{0: string, 1: string}|null [technik, kunde] des ersten Fehlers, null wenn alles passt */
    public static function fehler(string $ordner, string $pfad): ?array
    {
        if (!preg_match(self::MUSTER, $pfad)) {
            return ['Pfad passt nicht zu bew/<name>.bewegung', 'Der Name der Bewegungsdatei ist ungültig.'];
        }
        clearstatcache();
        $datei = "$ordner/$pfad";
        if (is_link("$ordner/bew") || is_link($datei)) {
            return ["$pfad ist ein Link", 'Die Bewegungsdatei ist keine gewöhnliche Datei.'];
        }
        if (!is_file($datei)) {
            return ["$pfad nicht gefunden", 'Die Bewegungsdatei wurde nicht gefunden.'];
        }
        $groesse = (int) filesize($datei);
        $code = $groesse > self::MAX_BYTES ? 'groesse' : null;
        if ($code === null) {
            try {
                $code = self::pruefen(json_decode((string) file_get_contents($datei), false, 8, JSON_THROW_ON_ERROR), $groesse);
            } catch (\JsonException) {
                $code = 'json';
            }
        }
        return $code === null ? null : ["$pfad: $code", self::TEXTE[$code]];
    }

    /**
     * Prüft den Inhalt einer Bewegungsdatei; gibt die Kennung des ersten Fehlers zurück (self::TEXTE) oder null. Die Reihenfolge der Prüfungen ist
     * dieselbe wie in lp/bewegung.js.
     *
     * @param int $bytes Größe der Datei als Text
     */
    public static function pruefen(mixed $d, int $bytes): ?string
    {
        if ($bytes > self::MAX_BYTES) {
            return 'groesse';
        }
        if (!is_object($d)) {
            return 'form';
        }
        $k = array_map('strval', array_keys(get_object_vars($d)));
        if (array_diff($k, self::SCHLUESSEL) !== [] || array_diff(self::SCHLUESSEL, $k) !== []) {
            return 'schluessel';
        }
        if (!self::ganz($d->v) || $d->v != 1) {
            return 'v';
        }
        if (!is_string($d->name) || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .,:()+_\/–\-]{0,59}\z/u', $d->name)) {
            return 'name';
        }
        if (!in_array($d->quelle, self::QUELLEN, true)) {
            return 'quelle';
        }
        if (!self::zahl($d->verhaeltnis) || $d->verhaeltnis < 0.25 || $d->verhaeltnis > 4 || !self::stellen($d->verhaeltnis, 4)) {
            return 'verhaeltnis';
        }
        if (!self::ganz($d->fps) || $d->fps < 10 || $d->fps > 60) {
            return 'fps';
        }
        if (!self::ganz($d->dim) || ($d->dim != 2 && $d->dim != 3)) {
            return 'dim';
        }
        if ($d->punkte !== self::PUNKTE) {
            return 'punkte';
        }
        $r = $d->rahmen;
        if (!is_array($r) || count($r) < self::MIN_RAHMEN || count($r) > self::MAX_RAHMEN) {
            return 'rahmen.anzahl';
        }
        $je = (int) $d->dim + 1;
        foreach ($r as $bild) {
            if (!is_array($bild) || count($bild) !== count(self::PUNKTE) * $je) {
                return 'rahmen.laenge';
            }
            foreach (self::PUNKTE as $i => $_) {
                $f = self::punkt(array_slice($bild, $i * $je, $je), $je === 4, in_array($i, self::GESICHT, true));
                if ($f !== null) {
                    return $f;
                }
            }
        }
        $dauer = (int) round(count($r) * 1000 / $d->fps);
        if (!self::ganz($d->dauer_ms) || $d->dauer_ms != $dauer || $dauer > self::MAX_DAUER_MS) {
            return 'dauer';
        }
        return null;
    }

    /** Ein Punkt: x, y, (z,) Sichtbarkeit. @param list<mixed> $w */
    private static function punkt(array $w, bool $mitZ, bool $gesicht): ?string
    {
        foreach ($w as $j => $v) {
            if (!self::zahl($v)) {
                return 'wert';
            }
            $z = $mitZ && $j === 2;
            if ($v < ($z ? -1 : 0) || $v > 1) {
                return 'bereich';
            }
            if (!self::stellen($v, 3)) {
                return 'stellen';
            }
        }
        $s = $w[count($w) - 1];
        if ($s == 0 && array_filter($w, static fn ($v) => $v != 0) !== []) {
            return 'unsichtbar';
        }
        return $gesicht && $s != 0 ? 'gesicht' : null;
    }

    private static function zahl(mixed $v): bool
    {
        return (is_int($v) || is_float($v)) && is_finite((float) $v);
    }

    private static function ganz(mixed $v): bool
    {
        return self::zahl($v) && floor((float) $v) == $v;
    }

    /** Höchstens $n Nachkommastellen (das Bildverhältnis darf vier haben: 9:16 = 0,5625, alle Messwerte drei). */
    private static function stellen(int|float $v, int $n): bool
    {
        $m = 10 ** $n;
        return abs(round($v * $m) - $v * $m) < 1e-6;
    }

    /**
     * Die Modelldateien des Bausteins „live“ im Seitenordner modell/ (kommen erst beim Veröffentlichen dazu, docs/bewegung-format.md): nur
     * Dateien aus scripts/modell-summen.txt, jede mit ihrer Prüfsumme. Gibt die Befunde als [technik, kunde] zurück.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function modell(string $ordner, string $endo): array
    {
        $soll = [];
        foreach (explode("\n", trim((string) file_get_contents("$endo/" . self::SUMMEN))) as $zeile) {
            if (preg_match('/^([0-9a-f]{64})  (\S+)$/', $zeile, $m)) {
                $soll[$m[2]] = $m[1];
            }
        }
        $kunde = 'Die Modelldateien der Kamera-Vorführung sind nicht in Ordnung — bitte mit scripts/modell-holen.sh neu holen.';
        $f = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$ordner/modell", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $datei) {
            $pfad = substr($datei->getPathname(), strlen($ordner) + 1);
            $name = substr($pfad, 7);
            if (!isset($soll[$name]) || is_link($datei->getPathname()) || !$datei->isFile()) {
                $f[] = ["$pfad gehört nicht zu den Modelldateien (scripts/modell-summen.txt)", $kunde];
            } elseif (hash_file('sha256', $datei->getPathname()) !== $soll[$name]) {
                $f[] = ["$pfad hat eine falsche Prüfsumme", $kunde];
            }
        }
        return $f;
    }
}
