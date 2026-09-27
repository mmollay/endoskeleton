<?php
/** Form eines Kundenprofils (profil.json, profil.css, Bilder, Rechtstexte) und das Übernehmen in eine Seite. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Profil;
use Endo\Lp\ProfilKopie;

$mitCss = static function (string $css): array {
    $d = H::profilKopie();
    file_put_contents("$d/profil.css", $css);
    return Profil::laden($d)->pruefen();
};
$mitJson = static function (callable $aendern): array {
    $d = H::profilKopie();
    $j = json_decode((string) file_get_contents("$d/profil.json"), true);
    file_put_contents("$d/profil.json", json_encode($aendern($j), JSON_UNESCAPED_UNICODE));
    return Profil::laden($d)->pruefen();
};
$testCss = (string) file_get_contents(H::testprofil() . '/profil.css');

return [
    'das Testprofil ist gültig' => static function (): void {
        $p = Profil::laden(H::testprofil());
        T::eq(H::texte($p->pruefen()), '', 'keine Befunde');
        T::eq($p->tokens['--primary'] ?? '', '#1d5c8c', 'Token gelesen');
        T::eq($p->bild('beispiel-vortrag.webp')['alt'] ?? '', 'Stilisierte Bühne mit Rednerpult und Publikum', 'Bild mit Alt-Text');
        T::eq($p->bild('fehlt.webp'), null, 'unbekanntes Bild');
        T::eq($p->hosts(), ['www.example.com', 'beispiel.invalid'], 'erlaubte Hosts');
    },
    'fehlendes Pflicht-Token' => static function () use ($mitCss, $testCss): void {
        $b = H::sperrend($mitCss(str_replace("  --lp-akzent: #e3eef7;\n", '', $testCss)));
        T::eq(count($b), 1, 'ein sperrender Befund: ' . H::texte($b));
        T::contains($b[0]->technik ?? '', '--lp-akzent', 'nennt das Token');
    },
    'Farbe nicht als Hex' => static function () use ($mitCss, $testCss): void {
        $b = H::sperrend($mitCss(str_replace('--text: #2b3440;', '--text: rgb(43, 52, 64);', $testCss)));
        T::eq(count($b), 1, 'ein Befund: ' . H::texte($b));
        T::contains($b[0]->technik ?? '', '--text', 'nennt das Token');
    },
    'nur :root und @font-face erlaubt' => static function () use ($mitCss, $testCss): void {
        T::eq(count(H::sperrend($mitCss($testCss . "\nbody { color: red; }\n"))), 1, 'zweiter Selektor');
        T::eq(count(H::sperrend($mitCss("@import url(https://fonts.example.com/x.css);\n" . $testCss))), 1, '@import');
        $b = H::sperrend($mitCss($testCss . "\n@font-face { font-family: X; src: url(https://fonts.example.com/x.woff2); }\n"));
        T::ok(count($b) === 1 && str_contains($b[0]->technik, 'nicht erlaubt'), 'fremde url() als nicht erlaubt gemeldet: ' . H::texte($b));
        T::eq(count(H::sperrend($mitCss($testCss . "\n@font-face { font-family: X; src: url(schrift/fehlt.woff2); }\n"))), 1, 'Schriftdatei fehlt');
    },
    'profil.json: Pflichtangaben' => static function () use ($mitJson): void {
        T::eq(count(H::sperrend($mitJson(static function ($j) { unset($j['firma']['email']); return $j; }))), 1, 'E-Mail fehlt');
        T::eq(count(H::sperrend($mitJson(static function ($j) { $j['anrede'] = 'ihr'; return $j; }))), 1, 'Anrede');
        T::eq(count(H::sperrend($mitJson(static function ($j) { $j['formularziel'] = 'http://beispiel.invalid/x'; return $j; }))), 1, 'Formularziel ohne https');
        T::eq(count(H::sperrend($mitJson(static function ($j) { $j['logo']['datei'] = 'logo.gif'; return $j; }))), 1, 'Logo fehlt');
        T::eq(count(H::sperrend($mitJson(static function ($j) { $j['version'] = '1.0'; return $j; }))), 1, 'Version');
        T::eq(count(H::sperrend($mitJson(static function ($j) { $j['link_hosts'] = ['https://www.example.com']; return $j; }))), 1, 'Host mit Schema');
        T::eq(count(H::sperrend($mitJson(static fn ($j) => $j + ['unbekannt' => 1]))), 1, 'unbekannter Schlüssel');
    },
    'Bilder: Datei und Alt-Text' => static function (): void {
        $d = H::profilKopie();
        file_put_contents("$d/bilder/bilder.json", json_encode([['datei' => 'fehlt.webp', 'alt' => 'x', 'motiv' => '', 'quelle' => '', 'freigegeben' => true, 'eingebrannter_text' => false],
            ['datei' => 'beispiel-video.webp', 'alt' => '', 'motiv' => '', 'quelle' => '', 'freigegeben' => true, 'eingebrannter_text' => false]]));
        T::eq(count(H::sperrend(Profil::laden($d)->pruefen())), 2, 'fehlende Datei und leerer Alt-Text');
    },
    'Rechtstexte müssen existieren' => static function (): void {
        $d = H::profilKopie();
        unlink("$d/rechtliches/datenschutz.txt");
        $b = H::sperrend(Profil::laden($d)->pruefen());
        T::eq(count($b), 1, 'ein Befund');
        T::contains($b[0]->kunde ?? '', 'Datenschutz', 'nennt die Datenschutzerklärung');
    },
    'ohne profil.json kein Profil' => static function (): void {
        $d = H::profilKopie();
        unlink("$d/profil.json");
        try { Profil::laden($d); T::ok(false, 'Ausnahme erwartet'); } catch (\RuntimeException $e) { T::contains($e->getMessage(), 'profil.json', 'Meldung'); }
    },
    'Profil übernehmen ersetzt die alte Kopie' => static function (): void {
        $seite = T::tmp() . '/seite';
        mkdir("$seite/profil/bilder", 0700, true);
        file_put_contents("$seite/profil/bilder/alt.webp", 'alt');
        $b = ProfilKopie::uebernehmen($seite, H::testprofil());
        T::eq(H::texte($b), '', 'keine Befunde');
        T::ok(!is_file("$seite/profil/bilder/alt.webp"), 'alte Datei entfernt');
        T::eq(trim((string) file_get_contents("$seite/profil/VERSION")), '1.0.0', 'VERSION geschrieben');
        foreach (['profil.json', 'profil.css', 'logo.svg', 'rechtliches/impressum.txt', 'rechtliches/datenschutz.txt', 'bilder/bilder.json', 'bilder/beispiel-vortrag.webp'] as $f) {
            T::ok(is_file("$seite/profil/$f"), "kopiert: $f");
        }
    },
    'ungültiges Profil wird nicht übernommen' => static function (): void {
        $d = H::profilKopie();
        file_put_contents("$d/profil.css", ':root { --primary: #1d5c8c; }');
        $seite = T::tmp() . '/seite';
        mkdir($seite);
        T::ok(H::sperrend(ProfilKopie::uebernehmen($seite, $d)) !== [], 'sperrende Befunde');
        T::ok(!is_dir("$seite/profil"), 'nichts kopiert');
    },
];
