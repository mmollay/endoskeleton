<?php
/**
 * Listen-Bausteine (3.71.0): preise (angebote[]), ablauf (schritte[]), zahlen (eintraege[]), team (personen[]), zeiten (zeilen[]),
 * referenzen (eintraege[]), vergleich (paare[]).
 *
 *   LB01  Katalog: Listenfelder mit Grenzen und Feldern je Eintrag (die Angaben, aus denen das Panel seinen Feldtyp baut); keine Felder „preis“/„termine“, kein *_wenn
 *   LB02  Markup je Baustein: Liste mit role="list" bzw. <ol>/<dl>, je Eintrag die Klassen, Überschriften H3 unter der H2, jeder Text genau einmal
 *   LB03  Grenzen der Listen sperren (zu wenige, zu viele Einträge, unbekanntes Feld im Eintrag) — mit „Eintrag N“ im Kundentext
 *   LB04  Bilder in Listen: Bildrechte (freigegeben) und Alt-Texte gelten auch für Bilder in Einträgen (bis 3.70.0 nur oben im Baustein)
 *   LB05  Referenzen als Leiste: beschrifteter, mit der Tastatur erreichbarer Bereich; als Raster ohne tabindex
 *   LB06  Vorher und Nachher: jedes Bild sichtbar beschriftet, Alt-Text aus dem Bestand oder dem Bauplan
 *   LB07  Maskierung: Angriffstexte in allen Listenfeldern erscheinen maskiert
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;

const LB_HEUTE = '2026-09-27T12:00';
const LB_LISTEN = ['preise' => 'angebote', 'ablauf' => 'schritte', 'zahlen' => 'eintraege', 'team' => 'personen', 'zeiten' => 'zeilen', 'referenzen' => 'eintraege', 'vergleich' => 'paare'];

$k = Katalog::laden();
/** Baut die Seite mit dem Baustein und gibt [Befunde, XPath von index.html, Ordner] zurück. */
$bau = static function (array $baustein, ?callable $profil = null): array {
    $p = null;
    if ($profil) { $p = H::profilKopie(); $profil($p); }
    $seite = H::seite([$baustein]);
    $seite['bausteine'][0]['anker'] = 'start';   // die Fixtures des Musterbuchs zeigen mit Knöpfen auf #start
    $o = H::ordner($seite, null, $p);
    $e = Bauer::bauen($o);
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . (string) @file_get_contents("$o/index.html"));
    libxml_clear_errors();
    return [$e->befunde, new DOMXPath($d), $o];
};
$musterbuch = static fn (string $name): array => json_decode((string) file_get_contents(H::wurzel() . "/lp/musterbuch/$name.json"), true)['bausteine'][1];

