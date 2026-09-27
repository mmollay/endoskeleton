<?php
/**
 * Prüft einen Bauplan (seite.json) gegen den Katalog und das Profil der Seite. Jeder Befund nennt den technischen
 * Feldpfad und einen Kundentext mit Abschnittsnummer und -namen.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Schema
{
    private const OBEN = ['format', 'meta', 'bausteine'];
    private const BILD = ['datei', 'alt', 'dekorativ'];
    private const KNOPF = ['text', 'ziel'];
    private const ANKER = '/^[a-z][a-z0-9-]{0,39}$/';
    /** Vom Rahmen vergebene ids: der Hauptinhalt und alles mit lp- (Überschriften, Formularfelder). */
    private const ANKER_VERGEBEN = '/^(inhalt$|lp-)/';
    private const BILDNAME = '[a-z0-9][a-z0-9_-]*(\.[a-z0-9_-]+)*';

    /** @var list<Befund> */
    private array $befunde = [];
    /** @var list<array{0: string, 1: string, 2: string}> Knopf-Ziele: [anker, pfad, wo] */
    private array $ziele = [];

    private function __construct(private readonly Katalog $k, private readonly Profil $p, private readonly string $ordner)
    {
    }

    /** @return list<Befund> */
    public static function pruefen(mixed $seite, Katalog $k, Profil $p, string $ordner): array
    {
        $s = new self($k, $p, $ordner);
        $s->seite($seite);
        return $s->befunde;
    }

    private function seite(mixed $seite): void
    {
        if (!is_array($seite) || ($seite !== [] && array_is_list($seite))) {
            $this->fehler('', 'seite.json ist kein JSON-Objekt', 'Der Bauplan hat die falsche Form.');
            return;
        }
        foreach (array_diff(array_keys($seite), self::OBEN) as $s) {
            $this->fehler((string) $s, "unbekannter Schlüssel $s", "Die Angabe „{$s}“ ist unbekannt.");
        }
        if (($seite['format'] ?? null) !== 1) {
            $this->fehler('format', 'format muss 1 sein', 'Der Bauplan hat ein unbekanntes Format.');
        }
        $meta = $seite['meta'] ?? null;
        if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) {
            $this->fehler('meta', 'meta fehlt oder ist kein Objekt', 'Die Seiteneinstellungen (Titel, Beschreibung) fehlen.');
        } else {
            $this->objekt($meta, $this->k->seite['felder'], 'meta', 'Seiteneinstellungen', null);
        }
        $bausteine = $seite['bausteine'] ?? null;
        if (!is_array($bausteine) || !array_is_list($bausteine) || $bausteine === []) {
            $this->fehler('bausteine', 'bausteine fehlt oder ist leer', 'Die Seite hat keine Abschnitte.');
            return;
        }
        $anker = [];
        $zahl = [];
        foreach ($bausteine as $i => $b) {
            $pfad = "bausteine.$i";
            $nr = $i + 1;
            if (!is_array($b) || ($b !== [] && array_is_list($b))) {
                $this->fehler($pfad, "$pfad ist kein Objekt", "Abschnitt $nr hat die falsche Form.");
                continue;
            }
            $typ = $b['typ'] ?? null;
            $e = is_string($typ) ? $this->k->baustein($typ) : null;
            if ($e === null) {
                $this->fehler("$pfad.typ", 'unbekannter Baustein ' . json_encode($typ, JSON_UNESCAPED_UNICODE), "Abschnitt $nr: Diesen Bausteintyp gibt es nicht.");
                continue;
            }
            $wo = "Abschnitt $nr „{$e['name']}“";
            $zahl[$typ] = ($zahl[$typ] ?? 0) + 1;
            $variante = $b['variante'] ?? $e['varianten'][0];
            if (!is_string($variante) || !in_array($variante, $e['varianten'], true)) {
                $this->fehler("$pfad.variante", "unbekannte Variante " . json_encode($variante, JSON_UNESCAPED_UNICODE) . " für $typ",
                    "$wo: Diese Variante gibt es nicht (möglich: " . implode(', ', $e['varianten']) . ').');
                $variante = null;
            }
            if (($e['regeln']['zuerst'] ?? false) && $i !== 0) {
                $this->fehler($pfad, "$typ muss zuerst stehen", "$wo muss der erste Abschnitt der Seite sein.");
            }
            $felder = $e['felder'] + ['anker' => ['typ' => 'anker', 'label' => 'Sprungmarke']];
            $rest = $b;
            unset($rest['typ'], $rest['variante']);
            $this->objekt($rest, $felder, $pfad, $wo, $variante);
            if (isset($b['anker']) && is_string($b['anker'])) {
                if (isset($anker[$b['anker']])) {
                    $this->fehler("$pfad.anker", "Anker {$b['anker']} doppelt", "$wo: Die Sprungmarke „{$b['anker']}“ gibt es schon.");
                }
                $anker[$b['anker']] = true;
            }
        }
        foreach ($this->k->bausteine as $typ => $e) {
            $n = $zahl[$typ] ?? 0;
            if (($e['regeln']['genau_einmal'] ?? false) && $n !== 1) {
                $this->fehler('bausteine', "$typ genau einmal erwartet, $n gefunden", "Die Seite braucht genau einen Abschnitt „{$e['name']}“ (gefunden: $n).");
            }
            $max = $e['regeln']['hoechstens'] ?? null;
            if ($max !== null && $n > $max) {
                $this->fehler('bausteine', "$typ höchstens $max, $n gefunden", "Den Abschnitt „{$e['name']}“ darf es höchstens {$max}-mal geben (gefunden: $n).");
            }
        }
        foreach ($this->ziele as [$ziel, $pfad, $wo]) {
            if (!isset($anker[$ziel])) {
                $this->fehler($pfad, "Anker #$ziel gibt es nicht", "$wo: Der Knopf führt zu einer Stelle, die es auf der Seite nicht gibt (#$ziel).");
            }
        }
    }

    /** Prüft die Felder eines Objekts gegen ihre Definitionen; unbekannte Schlüssel sind sperrend. */
    private function objekt(array $werte, array $felder, string $pfad, string $wo, ?string $variante): void
    {
        foreach (array_diff(array_keys($werte), array_keys($felder)) as $s) {
            $this->fehler("$pfad.$s", "unbekanntes Feld $s", "$wo: Das Feld „{$s}“ gibt es hier nicht.");
        }
        foreach ($felder as $name => $def) {
            $pflicht = ($def['pflicht'] ?? false) || ($variante !== null && in_array($variante, $def['pflicht_bei'] ?? [], true));
            if (!array_key_exists($name, $werte)) {
                if ($pflicht) {
                    $this->fehler("$pfad.$name", "Pflichtfeld $name fehlt", "$wo: „{$def['label']}“ fehlt.");
                }
                continue;
            }
            $this->feld($werte[$name], $def, "$pfad.$name", $wo, $pflicht);
        }
    }

    private function feld(mixed $w, array $def, string $pfad, string $wo, bool $pflicht): void
    {
        $label = "„{$def['label']}“";
        $form = fn (string $erwartet) => $this->fehler($pfad, "$pfad: erwartet $erwartet, bekommen " . get_debug_type($w), "$wo: $label hat die falsche Form (erwartet: $erwartet).");
        switch ($def['typ']) {
            case 'zeile':
            case 'absatz':
                if (!is_string($w)) {
                    $form('Text');
                    return;
                }
                if ($def['typ'] === 'zeile' && preg_match('/[\r\n]/', $w)) {
                    $this->fehler($pfad, "$pfad: Zeilenumbruch in einzeiligem Feld", "$wo: $label muss in eine Zeile passen.");
                }
                if ($pflicht && trim($w) === '') {
                    $this->fehler($pfad, "$pfad ist leer", "$wo: $label ist leer.");
                }
                $n = mb_strlen($w);
                if (isset($def['max']) && $n > $def['max']) {
                    $this->fehler($pfad, "$pfad: $n Zeichen > {$def['max']}", "$wo: $label ist $n Zeichen lang, erlaubt sind {$def['max']}.");
                }
                return;
            case 'zahl':
                if (!is_int($w)) {
                    $form('ganze Zahl');
                } elseif ($w < ($def['min'] ?? PHP_INT_MIN) || $w > ($def['max'] ?? PHP_INT_MAX)) {
                    $this->fehler($pfad, "$pfad: $w außerhalb {$def['min']}–{$def['max']}", "$wo: $label muss zwischen {$def['min']} und {$def['max']} liegen.");
                }
                return;
            case 'janein':
                if (!is_bool($w)) {
                    $form('ja/nein (true/false)');
                }
                return;
            case 'zeitpunkt':
                if (!is_string($w) || !Text::zeitpunktGueltig($w)) {
                    $this->fehler($pfad, "$pfad: kein Zeitpunkt JJJJ-MM-TTTHH:MM", "$wo: $label ist kein gültiges Datum mit Uhrzeit.");
                }
                return;
            case 'auswahl':
                if ($def['mehrfach'] ?? false) {
                    if (!is_array($w) || !array_is_list($w) || $w === [] || array_filter($w, static fn ($x) => !is_string($x))) {
                        $form('Liste von Auswahlwerten');
                        return;
                    }
                    if (count(array_unique($w)) !== count($w)) {
                        $this->fehler($pfad, "$pfad: Wert doppelt", "$wo: In $label steht ein Wert doppelt.");
                    }
                    foreach (array_diff($w, $def['werte']) as $x) {
                        $this->fehler($pfad, "$pfad: unbekannter Wert $x", "$wo: „{$x}“ ist in $label nicht möglich (möglich: " . implode(', ', $def['werte']) . ').');
                    }
                    foreach (array_diff($def['enthaelt'] ?? [], $w) as $x) {
                        $this->fehler($pfad, "$pfad: $x fehlt", "$wo: $label muss „{$x}“ enthalten.");
                    }
                } elseif (!is_string($w) || !in_array($w, $def['werte'], true)) {
                    $this->fehler($pfad, "$pfad: Wert nicht in " . implode('|', $def['werte']), "$wo: $label muss eines davon sein: " . implode(', ', $def['werte']) . '.');
                }
                return;
            case 'url':
                if (!is_string($w) || !Text::https($w, $this->p->hosts())) {
                    $this->fehler($pfad, "$pfad: nur https auf erlaubte Hosts (" . implode(', ', $this->p->hosts()) . ')', "$wo: $label muss eine sichere Adresse (https) auf einer freigegebenen Website sein.");
                }
                return;
            case 'anker':
                if (!is_string($w) || !preg_match(self::ANKER, $w)) {
                    $this->fehler($pfad, "$pfad: Anker [a-z][a-z0-9-]*", "$wo: $label darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.");
                } elseif (preg_match(self::ANKER_VERGEBEN, $w)) {
                    $this->fehler($pfad, "$pfad: Anker „{$w}“ ist vergeben (inhalt, lp-…)", "$wo: Die Sprungmarke „{$w}“ ist schon vergeben — bitte einen anderen Namen wählen.");
                }
                return;
            case 'kennung':
                if (!is_string($w) || !preg_match('~' . $def['muster'] . '~', $w)) {
                    $this->fehler($pfad, "$pfad: passt nicht zu {$def['muster']}", "$wo: $label hat die falsche Form.");
                }
                return;
            case 'bilddatei':
                if (!is_string($w)) {
                    $form('Bilddatei');
                    return;
                }
                $this->bilddatei($w, $pfad, $wo, $label);
                return;
            case 'bild':
                if (!is_array($w) || ($w !== [] && array_is_list($w))) {
                    $form('Bild {datei, alt, dekorativ}');
                    return;
                }
                foreach (array_diff(array_keys($w), self::BILD) as $s) {
                    $this->fehler("$pfad.$s", "unbekannter Schlüssel $s im Bild", "$wo: Beim Bild ist „{$s}“ unbekannt.");
                }
                if (!is_string($w['datei'] ?? null)) {
                    $this->fehler("$pfad.datei", "$pfad.datei fehlt", "$wo: Beim Bild fehlt die Datei.");
                    return;
                }
                $dekorativ = $w['dekorativ'] ?? false;
                if (!is_bool($dekorativ)) {
                    $this->fehler("$pfad.dekorativ", "$pfad.dekorativ muss true/false sein", "$wo: Beim Bild hat „dekorativ“ die falsche Form.");
                }
                if (isset($w['alt']) && (!is_string($w['alt']) || mb_strlen($w['alt']) > 200)) {
                    $this->fehler("$pfad.alt", "$pfad.alt muss Text ≤ 200 sein", "$wo: Die Bildbeschreibung ist zu lang oder hat die falsche Form.");
                }
                $art = $this->bilddatei($w['datei'], $pfad, $wo, $label);
                $ohneAlt = trim((string) ($w['alt'] ?? '')) === '';
                if ($art !== null && $dekorativ !== true && $ohneAlt && ($art === 'img' || array_key_exists('alt', $w))) {
                    $this->fehler("$pfad.alt", "$pfad.alt fehlt oder ist leer (ohne dekorativ)", "$wo: Zum Bild fehlt die Beschreibung für Menschen, die es nicht sehen (Alternativtext).");
                }
                return;
            case 'knopf':
                if (!is_array($w) || ($w !== [] && array_is_list($w))) {
                    $form('Knopf {text, ziel}');
                    return;
                }
                $this->objekt($w, ['text' => ['typ' => 'zeile', 'label' => 'Knopftext', 'max' => 40, 'pflicht' => true],
                    'ziel' => ['typ' => 'zeile', 'label' => 'Knopfziel', 'max' => 300, 'pflicht' => true]], $pfad, $wo, null);
                $ziel = $w['ziel'] ?? null;
                if (is_string($ziel) && str_starts_with($ziel, '#') && preg_match(self::ANKER, substr($ziel, 1))) {
                    $this->ziele[] = [substr($ziel, 1), "$pfad.ziel", $wo];
                } elseif (is_string($ziel) && ($def['nur_anker'] ?? false)) {
                    $this->fehler("$pfad.ziel", "$pfad.ziel: nur #anker erlaubt", "$wo: Der Knopf muss zu einer Stelle auf dieser Seite führen (z. B. zur Anmeldung).");
                } elseif (is_string($ziel) && !Text::https($ziel, $this->p->hosts())) {
                    $this->fehler("$pfad.ziel", "$pfad.ziel: #anker oder https auf erlaubte Hosts", "$wo: Der Knopf muss zu einer Stelle der Seite oder zu einer freigegebenen Website (https) führen.");
                }
                return;
            case 'liste':
                if (!is_array($w) || !array_is_list($w)) {
                    $form('Liste');
                    return;
                }
                $n = count($w);
                if ($n < ($def['min'] ?? 0) || $n > ($def['max'] ?? PHP_INT_MAX)) {
                    $this->fehler($pfad, "$pfad: $n Einträge, erlaubt {$def['min']}–{$def['max']}", "$wo: $label braucht {$def['min']} bis {$def['max']} Einträge (jetzt: $n).");
                }
                $einzeln = is_string($def['eintrag']['typ'] ?? null);
                foreach ($w as $i => $x) {
                    $woE = "$wo, Eintrag " . ($i + 1);
                    if ($einzeln) {
                        $this->feld($x, $def['eintrag'], "$pfad.$i", $woE, true);
                    } elseif (!is_array($x) || ($x !== [] && array_is_list($x))) {
                        $this->fehler("$pfad.$i", "$pfad.$i ist kein Objekt", "$woE hat die falsche Form.");
                    } else {
                        $this->objekt($x, $def['eintrag'], "$pfad.$i", $woE, null);
                    }
                }
                return;
        }
        throw new \LogicException("unbekannter Feldtyp {$def['typ']}");
    }

    /** Prüft bestand/<datei> bzw. img/<datei>; gibt 'bestand', 'img' oder null (Fehler) zurück. */
    private function bilddatei(string $datei, string $pfad, string $wo, string $label): ?string
    {
        if (preg_match('#^bestand/(' . self::BILDNAME . ')$#', $datei, $m) && $this->p->bild($m[1]) !== null) {
            return 'bestand';   // Form und Inhalt prüft das Profil
        }
        if (preg_match('#^img/(' . self::BILDNAME . '\.(webp|jpg|jpeg|png))$#', $datei, $m) && is_file("$this->ordner/img/$m[1]")) {
            if (!in_array(@getimagesize("$this->ordner/img/$m[1]")[2] ?? null, [IMAGETYPE_WEBP, IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                $this->fehler($pfad, "$pfad: $datei ist kein lesbares Bild (WebP, PNG, JPEG)", "$wo: Die Datei „{$datei}“ ist kein Bild oder beschädigt.");
                return null;
            }
            return 'img';
        }
        $this->fehler($pfad, "$pfad: Bild $datei nicht gefunden (bestand/<name> aus dem Profil oder img/<name> im Seitenordner)",
            "$wo: Das Bild „{$datei}“ gibt es nicht.");
        return null;
    }

    private function fehler(string $pfad, string $technik, string $kunde): void
    {
        $this->befunde[] = Befund::sperrend($pfad, $technik, $kunde);
    }
}
