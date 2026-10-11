<?php
/**
 * Sperrliste der Landingpages, Teil 2: der Prüfer (lp/src/Pruefer.php) — php8.2 tests/run.php pruefen-sperrliste
 *
 * Teil 1 (tests/faltung.test.php) prüft die Faltung und die Suche allein. Hier läuft die ganze Prüfung einer gebauten Seite: Sperrliste (sperrend), Grenzwörter (Hinweis) und „kostenlos trotz Preis“
 * müssen getarnte Schreibweisen in JEDEM Textfeld finden — Titel, Fließtext, Alt-Text, Listenpunkt, Knopf —, und ein Text, der sich nicht prüfen lässt, ist ein sperrender Befund, nie still frei.
 *
 *   PS01 getarntes Sperrwort in Titel, Fließtext, Alt-Text (Bauplan und Bildbestand), Listenpunkt, Knopf, Beschreibung, Wendung → je ein sperrender Befund am richtigen Feld
 *   PS02 getarntes Grenzwort → Hinweis, nie sperrend
 *   PS03 gewöhnliche Texte bleiben frei (Kräuter/Heilkräuter, Garantie/garantiert, schön/schon, Ziffern …)
 *   PS04 nicht prüfbar (Richtungszeichen, über 64 KB, Eintrag über 64 KB) → sperrend, ein Befund je Feld; ohne Liste gibt es nichts zu prüfen
 *   PS05 „kostenlos trotz Preis“ wird auch getarnt gefunden; ein nicht prüfbarer Text sperrt auch dort
 *   PS06 leere Einträge zählen nicht; jeder getroffene Eintrag meldet sich, nicht nur der erste
 *   PS07 das Format der Befunde bleibt (das Panel liest es): technik „Sperrliste: „Wort““ und „Grenzwort: „Wort““, Kundentext mit Abschnitt
 *
 * Keine Kundenlisten: Die Wörter der Listen sind erfundene Beispiele.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;

$k = Katalog::laden();
$b = static fn (string $typ): array => $k->baustein($typ)['beispiel'];
$sperr = ['garantiert', 'Heilkräuter', 'hilft gegen', 'Wirkstoff', '100 % sicher'];
$grenz = ['heilt', 'Wirkung'];
$heute = '2026-09-27T12:00';
/** Baut die Seite mit den Beispiellisten (oder einem eigenen Profil) und prüft sie. */
$pruefe = static function (array $seite, ?string $profil = null) use ($sperr, $grenz, $heute): array {
    $o = H::ordner($seite, null, $profil ?? H::profilMitListen($sperr, $grenz));
    T::eq(H::texte(H::sperrend(Bauer::bauen($o)->befunde)), '', 'Bau ohne sperrende Befunde');
    return Pruefer::pruefen($o, $heute);
};
/** Pfade der Befunde der Art $art, deren technik genau $technik ist (sortiert, ohne Doppelte). */
$pfade = static function (array $befunde, string $art, string $technik): array {
    $p = [];
    foreach ($befunde as $x) {
        if ($x->art === $art && $x->technik === $technik) { $p[$x->pfad] = true; }
    }
    ksort($p);
    return array_keys($p);
};
/** Alle Befunde, deren technik mit „Sperrliste: “ oder „Grenzwort: “ beginnt, als Zeilen „art pfad technik“. */
$listenbefunde = static fn (array $befunde): array => array_values(array_map(static fn ($x) => "$x->art $x->pfad $x->technik",
    array_filter($befunde, static fn ($x) => str_starts_with($x->technik, 'Sperrliste: ') || str_starts_with($x->technik, 'Grenzwort: '))));
/** Eine Seite, deren Hero-Feld $feld (z. B. 'titel') den Text $text trägt. */
$hero = static function (string $feld, string $text): array {
    $s = H::seite();
    $s['bausteine'][0][$feld] = $text;
    return $s;
};