return [
    'LB01 Katalog: Listenfelder mit Grenzen und Feldern je Eintrag, keine Sonderfelder für das Panel' => static function () use ($k): void {
        foreach (LB_LISTEN as $typ => $feld) {
            $e = $k->baustein($typ);
            T::ok($e !== null && $e['seit'] === '3.71.0', "$typ seit 3.71.0");
            $f = $e['felder'][$feld] ?? [];
            T::ok(($f['typ'] ?? '') === 'liste' && is_int($f['min'] ?? null) && is_int($f['max'] ?? null) && ($f['pflicht'] ?? false) === true, "$typ.$feld: Pflichtliste mit min/max");
            T::ok(is_array($f['eintrag'] ?? null) && !isset($f['eintrag']['typ']) && $f['eintrag'] !== [], "$typ.$feld: Einträge sind Objekte mit Feldern");
            $json = json_encode($e['felder']);
            T::ok(!str_contains($json, '"preis"') && !str_contains($json, '"termine"'), "$typ: kein Feld „preis“ oder „termine“ (das Panel behandelt sie besonders)");
            T::ok(!str_contains($json, '_wenn'), "$typ: keine Bedingung *_wenn (das Panel versteht sie nicht)");
        }
    },
    'LB02 Markup je Baustein: Listen, Klassen, H3 unter H2, jeder Text genau einmal' => static function () use ($bau, $musterbuch): void {
        $faelle = [
            'preise--karten' => ['//ul[contains(@class,"lp-preise-liste")][@role="list"]/li[contains(@class,"lp-angebot")]', 3],
            'ablauf--schritte' => ['//ol[contains(@class,"lp-ablauf-liste")]/li[contains(@class,"lp-schritt")]', 4],
            'zahlen--band' => ['//ul[contains(@class,"lp-zahlen-liste")][@role="list"]/li[contains(@class,"lp-zahl")]', 4],
            'team--karten' => ['//ul[contains(@class,"lp-team-liste")][@role="list"]/li[contains(@class,"lp-mitglied")]', 3],
            'zeiten--tabelle' => ['//dl[contains(@class,"lp-zeiten-liste")]/div[dt[contains(@class,"lp-zeiten-tage")] and dd[contains(@class,"lp-zeiten-zeit")]]', 3],
            'referenzen--raster' => ['//ul[contains(@class,"lp-referenzen-liste")][@role="list"]/li[contains(@class,"lp-referenz")]', 3],
            'vergleich--nebeneinander' => ['//ul[contains(@class,"lp-vergleich-liste")][@role="list"]/li/figure[contains(@class,"lp-paar")]', 2],
        ];
        foreach ($faelle as $name => [$xpath, $anzahl]) {
            $b = $musterbuch($name);
            [$befunde, $x] = $bau($b);
            T::eq(H::texte(H::sperrend($befunde)), '', "$name baut");
            T::eq($x->query($xpath)->length, $anzahl, "$name: $anzahl Einträge in der Liste");
            T::eq($x->query('//section[contains(@class,"lp-' . $b['typ'] . '--' . $b['variante'] . '")]')->length, 1, "$name: Abschnitt mit Varianten-Klasse");
            T::ok($x->query('//section[contains(@class,"lp-' . $b['typ'] . '")]//h2')->length === 1, "$name: genau eine H2 (der Titel), Einträge darunter als H3");
            T::eq($x->query('//section[contains(@class,"lp-' . $b['typ'] . '")]//h4|//section[contains(@class,"lp-' . $b['typ'] . '")]//h1')->length, 0, "$name: keine H1/H4 im Abschnitt (Überschriften ohne Sprung)");
            // jeder Text des Bauplans genau einmal auf der Seite (Bearbeiten-Modus des Panels findet Text über einen Textvergleich)
            $texte = [];
            array_walk_recursive($b, static function ($w, $s) use (&$texte): void {
                if (is_string($w) && !in_array($s, ['typ', 'variante', 'datei', 'ziel', 'alt'], true) && mb_strlen($w) > 3) { $texte[] = $w; }
            });
            $sichtbar = (string) $x->query('//section[contains(concat(" ", @class, " "), " lp-' . $b['typ'] . ' ")]')->item(0)?->textContent;
            foreach (array_unique($texte) as $t) {
                T::eq(substr_count($sichtbar, $t), 1, "$name: „{$t}“ steht genau einmal sichtbar");
            }
        }
        [, $x] = $bau($musterbuch('preise--karten'));
        T::eq($x->query('//li[contains(@class,"lp-angebot--hervorgehoben")]')->length, 1, 'genau ein Angebot hervorgehoben');
        T::eq($x->query('//li[contains(@class,"lp-angebot--hervorgehoben")]//a[contains(@class,"lp-knopf")][@href="#start"]')->length, 1, 'Knopf im Angebot');
        [, $x] = $bau($musterbuch('zahlen--schlicht'));
        T::eq(array_map(static fn ($n) => $n->textContent, iterator_to_array($x->query('//p[contains(@class,"lp-zahl-wert")]'))), ['6', '90', '12'], 'Zahlen als Text, wie im Bauplan (kein Hochzählen)');
    },
    'LB03 Grenzen der Listen sperren, mit „Eintrag N“ im Kundentext' => static function () use ($bau, $k): void {
        foreach (LB_LISTEN as $typ => $feld) {
            $e = $k->baustein($typ);
            $b = $e['beispiel'];
            $eintrag = $b[$feld][0];
            $zuViele = $b;
            $zuViele[$feld] = array_fill(0, $e['felder'][$feld]['max'] + 1, $eintrag);
            [$befunde] = $bau($zuViele);
            T::contains(H::texte($befunde), "bausteine.1.$feld: " . ($e['felder'][$feld]['max'] + 1) . ' Einträge, erlaubt', "$typ: zu viele Einträge sperren");
            $fremd = $b;
            $fremd[$feld][0]['unbekannt'] = 'x';
            [$befunde] = $bau($fremd);
            T::contains(H::texte($befunde), "unbekanntes Feld unbekannt", "$typ: unbekanntes Feld im Eintrag sperrt");
            T::contains(H::texte($befunde), 'Eintrag 1', "$typ: Kundentext nennt den Eintrag");
        }
    },
    'LB04 Bilder in Listen: Bildrechte und Alt-Texte gelten auch in Einträgen' => static function () use ($bau): void {
        $team = ['typ' => 'team', 'variante' => 'karten', 'titel' => 'Team', 'personen' => [
            ['name' => 'Anna Beispiel'],
            ['name' => 'Ben Muster', 'bild' => ['datei' => 'bestand/beispiel-video.webp']],
        ]];
        $ohneFreigabe = static function (string $p): void {
            $bilder = json_decode((string) file_get_contents("$p/bilder/bilder.json"), true);
            foreach ($bilder as &$b) { if ($b['datei'] === 'beispiel-video.webp') { $b['freigegeben'] = false; $b['alt'] = 'Garantiert geheilt im Bild'; } }
            file_put_contents("$p/bilder/bilder.json", json_encode($bilder, JSON_UNESCAPED_UNICODE));
        };
        [$befunde, , $o] = $bau($team, $ohneFreigabe);
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut (die Freigabe prüft erst der Prüfer)');
        $pruefung = H::texte(Pruefer::pruefen($o, LB_HEUTE));
        T::contains($pruefung, 'bausteine.1.personen.1.bild | bestand/beispiel-video.webp ist im Bildbestand nicht freigegeben', 'Bild in einem Eintrag ohne Freigabe sperrt (Pfad mit Eintrag)');
        T::contains($pruefung, 'bausteine.1.personen.1.bild.alt | Sperrliste: „garantiert geheilt“', 'der Alt-Text eines Bestandsbildes in einem Eintrag geht durch die Sperrliste');
        [, , $o2] = $bau($team);
        T::eq(H::texte(H::sperrend(Pruefer::pruefen($o2, LB_HEUTE))), '', 'mit Freigabe: kein sperrender Befund');
        $img = ['typ' => 'team', 'variante' => 'karten', 'titel' => 'Team', 'personen' => [['name' => 'Anna Beispiel', 'bild' => ['datei' => 'img/fehlt.webp']]]];
        [$befunde] = $bau($img);
        T::contains(H::texte($befunde), 'bausteine.1.personen.0.bild: Bild img/fehlt.webp nicht gefunden', 'fehlendes Bild in einem Eintrag sperrt beim Bau');
    },
    'LB05 Referenzen als Leiste: beschrifteter, mit der Tastatur erreichbarer Bereich; als Raster ohne tabindex' => static function () use ($bau, $musterbuch): void {
        [, $x] = $bau($musterbuch('referenzen--leiste'));
        $rahmen = $x->query('//div[contains(@class,"lp-referenzen-rahmen")]')->item(0);
        T::ok($rahmen instanceof DOMElement && $rahmen->getAttribute('tabindex') === '0' && $rahmen->getAttribute('role') === 'region', 'Leiste: Bereich mit tabindex="0"');
        T::eq($rahmen?->getAttribute('aria-label'), 'Unsere Arbeiten (waagrecht scrollbar)', 'Leiste: beschriftet');
        [, $x] = $bau($musterbuch('referenzen--raster'));
        T::ok(!$x->query('//div[contains(@class,"lp-referenzen-rahmen")]')->item(0)?->hasAttribute('tabindex'), 'Raster: nicht fokussierbar (scrollt nicht)');
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        T::ok(preg_match('/\.lp-referenzen--leiste \.lp-referenzen-rahmen\s*\{[^}]*overflow-x:\s*auto;[^}]*scroll-snap-type:\s*x mandatory;/', $css) === 1, 'Leiste: Scroll-Snap ohne Skript');
        T::notContains((string) file_get_contents(H::wurzel() . '/lp/lp.js'), 'lp-referenzen', 'kein Skript für die Leiste');
    },
    'LB06 Vorher und Nachher: sichtbar beschriftet, Alt-Text aus Bestand oder Bauplan' => static function () use ($bau, $musterbuch): void {
        [, $x] = $bau($musterbuch('vergleich--nebeneinander'));
        T::eq(array_map(static fn ($n) => $n->textContent, iterator_to_array($x->query('(//figure[contains(@class,"lp-paar")])[1]//p[contains(@class,"lp-paar-marke")]'))), ['Vorher', 'Nachher'], 'jedes Bild sichtbar beschriftet');
        $alts = array_map(static fn ($n) => $n->getAttribute('alt'), iterator_to_array($x->query('//img[contains(@class,"lp-paar-bild")]')));
        T::eq(count($alts), 4, 'vier Bilder');
        T::ok(!in_array('', $alts, true), 'kein Bild ohne Beschreibung');
        T::contains(implode('|', $alts), 'Derselbe Platz nach dem Umbau', 'Alt-Text aus dem Bauplan');
        T::eq($x->query('//figure[contains(@class,"lp-paar")]/figcaption[contains(@class,"lp-paar-beschriftung")]')->length, 1, 'Beschriftung nur, wo eine steht');
    },
    'LB07 Maskierung: Angriffstexte in allen Listenfeldern' => static function () use ($bau, $k): void {
        $boese = '<img src=x onerror=alert(1)>"\'&';
        foreach (LB_LISTEN as $typ => $feld) {
            $b = $k->baustein($typ)['beispiel'];
            array_walk_recursive($b[$feld], static function (&$w, $s) use ($boese): void {
                if (is_string($w) && in_array($s, ['name', 'titel', 'text', 'betrag', 'hinweis', 'wert', 'beschriftung', 'rolle', 'tage', 'zeit', 0, 1], true) && !str_starts_with($w, 'bestand/') && !str_starts_with($w, '#')) {
                    $w = mb_substr($boese . $w, 0, 12);
                }
            });
            [, , $o] = $bau($b);
            $html = (string) @file_get_contents("$o/index.html");
            T::notContains($html, '<img src=x', "$typ: kein eingeschleustes Element");
            T::contains($html, '&lt;img src=', "$typ: maskiert");
        }
    },
];
