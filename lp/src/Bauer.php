<?php
/**
 * Baut eine Landingpage aus ihrem Ordner: seite.json + ENDOSKELETON + profil/ → index.html, impressum.html,
 * datenschutz.html, robots.txt, es/. Ohne Netz, KI, Uhr und Zufall: zweimal bauen = dieselben Bytes.
 * Geschrieben wird nur, wenn es keine sperrenden Befunde gibt.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Bauer
{
    public static function bauen(string $seite, bool $schreiben = true, ?string $endo = null): Ergebnis
    {
        $endo ??= dirname(__DIR__, 2);
        $version = trim((string) file_get_contents("$endo/VERSION"));
        $marke = is_file("$seite/ENDOSKELETON") ? trim((string) file_get_contents("$seite/ENDOSKELETON")) : '';
        if (!preg_match('/^\d+\.\d+\.\d+$/', $marke) || $marke !== $version) {
            throw new BauFehler("ENDOSKELETON der Seite ist „{$marke}“, dieser Stand ist $version — mit /home/pawbot/projects/endoskeleton-versionen/<Marke>/lp/bauen.php bauen");
        }
        if (!is_dir("$seite/profil")) {
            throw new BauFehler("$seite/profil fehlt — zuerst lp/profil-uebernehmen.php ausführen");
        }
        try {
            $profil = Profil::laden("$seite/profil");
        } catch (\RuntimeException $e) {
            throw new BauFehler($e->getMessage(), 0, $e);
        }
        try {
            $plan = json_decode((string) @file_get_contents("$seite/seite.json"), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new Ergebnis([Befund::sperrend('seite.json', 'seite.json ist kein gültiges JSON: ' . $e->getMessage(),
                'Der Bauplan der Seite ist beschädigt und kann nicht gelesen werden.')]);
        }
        $katalog = Katalog::laden("$endo/lp/bausteine.json");
        $befunde = array_merge($profil->pruefen(), Schema::pruefen($plan, $katalog, $profil, $seite));
        if (Befund::sperrt($befunde)) {
            return new Ergebnis($befunde);
        }
        $befunde = array_merge($befunde, self::kontrast($katalog, $profil));
        if (Befund::sperrt($befunde)) {
            return new Ergebnis($befunde);
        }

        $meta = $plan['meta'];
        $k = new Kontext($profil, $meta, $seite);
        $bloecke = [];
        foreach ($plan['bausteine'] as $i => $b) {
            $b['variante'] ??= $katalog->baustein($b['typ'])['varianten'][0];
            $bloecke[] = self::vorlage("$endo/lp/bausteine/{$b['typ']}.php", ['b' => $b, 'k' => $k, 'nr' => $i + 1]);
        }

        $dateien = [];
        $logo = (string) $profil->daten['logo']['datei'];
        $dateien['es/' . $logo] = (string) file_get_contents("$seite/profil/$logo");
        if (is_dir("$seite/profil/schrift")) {
            foreach (Dateien::eintraege("$seite/profil/schrift") as $s) {
                if (str_ends_with($s, '.woff2') && is_file("$seite/profil/schrift/$s") && !is_link("$seite/profil/schrift/$s")) {
                    $dateien["es/schrift/$s"] = (string) file_get_contents("$seite/profil/schrift/$s");
                }
            }
        }
        foreach ($k->kopien as $ziel => $quelle) {
            $dateien[$ziel] = (string) file_get_contents($quelle);
        }
        $vorschau = Vorschau::bauen($plan, $profil, $seite, $katalog);
        $befunde = array_merge($befunde, $vorschau['befunde']);
        $dateien += $vorschau['dateien'];
        $dateien['es/lp.css'] = self::css($endo, $version, $profil);
        $dateien['es/lp.js'] = (string) file_get_contents("$endo/lp/lp.js");
        $rahmen = [
            'k' => $k, 'version' => $version, 'logo' => "es/$logo",
            'css' => 'es/lp.css?v=' . substr(hash('sha256', $dateien['es/lp.css']), 0, 10),
            'js' => 'es/lp.js?v=' . substr(hash('sha256', $dateien['es/lp.js']), 0, 10),
        ];
        $indexieren = ($meta['indexieren'] ?? false) === true;
        $dateien['index.html'] = self::vorlage("$endo/lp/rahmen.php", $rahmen + [
            'titel' => $meta['titel'], 'beschreibung' => $meta['beschreibung'], 'noindex' => !$indexieren, 'og' => $vorschau['og'],
            'inhalt' => implode("\n", $bloecke),
        ]);
        $firma = (string) $profil->daten['firma']['name'];
        foreach (['impressum' => 'Impressum', 'datenschutz' => 'Datenschutzerklärung'] as $name => $ueberschrift) {
            $dateien["$name.html"] = self::vorlage("$endo/lp/rahmen.php", $rahmen + [
                'titel' => "$ueberschrift – $firma", 'beschreibung' => "$ueberschrift von $firma", 'noindex' => true, 'og' => null,
                'inhalt' => "<section class=\"lp-block lp-rechtsseite\" aria-labelledby=\"lp-recht-titel\">\n<div class=\"lp-block-innen container\">\n"
                    . "<h1 id=\"lp-recht-titel\">$ueberschrift</h1>\n" . Rechtstext::html($profil->rechtstext($name), $profil->hosts())
                    . "\n<p><a href=\"index.html\">Zurück zur Seite</a></p>\n</div>\n</section>",
            ]);
        }
        $dateien['robots.txt'] = $indexieren ? "User-agent: *\nAllow: /\n" : "User-agent: *\nDisallow: /\n";
        ksort($dateien, SORT_STRING);

        if ($schreiben) {
            Dateien::loeschen("$seite/es");
            foreach ($dateien as $pfad => $inhalt) {
                Dateien::schreiben("$seite/$pfad", $inhalt);
            }
        }
        return new Ergebnis($befunde, $dateien);
    }

    /**
     * Kontrast als Matrix: jede Schriftfarbe auf jeder Fläche, Knopfschrift auch auf der Hover-Farbe, Fokusrahmen
     * auf jeder Fläche (lp/bausteine.json „rahmen.farbpaare“). Text 4,5:1, Fokusrahmen 3:1 — auch für kleine,
     * fette Schrift gilt 4,5:1 (Beschriftungen, Termindatum auf dem Handy, **fett** im Fließtext).
     * @return list<Befund>
     */
    public static function kontrast(Katalog $katalog, Profil $profil): array
    {
        return Kontrast::pruefe($profil->tokens, $katalog->rahmen['farbpaare'], 'profil.css', 'Kundenprofil');
    }

    private static function css(string $endo, string $version, Profil $profil): string
    {
        $p = (string) Kontrast::hex($profil->tokens['--primary']);
        $rgb = implode(', ', array_map('hexdec', str_split(substr($p, 1), 2)));
        return "/* SSI Endoskeleton $version — Landingpage: base.css + Profil + lp.css */\n"
            . file_get_contents("$endo/base.css") . "\n"
            . $profil->css . "\n"
            . ":root {\n  --primary-rgb: $rgb;\n}\n\n"
            . file_get_contents("$endo/lp/lp.css");
    }

    /** Führt eine Vorlage mit eigenen Variablen aus und gibt ihre Ausgabe zurück. */
    private static function vorlage(string $datei, array $vars): string
    {
        ob_start();
        try {
            (static function (string $__datei, array $__vars): void {
                extract($__vars);
                require $__datei;
            })($datei, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