return [
    'PS01 getarntes Sperrwort wird in jedem Textfeld gefunden: Titel, Fließtext, Knopf, Listenpunkt, Beschreibung, Wendung' => static function () use ($pruefe, $pfade, $hero, $b): void {
        $faelle = [
            'Titel, kyrillisches а'                  => [$hero('titel', "Das ist g\u{0430}rantiert gut"), 'garantiert', 'bausteine.0.titel'],
            'Fließtext, Umlaut in NFD'               => [$hero('text', "Heilkra\u{0308}uter für alle, Schritt für Schritt erklärt."), 'Heilkräuter', 'bausteine.0.text'],
            'Fließtext, Umlaut als ae'               => [$hero('text', 'Alles über Heilkraeuter im Garten.'), 'Heilkräuter', 'bausteine.0.text'],
            'Knopf, Vollbreit'                       => [(static function () { $s = H::seite(); $s['bausteine'][0]['knopf']['text'] = "\u{FF47}arantiert anmelden"; return $s; })(), 'garantiert', 'bausteine.0.knopf.text'],
            'Dachzeile, Wendung mit Leerzeichen U+3000' => [$hero('dachzeile', "Es hilft\u{3000}gegen Angst"), 'hilft gegen', 'bausteine.0.dachzeile'],
            'Dachzeile, Buchstabenreihe'             => [$hero('dachzeile', 'Hier steht g a r a n t i e r t'), 'garantiert', 'bausteine.0.dachzeile'],
            'Titel, mathematische Schrift'           => [$hero('titel', 'Der ' . implode('', array_map(static fn (string $c): string => mb_chr(0x1D5EE + ord($c) - ord('a')), str_split('wirkstoff')))), 'Wirkstoff', 'bausteine.0.titel'],
            'Titel, Wort mit Trenner mitten drin'    => [$hero('titel', 'Der Wirk-stoff hilft'), 'Wirkstoff', 'bausteine.0.titel'],
            'Titel, Prozentwendung ohne Leerzeichen' => [$hero('titel', 'Jetzt 100% sicher'), '100 % sicher', 'bausteine.0.titel'],
            'Titel, Unsichtbares im Wort'            => [$hero('titel', "Das ist gar\u{2065}an\u{3164}tiert"), 'garantiert', 'bausteine.0.titel'],
            'Beschreibung der Seite, Ligatur'        => [H::seite([], ['beschreibung' => "Der Wirksto\u{FB00} im Alltag"]), 'Wirkstoff', 'meta.beschreibung'],
            'Listenpunkt, Buchstabenreihe'           => [(static function () use ($b) { $t = $b('themen'); $t['punkte'][1] = 'Alles g.a.r.a.n.t.i.e.r.t'; return H::seite([$t]); })(), 'garantiert', 'bausteine.1.punkte.1'],
            'Listenpunkt, griechisches ο'            => [(static function () use ($b) { $t = $b('themen'); $t['punkte'][2] = "Der Wirkst\u{03BF}ff und du"; return H::seite([$t]); })(), 'Wirkstoff', 'bausteine.1.punkte.2'],
            'Antwort in den Fragen, kyrillisch'      => [(static function () use ($b) { $f = $b('faq'); $f['fragen'][0]['antwort'] = "Ja, g\u{0430}rantiert."; return H::seite([$f]); })(), 'garantiert', 'bausteine.1.fragen.0.antwort'],
            'Erfahrungsbericht, NFD und Zalgo'       => [(static function () use ($b) { $e = $b('erfahrungen'); $e['eintraege'][0]['text'] = "Die Heilkra\u{0308}uter haben mir geholfen."; return H::seite([$e]); })(), 'Heilkräuter', 'bausteine.1.eintraege.0.text'],
        ];
        foreach ($faelle as $was => [$seite, $wort, $pfad]) {
            $befunde = $pruefe($seite);
            T::eq($pfade($befunde, 'sperrend', "Sperrliste: „{$wort}“"), [$pfad], "$was: genau ein sperrender Befund „{$wort}“ am Feld $pfad");
        }
    },
    'PS01 getarntes Sperrwort im Alt-Text: im Bauplan und im Bildbestand' => static function () use ($pruefe, $pfade): void {
        $s = H::seite();
        $s['bausteine'][0]['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'alt' => "Ein \u{FF47}arantiert schönes Bild"];
        T::eq($pfade($pruefe($s), 'sperrend', 'Sperrliste: „garantiert“'), ['bausteine.0.bild.alt'], 'Alt-Text im Bauplan, Vollbreit');
        $s['bausteine'][0]['bild']['alt'] = 'Eine Hand voll Heilkr' . "\u{04D3}" . 'uter';
        T::eq($pfade($pruefe($s), 'sperrend', 'Sperrliste: „Heilkräuter“'), ['bausteine.0.bild.alt'], 'Alt-Text im Bauplan, vorkomponiertes kyrillisches ӓ');
        $profil = H::profilMitListen(['garantiert', 'Heilkräuter'], [], static function (string $d): void {
            $bilder = json_decode((string) file_get_contents("$d/bilder/bilder.json"), true);
            $bilder[0]['alt'] = "Frau, g\u{0430}rantiert zufrieden";
            file_put_contents("$d/bilder/bilder.json", json_encode($bilder, JSON_UNESCAPED_UNICODE));
        });
        T::eq($pfade($pruefe(H::seite(), $profil), 'sperrend', 'Sperrliste: „garantiert“'), ['bausteine.0.bild.alt'], 'Alt-Text aus dem Bildbestand, kyrillisch');
        $video = H::profilMitListen(['garantiert'], [], static function (string $d): void {
            $bilder = json_decode((string) file_get_contents("$d/bilder/bilder.json"), true);
            $bilder[1]['alt'] = 'Das g a r a n t i e r t schönste Video';
            file_put_contents("$d/bilder/bilder.json", json_encode($bilder, JSON_UNESCAPED_UNICODE));
        });
        $k = Endo\Lp\Katalog::laden();
        T::eq($pfade($pruefe(H::seite([$k->baustein('video')['beispiel']]), $video), 'sperrend', 'Sperrliste: „garantiert“'), ['bausteine.1.vorschau.alt'], 'Alt-Text der Videovorschau aus dem Bildbestand, Buchstabenreihe');
    },
    'PS02 getarntes Grenzwort ist ein Hinweis und sperrt nie' => static function () use ($pruefe, $pfade, $hero, $listenbefunde): void {
        $befunde = $pruefe($hero('text', "Das \u{FF48}eilt vieles und die W\u{0456}rkung ist spürbar."));
        T::eq($pfade($befunde, 'hinweis', 'Grenzwort: „heilt“'), ['bausteine.0.text'], 'Vollbreit „heilt“ gibt den Hinweis');
        T::eq($pfade($befunde, 'hinweis', 'Grenzwort: „Wirkung“'), ['bausteine.0.text'], 'kyrillisches і in „Wirkung“ gibt den Hinweis');
        T::eq(H::texte(H::sperrend($befunde)), '', 'Grenzwörter sperren nicht');
        T::eq(count($listenbefunde($befunde)), 2, 'genau die zwei Hinweise');
        $befunde = $pruefe($hero('titel', 'Wirkung: w i r k u n g'));
        T::eq($pfade($befunde, 'hinweis', 'Grenzwort: „Wirkung“'), ['bausteine.0.titel'], 'Grenzwort als Buchstabenreihe');
    },
    'PS03 gewöhnliche deutsche Texte bleiben frei (kein Treffer der Listen)' => static function () use ($pruefe, $listenbefunde, $b): void {
        $themen = $b('themen');
        $themen['punkte'] = ['Kräuter, Blüten und schöne Gärten', 'Die Garantie läuft zwei Jahre, Garantien gibt es nicht', 'Ich eilte nach Hause und es ist schon spät'];
        $faq = $b('faq');
        $faq['fragen'][0] = ['frage' => 'Wie sicher ist die Anmeldung?', 'antwort' => 'Das Formular ist geschützt; wir prüfen 100 Anmeldungen täglich und speichern 100 € Guthaben nicht.'];
        $s = H::seite([$themen, $faq]);
        $s['bausteine'][0]['text'] = 'Wunder-Kräuter, Mütter und Väter: z. B. u. a. Option A, B, C oder D. Option 1, 2, 3.';
        $s['bausteine'][0]['titel'] = 'Frische Kräuter aus dem Garten';
        $s['meta']['beschreibung'] = 'Ein ruhiger Abend über Kräuter, Tees und schöne Gewohnheiten – für alle, die etwas Neues lernen möchten.';
        T::eq($listenbefunde($pruefe($s)), [], 'keine Sperrlisten- oder Grenzwort-Befunde');
        $s['bausteine'][0]['titel'] = 'Привет, мир! Ελληνικά, 日本語, Emoji 🎉✨';
        T::eq($listenbefunde($pruefe($s)), [], 'fremde Schriften und Emoji bleiben frei');
    },
    'PS04 ein Text, der sich nicht prüfen lässt, ist ein sperrender Befund — Richtungszeichen, zu lang; ein Befund je Feld' => static function () use ($pruefe, $pfade, $hero, $listenbefunde): void {
        $befunde = $pruefe($hero('titel', "\u{202E}treitnarag nov uD"));
        T::eq($pfade($befunde, 'sperrend', 'Sperrliste: Text nicht prüfbar'), ['bausteine.0.titel'], 'Richtungszeichen U+202E im Titel: sperrend, genau ein Befund (trotz Sperrliste UND Grenzwörtern)');
        $kunde = '';
        foreach ($befunde as $x) {
            if ($x->technik === 'Sperrliste: Text nicht prüfbar') { $kunde = $x->kunde; }
        }
        T::contains($kunde, 'Abschnitt 1 „Kopfbereich“', 'der Kundentext nennt den Abschnitt');
        T::notContains($kunde, 'darf auf der Seite nicht stehen', 'der Kundentext behauptet kein Sperrwort');
        T::ok(preg_match('~[/\\\\]|\.php|\bpreg\b|\bUTF|\bRegex~u', $kunde) !== 1, 'der Kundentext ohne Pfade, Befehle und Fachwörter: ' . $kunde);
        // zu lang: ein Alt-Text aus dem Bildbestand über 64 KB
        $profil = H::profilMitListen(['garantiert'], [], static function (string $d): void {
            $bilder = json_decode((string) file_get_contents("$d/bilder/bilder.json"), true);
            $bilder[0]['alt'] = str_repeat('Bunte Bühne. ', 6000);
            file_put_contents("$d/bilder/bilder.json", json_encode($bilder, JSON_UNESCAPED_UNICODE));
        });
        T::eq($pfade($pruefe(H::seite(), $profil), 'sperrend', 'Sperrliste: Text nicht prüfbar'), ['bausteine.0.bild.alt'], 'Alt-Text über 64 KB: sperrend (nicht gekappt, nicht frei)');
        // nur Grenzwörter, kein Sperrwort: auch dann ist ein nicht prüfbarer Text gesperrt
        $nurGrenz = H::profilMitListen([], ['heilt']);
        T::eq($pfade($pruefe($hero('titel', "Titel \u{202E}x"), $nurGrenz), 'sperrend', 'Sperrliste: Text nicht prüfbar'), ['bausteine.0.titel'], 'nur Grenzwörter in der Liste: nicht prüfbarer Text sperrt');
        // ohne Liste gibt es nichts zu vergleichen
        $ohne = H::profilMitListen([], []);
        T::eq($listenbefunde($pruefe($hero('titel', "Titel \u{202E}x"), $ohne)), [], 'ohne Sperrliste und Grenzwörter gibt es nichts zu sperren');
        // ein Eintrag der Liste, der sich nicht prüfen lässt: ein sperrender Befund am Profil, die übrigen Einträge prüfen weiter
        $riesig = H::profilMitListen(['garantiert', str_repeat('x', 70000)], []);
        $befunde = $pruefe($hero('titel', "Das ist g\u{0430}rantiert gut"), $riesig);
        T::eq($pfade($befunde, 'sperrend', 'Sperrliste: Eintrag nicht prüfbar (70000 Bytes)'), ['profil.sperrliste'], 'Eintrag über 64 KB: ein sperrender Befund am Profil');
        T::eq($pfade($befunde, 'sperrend', 'Sperrliste: „garantiert“'), ['bausteine.0.titel'], 'die übrigen Einträge prüfen weiter');
        T::contains(implode(' ', array_map(static fn ($x) => $x->kunde, array_filter($befunde, static fn ($x) => $x->pfad === 'profil.sperrliste'))), 'Kundenprofil', 'der Kundentext nennt das Profil');
    },
    'PS04 steht die interne Kodierung nicht auf UTF-8, sperrt der Prüfer (die Listen sind „nicht prüfbar“) — statt still falsch zu falten' => static function () use ($hero, $heute): void {
        $ordner = H::ordner($hero('titel', "Das ist \u{FF47}arantiert gut"), null, H::profilMitListen(['garantiert'], ['heilt']));
        $code = 'require ' . var_export(H::wurzel() . '/lp/autoload.php', true) . '; foreach (Endo\\Lp\\Pruefer::pruefen(' . var_export($ordner, true) . ', ' . var_export($heute, true)
            . ') as $b) { echo json_encode([$b->art, $b->pfad, $b->technik], JSON_UNESCAPED_UNICODE), "\n"; }';
        $lauf = static function (string $zeichensatz) use ($code): array {
            $p = proc_open([PHP_BINARY, '-d', 'default_charset=' . $zeichensatz, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $rohre);
            $aus = '';
            if (is_resource($p)) {
                $aus = (string) stream_get_contents($rohre[1]);
                stream_get_contents($rohre[2]);
                proc_close($p);
            }
            return array_values(array_filter(array_map(static fn (string $z): mixed => json_decode($z, true), explode("\n", $aus)), 'is_array'));
        };
        $gefunden = static fn (array $zeilen, string $technik): array => array_values(array_unique(array_map(static fn (array $z) => $z[1], array_filter($zeilen, static fn (array $z) => $z[0] === 'sperrend' && $z[2] === $technik))));
        T::eq($gefunden($lauf('UTF-8'), 'Sperrliste: „garantiert“'), ['bausteine.0.titel'], 'mit UTF-8 findet der Prüfer das vollbreite „garantiert“ im Titel');
        T::eq($gefunden($lauf('UTF-8'), 'Sperrliste: Eintrag nicht prüfbar (10 Bytes)'), [], 'mit UTF-8 ist jeder Eintrag prüfbar');
        // Mit ISO-8859-1 lässt sich schon jeder Eintrag der Listen nicht prüfen: sperrender Befund am Profil (die Seite wird nie still freigegeben)
        T::eq($gefunden($lauf('ISO-8859-1'), 'Sperrliste: Eintrag nicht prüfbar (10 Bytes)'), ['profil.sperrliste'], 'mit ISO-8859-1 meldet der Prüfer die Sperrliste als nicht prüfbar (sperrend) — er findet nicht still „nichts“');
        T::eq($gefunden($lauf('ISO-8859-1'), 'Sperrliste: „garantiert“'), [], 'und behauptet nichts über ein Sperrwort');
    },
    'PS05 „kostenlos trotz Preis“ wird auch getarnt gefunden, ein nicht prüfbarer Text sperrt auch dort' => static function () use ($pruefe, $b): void {
        $trotz = static fn (array $befunde): array => array_values(array_unique(array_map(static fn ($x) => $x->pfad,
            array_filter(H::sperrend($befunde), static fn ($x) => str_contains($x->technik, 'trotz Preis')))));
        $t = $b('termin');
        $t['preis'] = 25;
        $s = H::seite([$t]);
        $s['meta']['beschreibung'] = 'Online-Abend mit Fragerunde.';
        $s['bausteine'][0]['knopf']['text'] = 'Jetzt anmelden';
        $s['bausteine'][0]['dachzeile'] = 'Online-Abend';
        $s['bausteine'][2]['titel'] = 'Jetzt anmelden';
        $ohneListe = H::profilMitListen([], []);
        T::eq($trotz($pruefe($s, $ohneListe)), [], 'Ausgangsseite: kein Befund');
        foreach (["Gr\u{0430}tis-Abend", "K\u{041E}STENLOS dabei", "\u{FF4B}\u{FF4F}\u{FF53}\u{FF54}\u{FF45}\u{FF4E}\u{FF4C}\u{FF4F}\u{FF53}", 'g r a t i s', 'g.r.a.t.i.s zum Start', 'Gebuehrenfrei dabei', 'Gebührenfreier Abend',
            "Eintritt\u{3000}frei", "Eintritt\u{3164}frei", "Kostenfreie Teilnahme", 'kosten-los', 'Heute umsonst dabei', "Gratis\u{200B}abend", "Gratis\u{0430}bend", '𝗴𝗿𝗮𝘁𝗶𝘀', "Das ist unent\u{0261}eltlich", 'zum Nulltarif', 'ohne Kosten', 'ohne Gebuehren', 'ohne Gebühr', 'Ohne Gebühren teilnehmen', 'Freier Eintritt'] as $wort) {
            $s['bausteine'][0]['dachzeile'] = $wort;
            T::eq($trotz($pruefe($s, $ohneListe)), ['bausteine.0.dachzeile'], "„{$wort}“ sperrt");
        }
        foreach (['Nicht umsonst bekannt', 'Kostenbeitrag inklusive', 'Gratulation zum Jubiläum', 'Frei sprechen lernen', 'Eintritt in die Stille', 'Online-Abend', 'Kostenübersicht', 'Das ist nicht gratis-ähnlich: 25 € Beitrag'] as $wort) {
            $s['bausteine'][0]['dachzeile'] = $wort;
            $erwartet = str_contains($wort, 'gratis') ? ['bausteine.0.dachzeile'] : [];   // „gratis-ähnlich“ enthält das Wort „gratis“ und ist wie bisher ein Treffer
            T::eq($trotz($pruefe($s, $ohneListe)), $erwartet, "„{$wort}“" . ($erwartet === [] ? ' sperrt nicht' : ' sperrt (wie bisher)'));
        }
        $s['bausteine'][0]['dachzeile'] = "Online \u{202E}dnebA";
        $nichtPruefbar = static fn (array $befunde): array => array_values(array_map(static fn ($x) => $x->pfad,
            array_filter(H::sperrend($befunde), static fn ($x) => $x->technik === 'Sperrliste: Text nicht prüfbar')));
        T::eq($nichtPruefbar($pruefe($s, $ohneListe)), ['bausteine.0.dachzeile'], 'Preis-Prüfung: ein nicht prüfbarer Text sperrt, auch ohne Listen im Profil');
        T::eq($nichtPruefbar($pruefe($s, H::profilMitListen(['garantiert'], ['heilt']))), ['bausteine.0.dachzeile'], 'mit Listen im Profil nur EIN Befund „nicht prüfbar“ für dasselbe Feld (Listen und Preis-Prüfung melden es nicht doppelt)');
        // bei preis 0 oder ohne Preis prüft die Regel nichts
        $t['preis'] = 0;
        $null = H::seite([$t]);
        T::eq($trotz($pruefe($null, $ohneListe)), [], 'preis 0: „kostenlos“-Texte passen');
    },
    'PS06 leere Einträge zählen nicht, und jeder getroffene Eintrag meldet sich' => static function () use ($pruefe, $pfade, $hero, $listenbefunde): void {
        $leer = H::profilMitListen(['*', "\u{200B}", "\u{0301}", '***', "\u{FEFF}*", 'garantiert'], ['**', "\u{00AD}"]);
        T::eq($listenbefunde($pruefe($hero('text', 'Ein ganz gewöhnlicher Satz mit * und ** und Sternen.'), $leer)), [], 'Einträge, die gefaltet nichts übrig lassen, sperren nichts');
        T::eq($pfade($pruefe($hero('text', "Alles g\u{0430}rantiert."), $leer), 'sperrend', 'Sperrliste: „garantiert“'), ['bausteine.0.text'], 'der gültige Eintrag daneben gilt');
        $befunde = $pruefe($hero('text', "Der Wirkst\u{043E}ff ist g\u{0430}rantiert gut und h\u{0456}lft\u{3000}gegen alles."));
        $gefunden = [];
        foreach ($befunde as $x) {
            if ($x->art === 'sperrend' && str_starts_with($x->technik, 'Sperrliste: „')) { $gefunden[] = $x->technik; }
        }
        T::eq($gefunden, ['Sperrliste: „garantiert“', 'Sperrliste: „hilft gegen“', 'Sperrliste: „Wirkstoff“'], 'alle drei getroffenen Einträge in der Reihenfolge der Liste, nicht nur der erste');
    },
    'PS07 das Format der Befunde bleibt, wie das Panel es liest' => static function () use ($pruefe, $hero): void {
        // Das Panel erkennt Treffer an diesen technik-Zeilen (app/Services/Landingpage/Befunde.php: SPERRLISTE, GRENZWORT) und zeigt sonst den Satz des Baukastens ohne Ortsangabe.
        $sperrliste = '/^Sperrliste: „(.+)“$/us';
        $grenzwort = '/^Grenzwort: „(.+)“$/us';
        $ort = '~^(?:Abschnitt \d+ „[^“]*“(?:, Eintrag \d+)?|Seiteneinstellungen): ~u';
        $befunde = $pruefe($hero('titel', "Der g\u{0430}rantiert heilt"));
        $treffer = null;
        $grenze = null;
        foreach ($befunde as $x) {
            if (preg_match($sperrliste, $x->technik, $m) === 1) { $treffer = [$x, $m[1]]; }
            if (preg_match($grenzwort, $x->technik, $m) === 1) { $grenze = [$x, $m[1]]; }
        }
        T::ok($treffer !== null && $treffer[1] === 'garantiert' && $treffer[0]->art === 'sperrend' && $treffer[0]->pfad === 'bausteine.0.titel', 'Sperrliste-Treffer: technik „Sperrliste: „garantiert““, sperrend, Pfad des Feldes');
        T::ok($grenze !== null && $grenze[1] === 'heilt' && $grenze[0]->art === 'hinweis', 'Grenzwort-Treffer: technik „Grenzwort: „heilt““, Hinweis');
        T::eq($treffer[0]->kunde ?? '', 'Abschnitt 1 „Kopfbereich“: „garantiert“ darf auf der Seite nicht stehen (Sperrliste).', 'Kundentext des Sperrwort-Treffers unverändert');
        T::eq($grenze[0]->kunde ?? '', 'Abschnitt 1 „Kopfbereich“: „heilt“ bitte gegenlesen — keine Heil- oder Wirkversprechen.', 'Kundentext des Grenzwort-Hinweises unverändert');
        $nicht = $pruefe($hero('titel', "\u{202E}Titel"));
        foreach ($nicht as $x) {
            if ($x->technik === 'Sperrliste: Text nicht prüfbar') {
                T::ok(preg_match($sperrliste, $x->technik) !== 1 && preg_match($grenzwort, $x->technik) !== 1, 'der Befund „nicht prüfbar“ wird vom Panel NICHT als Heil- oder Wirkversprechen ausgegeben');
                T::ok(preg_match($ort, $x->kunde) === 1, 'sein Kundentext beginnt mit der Ortsangabe des Baukastens (das Panel schneidet sie ab): ' . $x->kunde);
                T::ok($x->art === 'sperrend', 'sperrend');
                return;
            }
        }
        T::ok(false, 'kein Befund „Sperrliste: Text nicht prüfbar“ gefunden');
    },
];
