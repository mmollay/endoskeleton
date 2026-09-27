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
/** Gibt es einen Befund der Art $art, dessen Technik-Text oder Pfad $teil enthält? Der Nachbau-Befund zählt nicht mit. */
$hat = static function (array $befunde, string $art, string $teil, string $was): void {
    foreach ($befunde as $x) {
        if (str_starts_with($x->technik, 'gebaute Dateien passen nicht')) { continue; }
        if ($x->art === $art && (str_contains($x->technik, $teil) || str_contains($x->pfad, $teil))) { T::ok(true, $was); return; }
    }
    T::ok(false, "$was — erwartet $art mit „{$teil}“, bekommen:\n" . H::texte($befunde));
};
/** Meldet der Nachbau-Vergleich die Datei $datei? */
$nachbau = static function (array $befunde, string $datei, string $was): void {
    foreach ($befunde as $x) {
        if ($x->art === 'sperrend' && str_starts_with($x->technik, 'gebaute Dateien passen nicht') && str_contains($x->technik, $datei)) { T::ok(true, $was); return; }
    }
    T::ok(false, "$was — Nachbau-Befund zu $datei fehlt:\n" . H::texte($befunde));
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
    'Anmeldung ohne Sprungmarke „anmeldung“ sperrt (Rücksprung der Weiterleitung geht auf #anmeldung)' => static function () use ($pruefe, $hat): void {
        $s = H::seite();
        $n = count($s['bausteine']) - 1;
        unset($s['bausteine'][0]['knopf'], $s['bausteine'][$n]['anker']);
        $hat($pruefe($s), 'sperrend', "bausteine.$n.anker", 'Anmeldung ohne Sprungmarke');
        $s['bausteine'][$n]['anker'] = 'mitmachen';
        $hat($pruefe($s), 'sperrend', "bausteine.$n.anker", 'Anmeldung mit anderer Sprungmarke');
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
    'von Hand geänderte Dateien fallen auf (Nachbau-Vergleich)' => static function () use ($pruefe, $nachbau): void {
        $nachbau($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/index.html", str_replace('Gesund durch den Winter</h1>', 'Anders</h1>', (string) file_get_contents("$o/index.html")))), 'index.html', 'geänderte index.html');
        $nachbau($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/es/extra.js", 'x')), 'es/extra.js', 'zusätzliche Datei in es/');
        $nachbau($pruefe(H::seite(), null, HEUTE, static fn ($o) => unlink("$o/robots.txt")), 'robots.txt', 'fehlende Datei');
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
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<style>p { color: red; }</style>')), 'sperrend', 'style', '<style>-Element');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<p onclick="x">x</p>')), 'sperrend', 'on*-Attribut', 'on-Attribut');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<img src="es/logo.svg" srcset="https://cdn.example.com/a.webp 2x" alt="" width="1" height="1">')), 'sperrend', 'cdn.example.com', 'fremde Quelle im srcset');
        $hat($pruefe(H::seite(), null, HEUTE, $einbau('<p id="inhalt">x</p>')), 'sperrend', 'doppelt', 'doppelte id');
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/es/lp.css", "body { background: url(https://cdn.example.com/p.png); }\n", FILE_APPEND)), 'sperrend', 'fremde Dateien', 'url() mit Adresse im CSS');
        $ohne = static fn (string $muster) => static fn ($o) => file_put_contents("$o/index.html", (string) preg_replace($muster, '', (string) file_get_contents("$o/index.html")));
        $hat($pruefe(H::seite(), null, HEUTE, $ohne('#<div class="form-hp".*?</div>\n#s')), 'sperrend', 'Honigtopf', 'Honigtopf fehlt');
        $hat($pruefe(H::seite(), null, HEUTE, $ohne('#<input id="lp-f-einwilligung"[^>]*>#')), 'sperrend', 'Einwilligung fehlt', 'Einwilligung fehlt');
    },
    'keine Fehlalarme bei „online =“ oder „style =“ im Text' => static function () use ($pruefe): void {
        T::eq(H::texte(H::sperrend($pruefe(H::seite([], ['beschreibung' => 'Teilnahme online = kostenlos. Dein neuer style = gesund.'])))), '', 'nur Text, kein Attribut');
    },
    'Sperrliste lässt sich nicht mit Auszeichnung oder Leerraum umgehen' => static function () use ($pruefe, $hat, $b): void {
        foreach (['Ich bin jetzt **garantiert** geheilt.', "Ich bin garantiert\ngeheilt.", 'Ich bin garantiert  geheilt.', "Ich bin garan\u{00AD}tiert geheilt.", "Ich bin garantiert\u{200B} geheilt."] as $text) {
            $e = $b('erfahrungen');
            $e['eintraege'][0]['text'] = $text;
            $hat($pruefe(H::seite([$e])), 'sperrend', 'Sperrliste', 'Sperrwort trotz ' . json_encode($text, JSON_UNESCAPED_UNICODE));
        }
    },
    'nur Gebautes und Verwendetes liegt im Seitenordner' => static function () use ($pruefe, $hat): void {
        $hat($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/aktion.html", '<script>alert(1)</script>')), 'sperrend', 'aktion.html', 'fremde Datei oben');
        $hat($pruefe(H::seite(), null, HEUTE, static function ($o): void { mkdir("$o/img"); copy(H::testprofil() . '/bilder/beispiel-video.webp', "$o/img/unbenutzt.webp"); }), 'sperrend', 'img/unbenutzt.webp', 'unbenutztes Bild in img/');
        T::eq(H::texte(H::sperrend($pruefe(H::seite(), null, HEUTE, static fn ($o) => file_put_contents("$o/VERSION", "1.0.0\n")))), '', 'VERSION und CHANGELOG des Livegangs sind erlaubt');
    },
    'Bildrechte: nicht freigegebene Bilder sperren, eingebrannter Text ist ein Hinweis' => static function () use ($pruefe, $hat, $profilMit): void {
        $mit = static fn (array $aenderung) => $profilMit(static fn ($j) => $j, static function ($d) use ($aenderung): void {
            $bilder = json_decode((string) file_get_contents("$d/bilder/bilder.json"), true);
            $bilder[0] = $aenderung + $bilder[0];
            file_put_contents("$d/bilder/bilder.json", json_encode($bilder, JSON_UNESCAPED_UNICODE));
        });
        $hat($pruefe(H::seite(), $mit(['freigegeben' => false])), 'sperrend', 'freigegeben', 'Bild nicht freigegeben');
        $befunde = $pruefe(H::seite(), $mit(['eingebrannter_text' => true]));
        $hat($befunde, 'hinweis', 'eingebrannt', 'eingebrannter Text');
        T::eq(H::texte(H::sperrend($befunde)), '', 'eingebrannter Text sperrt nicht');
    },
    'Überschriftensprung in einem Rechtstext ist ein Hinweis' => static function () use ($pruefe, $hat, $profilMit): void {
        $p = $profilMit(static fn ($j) => $j, static fn ($d) => file_put_contents("$d/rechtliches/datenschutz.txt", "### Gleich Ebene drei\n\nText.\n"));
        $hat($pruefe(H::seite(), $p), 'hinweis', 'Überschrift', 'Sprung von H1 zu H3');
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
        $o2 = H::ordner(H::seite());
        mkdir("$o2/img");
        imagepng(imagecreatetruecolor(60, 40), "$o2/img/verkleidet.webp");
        $s['bausteine'][0]['bild'] = ['datei' => 'img/verkleidet.webp', 'alt' => 'Testbild'];
        file_put_contents("$o2/seite.json", json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Bauer::bauen($o2);
        $hat(Pruefer::pruefen($o2, HEUTE), 'sperrend', 'WebP', 'PNG mit Endung .webp wird am Inhalt erkannt');
    },
    'ASCII-Umlaute und fehlendes Vorschaubild sind Hinweise' => static function () use ($pruefe, $hat, $b): void {
        $t = $b('themen');
        $t['punkte'][0] = 'Was fuer ein Thema';
        $befunde = $pruefe(H::seite([$t]));
        $hat($befunde, 'hinweis', 'bausteine.1.punkte.0', 'ASCII-Umlaut');
        T::eq(H::texte(H::sperrend($befunde)), '', 'nur Hinweise');
    },
    'ungebaute Seite: Befunde des Baus' => static function () use ($hat): void {
        $s = H::seite(); $s['bausteine'][0]['titel'] = '';
        $o = H::ordner($s);
        $hat(Pruefer::pruefen($o, HEUTE), 'sperrend', 'bausteine.0.titel', 'Schema-Befund');
    },
];
