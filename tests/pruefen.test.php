<?php
/** Prüfungen vor der Freigabe (lp/src/Pruefer.php, Entwurf §8, statischer Teil). */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;

const HEUTE = '2026-09-27T12:00';

$k = Katalog::laden();
$b = static fn (string $typ): array => $k->baustein($typ)['beispiel'];
$alle = array_map($b, ['termin', 'themen', 'video', 'erfahrungen', 'faq', 'abschluss']);
/** Baut die Seite und prüft sie. */
$pruefe = static function (array $seite, ?string $profil = null, string $heute = HEUTE, ?callable $danach = null): array {
    $o = H::ordner($seite, null, $profil);
    T::eq(H::texte(H::sperrend(Bauer::bauen($o)->befunde)), '', 'Bau ohne sperrende Befunde');
    if ($danach) { $danach($o); }
    return Pruefer::pruefen($o, $heute);
};
/** Gibt es einen Befund der Art $art, dessen Technik-Text $teil enthält? */
$hat = static function (array $befunde, string $art, string $teil, string $was): void {
    foreach ($befunde as $x) {
        if ($x->art === $art && (str_contains($x->technik, $teil) || str_contains($x->pfad, $teil))) { T::ok(true, $was); return; }
    }
    T::ok(false, "$was — erwartet $art mit „{$teil}\", bekommen:\n" . H::texte($befunde));
};
$profilMit = static function (callable $aendern, ?callable $dateien = null): string {
    $p = H::profilKopie();
    $j = json_decode((string) file_get_contents("$p/profil.json"), true);
    file_put_contents("$p/profil.json", json_encode($aendern($j), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($dateien) { $dateien($p); }
    return $p;
};

return [
    'vollständige Seite mit Formularziel und künftigem Termin: nichts Sperrendes' => static function () use ($pruefe, $alle): void {
        $befunde = $pruefe(H::seite($alle));
        T::eq(H::texte(H::sperrend($befunde)), '', 'keine sperrenden Befunde');
    },
    'ohne Formularziel ist der Livegang gesperrt' => static function () use ($pruefe, $hat, $profilMit): void {
        $hat($pruefe(H::seite(), $profilMit(static fn ($j) => ['formularziel' => ''] + $j)), 'sperrend', 'formularziel', 'Anmeldung nicht angebunden');
    },
    'Termine: alle vergangen sperrt, einzelne und baldige sind Hinweise' => static function () use ($pruefe, $hat, $b): void {
        $t = $b('termin');
        $t['termine'] = [['beginn' => '2026-09-01T19:00', 'dauer_min' => 60], ['beginn' => '2026-10-06T19:00', 'dauer_min' => 60]];
        $befunde = $pruefe(H::seite([$t]));
        T::eq(H::texte(H::sperrend($befunde)), '', 'einer vergangen: nicht sperrend');
        $hat($befunde, 'hinweis', 'vergangen', 'Hinweis auf den vergangenen Termin');
        $hat($pruefe(H::seite([$t]), null, '2100-01-01T00:00'), 'sperrend', 'alle Termine vergangen', 'alle vergangen');
        $hat($pruefe(H::seite([$t]), null, '2026-10-06T08:00'), 'hinweis', 'weniger als 24', 'Termin in weniger als 24 Stunden');
        $hat($pruefe(H::seite([$t]), null, '2026-10-06T19:30'), 'hinweis', 'vergangen', 'laufender Termin gilt bis zum Ende als offen');
        T::eq(H::texte(H::sperrend($pruefe(H::seite([$t]), null, '2026-10-06T19:30'))), '', 'laufender Termin sperrt nicht');
    },
    'Sperrliste in allen Textfeldern, Grenzwörter als Hinweis' => static function () use ($pruefe, $hat, $b, $profilMit): void {
        $e = $b('erfahrungen');
        $e['eintraege'][0]['text'] = 'Ich wurde GARANTIERT GEHEILT.';
        $hat($pruefe(H::seite([$e])), 'sperrend', 'bausteine.1.eintraege.0.text', 'Sperrwort im Erfahrungsbericht');
        $hat($pruefe(H::seite([], ['beschreibung' => 'Hier wird man garantiert geheilt.'])), 'sperrend', 'meta.beschreibung', 'Sperrwort in der Meta-Beschreibung');
        $s = H::seite();
        $s['bausteine'][0]['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'alt' => 'Mann, garantiert geheilt'];
        $hat($pruefe($s), 'sperrend', 'bausteine.0.bild.alt', 'Sperrwort im Alt-Text der Seite');
        $p = $profilMit(static fn ($j) => $j, static function ($d): void {
            $bilder = json_decode((string) file_get_contents("$d/bilder/bilder.json"), true);
            $bilder[0]['alt'] = 'Frau, garantiert geheilt';
            file_put_contents("$d/bilder/bilder.json", json_encode($bilder, JSON_UNESCAPED_UNICODE));
        });
        $hat($pruefe(H::seite(), $p), 'sperrend', 'bausteine.0.bild.alt', 'Sperrwort im Alt-Text aus dem Bildbestand');
        $e['eintraege'][0]['text'] = 'Das heilt nicht alles, hilft aber.';
        $befunde = $pruefe(H::seite([$e]));
        $hat($befunde, 'hinweis', 'bausteine.1.eintraege.0.text', 'Grenzwort als Hinweis');
        T::eq(H::texte(H::sperrend($befunde)), '', 'Grenzwort sperrt nicht');
    },
    'Platzhalter sperren' => static function () use ($pruefe, $hat, $b): void {
        foreach (['{{Thema}}' => 'geschweifte Klammern', 'Lorem ipsum dolor' => 'Lorem ipsum', 'TODO Text' => 'TODO', '…' => 'nur Auslassung', '...' => 'nur Punkte'] as $text => $was) {
            $t = $b('themen');
            $t['punkte'][1] = $text;
            $hat($pruefe(H::seite([$t])), 'sperrend', 'bausteine.1.punkte.1', "Platzhalter: $was");
        }
    },
    'Platzhalter-Rechtstext sperrt' => static function () use ($pruefe, $hat, $profilMit): void {
        $p = $profilMit(static fn ($j) => $j, static fn ($d) => file_put_contents("$d/rechtliches/impressum.txt", "## Impressum\n\n{{PLATZHALTER: Text vom Kunden}}\n"));
        $hat($pruefe(H::seite(), $p), 'sperrend', 'impressum', 'Impressum ist Platzhalter');
    },
    'Profil als Vorschlag ist ein Hinweis' => static function () use ($pruefe, $hat, $profilMit): void {
        $hat($pruefe(H::seite(), $profilMit(static fn ($j) => ['vorschlag' => true] + $j)), 'hinweis', 'vorschlag', 'Vorschlag');
    },
    'von Hand geänderte Dateien fallen auf (Nachbau-Vergleich)' => static function () use ($pruefe, $hat): void {
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/index.html", str_replace('Gesund durch den Winter</h1>', 'Anders</h1>', (string) file_get_contents("$o/index.html")))), 'sperrend', 'index.html', 'geänderte index.html');
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/es/extra.js", 'x')), 'sperrend', 'es/extra.js', 'zusätzliche Datei in es/');
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => unlink("$o/robots.txt")), 'sperrend', 'robots.txt', 'fehlende Datei');
    },
    'HTML-Regeln auch an von Hand geänderten Seiten' => static function () use ($pruefe, $hat): void {
        $einbau = static fn (string $html) => static fn ($o) => file_put_contents("$o/index.html", str_replace('</main>', $html . '</main>', (string) file_get_contents("$o/index.html")));
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<a href="https://evil.example.org/">x</a>')), 'sperrend', 'evil.example.org', 'fremder Link');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<a href="#gibt-es-nicht">x</a>')), 'sperrend', '#gibt-es-nicht', 'toter Anker');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<a href="fehlt.html">x</a>')), 'sperrend', 'fehlt.html', 'tote interne Seite');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<p style="color:red">x</p>')), 'sperrend', 'style', 'style-Attribut');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<script>alert(1)</script>')), 'sperrend', 'Skript', 'Inline-Skript');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<img src="https://cdn.example.com/a.webp" alt="" width="1" height="1">')), 'sperrend', 'cdn.example.com', 'fremde Bildquelle');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<iframe src="about:blank" title="x"></iframe>')), 'sperrend', 'iframe', 'iframe im HTML');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<h1>Zweite</h1>')), 'sperrend', 'H1', 'zwei H1');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<link rel="preconnect" href="https://fonts.example.com">')), 'sperrend', 'fonts.example.com', 'preconnect');
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/es/lp.css", "@import url(https://fonts.example.com/x.css);\n", FILE_APPEND)), 'sperrend', 'lp.css', 'fremder Import im CSS');
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/index.html", str_replace('type="checkbox" value="ja"', 'type="checkbox" value="ja" checked', (string) file_get_contents("$o/index.html")))), 'sperrend', 'vorangehakt', 'vorangehakte Einwilligung');
    },
    'Bilder: Größe und Format' => static function () use ($pruefe, $hat): void {
        $o = H::ordner(H::seite());
        mkdir("$o/img");
        $im = imagecreatetruecolor(900, 600);
        for ($i = 0; $i < 900 * 600 / 3; $i++) { imagesetpixel($im, ($i * 7919) % 900, ($i * 104729) % 600, ($i * 2654435761) & 0xFFFFFF); }
        imagepng($im, "$o/img/gross.png", 0);
        imagewebp($im, "$o/img/gross.webp", 100);
        T::ok(filesize("$o/img/gross.png") > 400 * 1024, 'Testbild ist groß');
        $s = H::seite();
        $s['bausteine'][0]['bild'] = ['datei' => 'img/gross.png', 'alt' => 'Testbild'];
        file_put_contents("$o/seite.json", json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Bauer::bauen($o);
        $befunde = Pruefer::pruefen($o, HEUTE);
        $hat($befunde, 'sperrend', 'WebP', 'kein WebP');
        $hat($befunde, 'sperrend', '400 KB', 'zu groß');
    },
    'ASCII-Umlaute und fehlendes Vorschaubild sind Hinweise' => static function () use ($pruefe, $hat, $b): void {
        $t = $b('themen');
        $t['punkte'][0] = 'Was fuer ein Thema';
        $befunde = $pruefe(H::seite([$t]));
        $hat($befunde, 'hinweis', 'bausteine.1.punkte.0', 'ASCII-Umlaut');
        $hat($befunde, 'hinweis', 'meta.vorschaubild', 'kein Vorschaubild');
        T::eq(H::texte(H::sperrend($befunde)), '', 'nur Hinweise');
    },
    'ungebaute Seite: Befunde des Baus' => static function () use ($hat): void {
        $s = H::seite(); $s['bausteine'][0]['titel'] = '';
        $o = H::ordner($s);
        $hat(Pruefer::pruefen($o, HEUTE), 'sperrend', 'bausteine.0.titel', 'Schema-Befund');
    },
];
