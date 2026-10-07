<?php
/**
 * Erzeugt den Skill (lp/SKILL.md, für Claude und Entwickler) und die Werkstatt-Regeln (lp/werkstatt.md, für Teil 2b)
 * aus dem Katalog — so können Beschreibung und Katalog nicht auseinanderlaufen.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Skill
{
    public static function skill(Katalog $k, string $kopf): string
    {
        $s = rtrim($kopf) . "\n\n## Seiteneinstellungen (`meta`)\n\n" . self::tabelle($k->seite['felder'], null)
            . "\n## Bausteine (Katalog {$k->version})\n\n"
            . "_Erzeugt aus `lp/bausteine.json` mit `php ci/skill-bauen.php` — nicht von Hand ändern._\n\n"
            . "Jeder Baustein kennt zusätzlich `typ` (Pflicht), `variante` (ohne Angabe die erste) und `anker` (Sprungmarke `[a-z][a-z0-9-]*`, Ziel für Knöpfe `#anker`).\n";
        foreach ($k->bausteine as $typ => $e) {
            $s .= "\n### `$typ` — {$e['name']}\n\n"
                . 'Varianten: ' . implode(', ', array_map(static fn ($v) => "`$v`", $e['varianten']))
                . " · seit {$e['seit']}, geändert {$e['geaendert']}"
                . (($r = self::regeln($e['regeln'])) !== '' ? " · $r" : '') . "\n\n"
                . self::tabelle($e['felder'], $e['varianten'])
                . "\n```json\n" . json_encode($e['beispiel'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n```\n";
        }
        return $s;
    }

    public static function werkstatt(Katalog $k): string
    {
        $s = "# Landingpage bearbeiten (Endoskeleton {$k->version})\n\n"
            . "_Erzeugt aus `lp/bausteine.json` mit `php ci/skill-bauen.php` — nicht von Hand ändern._\n\n"
            . "- Du änderst ausschließlich `seite.json`, den Bauplan der Seite. HTML, CSS und das Profil fasst du nie an.\n"
            . "- Die Seite besteht aus Abschnitten (Bausteinen) in fester Form. Aussehen, Farben und Schriften kommen aus dem Profil des Kunden.\n"
            . "- Der Kopfbereich (`hero`) steht genau einmal und ganz oben. Anmeldung und Häufige Fragen gibt es höchstens einmal.\n"
            . "- Links nur in Knöpfen und Quellen, nur `https://` auf die freigegebenen Websites des Kunden oder `#anker` auf der Seite.\n"
            . "- Texte ohne HTML. Leerzeile = neuer Absatz, `**fett**` ist die einzige Auszeichnung. Echte Umlaute (ä, ö, ü, ß).\n"
            . "- Keine Heil- oder Wirkversprechen; die Sperrliste des Kunden wird geprüft, der Kunde gibt die Seite frei.\n"
            . "- Bilder: `bestand/<datei>` aus dem Bildbestand des Kunden oder `img/<datei>` im Seitenordner (dann mit `alt`, leer nur mit `\"dekorativ\": true`).\n"
            . "- Nach jeder Änderung bauen und prüfen; Befunde nennen den Abschnitt mit Nummer und Namen.\n\n## Abschnitte\n";
        foreach ($k->bausteine as $typ => $e) {
            $s .= "\n### {$e['name']} (`$typ`)\n\nVarianten: " . implode(', ', array_map(static fn ($v) => "`$v`", $e['varianten']))
                . (($r = self::regeln($e['regeln'])) !== '' ? " · $r" : '') . "\n\n" . self::tabelle($e['felder'], $e['varianten'])
                . "\n```json\n" . json_encode($e['beispiel'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n```\n";
        }
        return $s;
    }

    private static function tabelle(array $felder, ?array $varianten): string
    {
        $z = "| Feld | Typ | Pflicht | Grenzen |\n|---|---|---|---|\n";
        foreach ($felder as $name => $f) {
            $z .= self::zeile("`$name`", $f);
            if ($f['typ'] === 'liste' && !is_string($f['eintrag']['typ'] ?? null)) {
                foreach ($f['eintrag'] as $n => $g) {
                    $z .= self::zeile("`{$name}[].$n`", $g);
                }
            }
        }
        return $z;
    }

    private static function zeile(string $name, array $f): string
    {
        $pflicht = ($f['pflicht'] ?? false) ? 'ja' : (isset($f['pflicht_bei']) ? 'bei ' . implode(', ', $f['pflicht_bei']) : '–');
        return "| $name | {$f['typ']} | $pflicht | " . self::grenzen($f) . (isset($f['fehlt']) ? "; fehlt: {$f['fehlt']}" : '') . " |\n";
    }

    private static function grenzen(array $f): string
    {
        return match ($f['typ']) {
            'zeile' => "eine Zeile, ≤ {$f['max']} Zeichen",
            'absatz' => "≤ {$f['max']} Zeichen, Leerzeile = Absatz, `**fett**`",
            'zahl' => "{$f['min']}–{$f['max']}",
            'zeitpunkt' => '`JJJJ-MM-TTTHH:MM`, Wiener Zeit',
            'auswahl' => (($f['mehrfach'] ?? false) ? 'Liste aus ' : 'eines von ') . implode(', ', array_map(static fn ($w) => "`$w`", $f['werte']))
                . (isset($f['enthaelt']) ? ', muss ' . implode(', ', array_map(static fn ($w) => "`$w`", $f['enthaelt'])) . ' enthalten' : ''),
            'url' => '`https://` auf erlaubte Hosts des Profils',
            'anker' => '`[a-z][a-z0-9-]*`',
            'kennung' => '`' . $f['muster'] . '`',
            'bild' => '`{"datei": "bestand/…" | "img/…", "alt": …, "dekorativ": true}`',
            'bilddatei' => '`bestand/…` oder `img/…`',
            'knopf' => ($f['nur_anker'] ?? false) ? '`{"text": ≤ 40 Zeichen, "ziel": "#anker"}` (nur Sprungmarken der Seite)' : '`{"text": ≤ 40 Zeichen, "ziel": "#anker" | "https://…"}`',
            'liste' => "{$f['min']}–{$f['max']} Einträge" . (is_string($f['eintrag']['typ'] ?? null) ? ' (' . self::grenzen($f['eintrag']) . ')' : ''),
            'janein' => '`true` / `false`',
        };
    }

    private static function regeln(array $r): string
    {
        $t = [];
        if ($r['genau_einmal'] ?? false) { $t[] = 'genau einmal'; }
        if ($r['zuerst'] ?? false) { $t[] = 'als erster Abschnitt'; }
        if (isset($r['hoechstens'])) { $t[] = "höchstens {$r['hoechstens']}-mal"; }
        return implode(', ', $t);
    }
}
