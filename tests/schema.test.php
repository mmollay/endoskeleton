<?php
/** Prüfung von seite.json gegen Katalog und Profil: kaputte Baupläne → genau die erwarteten Meldungen. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Schema;

$k = Katalog::laden();
$p = Profil::laden(H::testprofil());
$b = static fn (string $typ): array => $k->baustein($typ)['beispiel'];
$pruefe = static fn (mixed $seite, ?string $ordner = null): array => Schema::pruefen($seite, $k, $p, $ordner ?? T::tmp());
/** Genau ein sperrender Befund, dessen Technik-Text $teil enthält. */
$einer = static function (mixed $seite, string $teil, string $was, ?string $ordner = null) use ($pruefe): void {
    $befunde = $pruefe($seite, $ordner);
    $s = H::sperrend($befunde);
    T::ok(count($s) === 1 && (str_contains($s[0]->pfad, $teil) || str_contains($s[0]->technik, $teil)), "$was — erwartet genau einen Befund mit „{$teil}\", bekommen:\n" . H::texte($befunde));
};
$alle = ['termin', 'themen', 'video', 'erfahrungen', 'faq', 'abschluss'];

return [
    'gültiger Bauplan mit allen Bausteinen' => static function () use ($pruefe, $b, $alle): void {
        T::eq(H::texte($pruefe(H::seite(array_map($b, $alle)))), '', 'keine Befunde');
    },
    'Form des Bauplans' => static function () use ($einer, $pruefe): void {
        T::eq(count(H::sperrend($pruefe([1, 2]))), 1, 'Liste statt Objekt');
        T::eq(count(H::sperrend($pruefe('text'))), 1, 'Text statt Objekt');
        $s = H::seite(); $s['format'] = 2;
        $einer($s, 'format', 'Format 2');
        $s = H::seite(); $s['farbe'] = 'rot';
        $einer($s, 'farbe', 'unbekannter Schlüssel oben');
        $s = H::seite(); $s['bausteine'] = [];
        $einer($s, 'bausteine', 'keine Bausteine');
    },
    'Meta-Angaben' => static function () use ($einer): void {
        $einer(H::seite([], ['titel' => str_repeat('ä', 61)]), 'meta.titel', 'Titel zu lang');
        $s = H::seite(); unset($s['meta']['beschreibung']);
        $einer($s, 'meta.beschreibung', 'Beschreibung fehlt');
        $einer(H::seite([], ['indexieren' => 'ja']), 'meta.indexieren', 'indexieren kein Wahrheitswert');
        $einer(H::seite([], ['vorschaubild' => 'bestand/fehlt.webp']), 'meta.vorschaubild', 'Vorschaubild unbekannt');
    },
    'Typ, Variante, unbekanntes Feld' => static function () use ($einer, $b): void {
        $einer(H::seite([['typ' => 'karussell']]), 'bausteine.1.typ', 'unbekannter Typ');
        $einer(H::seite([['variante' => 'rund'] + $b('termin')]), 'bausteine.1.variante', 'unbekannte Variante');
        $einer(H::seite([$b('termin') + ['farbe' => 'rot']]), 'bausteine.1.farbe', 'unbekanntes Feld');
    },
    'Pflichtfelder, auch je Variante' => static function () use ($einer, $pruefe, $b): void {
        $t = $b('termin'); unset($t['wenn_leer']);
        $einer(H::seite([$t]), 'bausteine.1.wenn_leer', 'Pflichtfeld fehlt');
        $s = H::seite(); unset($s['bausteine'][0]['bild']);
        $einer($s, 'bausteine.0.bild', 'Bild fehlt bei bild-rechts');
        $s = H::seite(); unset($s['bausteine'][0]['bild']); $s['bausteine'][0]['variante'] = 'schlicht';
        T::eq(H::texte($pruefe($s)), '', 'schlicht braucht kein Bild');
        $v = $b('video'); $v['variante'] = 'mit-text';
        $einer(H::seite([$v]), 'bausteine.1.text', 'Text fehlt bei Video mit Text');
        $t = $b('termin'); $t['titel'] = '   ';
        $einer(H::seite([$t]), 'bausteine.1.titel', 'nur Leerzeichen');
    },
    'Längen zählen Zeichen, nicht Bytes' => static function () use ($pruefe, $b): void {
        $e = $b('erfahrungen');
        $e['eintraege'][0]['text'] = str_repeat('ä', 300);
        T::eq(H::texte($pruefe(H::seite([$e]))), '', '300 Umlaute sind erlaubt');
        $e['eintraege'][0]['text'] = str_repeat('ä', 301);
        $s = H::sperrend($pruefe(H::seite([$b('themen'), $b('video'), $b('termin'), $e])));
        T::eq(count($s), 1, 'ein Befund');
        T::eq($s[0]->pfad ?? '', 'bausteine.4.eintraege.0.text', 'Pfad');
        T::eq($s[0]->kunde ?? '', 'Abschnitt 5 „Erfahrungen", Eintrag 1: „Text" ist 301 Zeichen lang, erlaubt sind 300.', 'Kundentext');
    },
    'Formen der Felder' => static function () use ($einer, $b): void {
        $t = $b('termin'); $t['titel'] = 42;
        $einer(H::seite([$t]), 'bausteine.1.titel', 'Zahl statt Text');
        $t = $b('termin'); $t['titel'] = "Zwei\nZeilen";
        $einer(H::seite([$t]), 'bausteine.1.titel', 'Zeilenumbruch in einer Zeile');
        $t = $b('termin'); $t['termine'][0]['beginn'] = '2099-02-30T19:00';
        $einer(H::seite([$t]), 'bausteine.1.termine.0.beginn', 'ungültiger Zeitpunkt');
        $t = $b('termin'); $t['termine'][0]['dauer_min'] = 5;
        $einer(H::seite([$t]), 'bausteine.1.termine.0.dauer_min', 'Dauer zu kurz');
        $t = $b('termin'); $t['termine'] = array_fill(0, 7, $t['termine'][0]);
        $einer(H::seite([$t]), 'bausteine.1.termine', 'zu viele Termine');
        $t = $b('themen'); $t['punkte'] = ['nur einer'];
        $einer(H::seite([$t]), 'bausteine.1.punkte', 'zu wenige Punkte');
        $t = $b('termin'); $t['art'] = 'hybrid';
        $einer(H::seite([$t]), 'bausteine.1.art', 'unbekannte Auswahl');
        $v = $b('video'); $v['kennung'] = 'zu-kurz';
        $einer(H::seite([$v]), 'bausteine.1.kennung', 'YouTube-Kennung');
        $s = H::seite(); $s['bausteine'][1]['felder'] = ['vorname'];
        $einer($s, 'bausteine.1.felder', 'E-Mail fehlt im Formular');
        $s = H::seite(); $s['bausteine'][1]['felder'] = ['email', 'email'];
        $einer($s, 'bausteine.1.felder', 'Feld doppelt');
        $s = H::seite(); $s['bausteine'][1]['felder'] = ['email', 'geburtstag'];
        $einer($s, 'bausteine.1.felder', 'unbekanntes Formularfeld');
    },
    'Links nur https auf erlaubte Hosts' => static function () use ($einer, $pruefe, $b): void {
        $e = $b('erfahrungen'); $e['eintraege'][0]['quelle'] = 'https://evil.example.org/x';
        $einer(H::seite([$e]), 'bausteine.1.eintraege.0.quelle', 'fremder Host');
        $e['eintraege'][0]['quelle'] = 'http://www.example.com/x';
        $einer(H::seite([$e]), 'bausteine.1.eintraege.0.quelle', 'http');
        $a = $b('abschluss'); $a['knopf']['ziel'] = 'javascript:alert(1)';
        $einer(H::seite([$a]), 'bausteine.1.knopf.ziel', 'javascript im Knopf');
        $a['knopf']['ziel'] = 'https://www.example.com/shop';
        T::eq(H::texte($pruefe(H::seite([$a]))), '', 'Knopf auf erlaubten Host');
    },
    'Regeln: zuerst, genau einmal, höchstens' => static function () use ($einer, $pruefe, $b): void {
        $s = H::seite([$b('termin')]); $s['bausteine'] = [$s['bausteine'][1], $s['bausteine'][0], $s['bausteine'][2]];
        $einer($s, 'zuerst', 'Kopfbereich nicht zuerst');
        $s = H::sperrend($pruefe(H::seite([$b('hero')])));
        T::eq(array_map(static fn ($x) => $x->technik, $s), ['hero muss zuerst stehen', 'hero genau einmal erwartet, 2 gefunden'], 'zwei Kopfbereiche: zweiter nicht zuerst und nicht genau einmal');
        $s = H::seite([$b('anmeldung') + ['anker' => 'zweite']]); $s['bausteine'][1]['anker'] = 'zweite';
        $einer($s, 'höchstens 1', 'zwei Anmeldungen');
    },
    'Anker: Ziel muss existieren, keine Doppelten' => static function () use ($einer, $b): void {
        $a = $b('abschluss'); $a['knopf']['ziel'] = '#gibt-es-nicht';
        $einer(H::seite([$a]), 'bausteine.1.knopf.ziel', 'Anker fehlt');
        $t = $b('termin'); $t['anker'] = 'anmeldung';
        $einer(H::seite([$t]), 'anmeldung', 'Anker doppelt');
        $t = $b('termin'); $t['anker'] = 'Termin 1';
        $einer(H::seite([$t]), 'bausteine.1.anker', 'ungültiger Anker');
    },
    'Bilder aus Bestand und Seitenordner' => static function () use ($einer, $pruefe): void {
        $s = H::seite(); $s['bausteine'][0]['bild'] = ['datei' => 'bestand/fehlt.webp'];
        $einer($s, 'bausteine.0.bild', 'unbekanntes Bestandsbild');
        $ordner = T::tmp();
        mkdir("$ordner/img");
        copy(H::testprofil() . '/bilder/beispiel-video.webp', "$ordner/img/eigenes.webp");
        $s['bausteine'][0]['bild'] = ['datei' => 'img/eigenes.webp'];
        $einer($s, 'bausteine.0.bild.alt', 'eigenes Bild ohne Alt-Text', $ordner);
        $s['bausteine'][0]['bild'] = ['datei' => 'img/eigenes.webp', 'alt' => 'Ein Beispielbild'];
        T::eq(H::texte($pruefe($s, $ordner)), '', 'mit Alt-Text gültig');
        $s['bausteine'][0]['bild'] = ['datei' => 'img/eigenes.webp', 'alt' => '', 'dekorativ' => true];
        T::eq(H::texte($pruefe($s, $ordner)), '', 'dekorativ ohne Alt-Text gültig');
        $s['bausteine'][0]['bild'] = ['datei' => 'img/fehlt.webp', 'alt' => 'x'];
        $einer($s, 'bausteine.0.bild', 'eigenes Bild fehlt', $ordner);
        $s['bausteine'][0]['bild'] = ['datei' => '../profil/logo.svg', 'alt' => 'x'];
        $einer($s, 'bausteine.0.bild', 'Pfad aus dem Ordner hinaus', $ordner);
        $s['bausteine'][0]['bild'] = ['datei' => 'img/../img/eigenes.webp', 'alt' => 'x'];
        $einer($s, 'bausteine.0.bild', 'Pfad mit .. innerhalb von img/', $ordner);
        $s['bausteine'][0]['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'breite' => 3];
        $einer($s, 'bausteine.0.bild.breite', 'unbekannter Schlüssel im Bild', $ordner);
    },
];
