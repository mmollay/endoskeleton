<?php
/**
 * Drei Knöpfe oben im Kopfbereich (Feld `schnellwahl`, seit 3.76.0; in 3.77.0 nach Alberts Zahlen neu: 95 % Handy, 89 % unter 30 s):
 * 1 „Jetzt zum nächsten Live-Zoom anmelden“ (Sprung zur Anmeldung), 2 „Echte Erfolgsberichte ansehen“ (eine https-Adresse im selben Fenster, je
 * utm_campaign einstellbar, sonst der Sprung zu Erfahrungen oder Video) und 3 „Zoom-Aufzeichnung sofort ansehen“ (YouTube erst nach einem Tipp, darunter
 * ein zweiter Anmeldeknopf). Der Zoom-Link ohne E-Mail (3.76.0) ist entfallen. Dazu: Hinweis statt Pflicht-Häkchen (Single-Opt-In) und fbclid fürs
 * Panel — php8.2 tests/run.php schnellwahl
 *
 *   SW01 Katalog: Felder und Klassen (seit 3.77.0), der Zoom-Platz ist weg, Beispiel unverändert
 *   SW02 Ohne das Feld (oder false): kein Knopf-Stapel, der Knopf des Kopfbereichs wie bisher
 *   SW03 Alle drei: Reihenfolge, Texte, Ziele, kein neues Fenster; die Aufzeichnung mit lokalem Startbild, ohne Verbindung zu Google, mit zweitem Anmeldeknopf
 *   SW04 Knopf 2 ohne eigene Adresse: Sprung zu Erfahrungen vor Video; ohne beides kein Knopf 2; ohne Anmeldung kein 1
 *   SW05 Kampagnen: Zuordnung als Datenattribut; Schema prüft https, Hosts, Kampagnen-Form und Abhängigkeiten
 *   SW06 Schema: schnellwahl muss ja/nein sein
 *   SW07 lp.js: Adresse je Kampagne nur https, Klick je Knopf, fbclid ins Formular; kein Zoom-Abruf, kein Tag Manager, kein Pixel dazu
 *   SW08 CSS und Musterbuch
 *   SW09 Anmeldung: Hinweis statt Pflicht-Häkchen (einwilligung_hinweis), Kurzsumme des Hinweises; Newsletter-Häkchen bleibt
 *   SW10 Anmeldung: verstecktes Feld fbclid (leer im HTML)
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Schema;

$k = Katalog::laden();
$schema = static fn (array $s, string $ordner): string => H::texte(Schema::pruefen($s, $k, Profil::laden(H::testprofil()), $ordner));
/** Seite: Kopfbereich (Beispiel + $hero), dann $mitte, dann Anmeldung (wenn $anmeldung, mit $form). */
$seite = static function (array $hero = [], array $mitte = [], bool $anmeldung = true, array $form = []) use ($k): array {
    $s = H::seite($mitte);
    $s['bausteine'][0] = array_filter($hero + $s['bausteine'][0], static fn ($w) => $w !== null);
    if (!$anmeldung) {
        array_pop($s['bausteine']);
    } else {
        $s['bausteine'][count($s['bausteine']) - 1] = $form + $s['bausteine'][count($s['bausteine']) - 1];
    }
    return $s;
};
$b = static fn (string $typ, array $mehr = []): array => array_filter($mehr + $k->baustein($typ)['beispiel'], static fn ($w) => $w !== null);
$bau = static function (array $plan): array {
    $o = H::ordner($plan);
    T::eq(H::texte(Bauer::bauen($o)->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$o/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d)];
};
$knoepfe = static fn (DOMXPath $x): array => array_map(static fn (DOMElement $e): array => [$e->getAttribute('data-lp-knopf'), $e->getAttribute('href'), trim($e->textContent)],
    iterator_to_array($x->query('//nav[contains(@class,"lp-schnellwahl")]//a[@data-lp-knopf]')));
$css = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.css');
$js = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.js');
const SW_1 = 'Jetzt zum nächsten Live-Zoom anmelden';
const SW_2 = 'Echte Erfolgsberichte ansehen';
const SW_3 = 'Zoom-Aufzeichnung sofort ansehen';
const SW_YT = 'dQw4w9WgXcQ';
const SW_ZIEL = 'https://beispiel.invalid/erfolge';
$aufzeichnung = ['aufzeichnung_kennung' => SW_YT, 'aufzeichnung_bild' => ['datei' => 'bestand/beispiel-vortrag.webp', 'alt' => 'Ausschnitt aus der Aufzeichnung']];

return [
    'SW01 Katalog: Felder und Klassen seit 3.77.0, der Zoom-Platz ist weg, Beispiel unverändert' => static function () use ($k): void {
        $hero = $k->baustein('hero');
        $f = $hero['felder'];
        T::eq($f['schnellwahl']['typ'] ?? null, 'janein', 'schnellwahl bleibt janein');
        T::eq($f['schnellwahl']['seit'] ?? null, '3.76.0', 'schnellwahl seit 3.76.0');
        foreach (['bericht_ziel' => 'url', 'bericht_kampagnen' => 'liste', 'aufzeichnung_kennung' => 'kennung', 'aufzeichnung_bild' => 'bild'] as $name => $typ) {
            T::eq($f[$name]['typ'] ?? null, $typ, "$name: Typ $typ");
            T::eq($f[$name]['seit'] ?? null, '3.77.0', "$name: seit 3.77.0");
            T::eq($f[$name]['nur_wenn'] ?? null, ['schnellwahl' => true], "$name: nur mit schnellwahl");
        }
        T::ok(!str_contains((string) json_encode($f['schnellwahl']), 'ohne E-Mail beim Zoom'), 'der Zoom-Knopf ohne E-Mail ist aus der Beschreibung');
        T::ok(!isset($hero['beispiel']['schnellwahl']), 'das Katalog-Beispiel bleibt ohne Stapel');
        T::eq($hero['geaendert'] ?? null, '3.77.0', 'Kopfbereich geändert 3.77.0');
        T::eq(array_values(array_intersect(array_keys($hero['klassen']), ['lp-schnellwahl-zoom', 'lp-schnellwahl-wartet'])), [], 'die Klassen des Zoom-Platzes sind weg');
        foreach (['lp-schnellwahl' => '3.76.0', 'lp-schnellwahl-knopf' => '3.76.0', 'lp-aufzeichnung' => '3.77.0', 'lp-aufzeichnung-titel' => '3.77.0'] as $klasse => $seit) {
            T::eq($hero['klassen'][$klasse] ?? null, $seit, "Klasse $klasse seit $seit");
        }
        $anm = $k->baustein('anmeldung');
        T::eq($anm['felder']['einwilligung_hinweis']['typ'] ?? null, 'janein', 'Anmeldung: einwilligung_hinweis janein');
        T::eq($anm['felder']['einwilligung_hinweis']['seit'] ?? null, '3.77.0', 'Anmeldung: seit 3.77.0');
        T::eq($anm['klassen']['lp-einwilligung-hinweis'] ?? null, '3.77.0', 'Klasse lp-einwilligung-hinweis seit 3.77.0');
    },

    'SW02 Ohne das Feld oder mit false: kein Stapel, der Knopf des Kopfbereichs wie bisher' => static function () use ($seite, $b, $bau): void {
        foreach ([[], ['schnellwahl' => false]] as $hero) {
            [$html, $x] = $bau($seite($hero, [$b('termin'), $b('erfahrungen')]));
            T::ok(!str_contains($html, 'lp-schnellwahl') && !str_contains($html, 'data-lp-knopf') && !str_contains($html, 'lp-aufzeichnung'), 'kein Stapel, keine Aufzeichnung, kein Klick-Merkmal');
            T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")]')->length, 1, 'der Knopf des Kopfbereichs steht da');
        }
    },

    'SW03 Alle drei: Reihenfolge, Ziele, kein neues Fenster; Aufzeichnung erst nach dem Tipp, zweiter Anmeldeknopf' => static function () use ($seite, $b, $bau, $knoepfe, $aufzeichnung): void {
        [$html, $x] = $bau($seite(['schnellwahl' => true, 'bericht_ziel' => SW_ZIEL] + $aufzeichnung, [$b('termin'), $b('erfahrungen')]));
        $nav = $x->query('//section[contains(@class,"lp-hero")]//nav[contains(@class,"lp-schnellwahl")]');
        T::eq($nav->length, 1, 'genau ein Stapel im Kopfbereich');
        $nav = $nav->item(0);
        T::ok($nav instanceof DOMElement && $nav->getAttribute('aria-label') !== '', 'nav mit Namen');
        $vorher = $nav?->previousSibling;
        while ($vorher !== null && !$vorher instanceof DOMElement) {
            $vorher = $vorher->previousSibling;
        }
        T::eq($vorher?->nodeName, 'h1', 'der Stapel steht direkt nach der Überschrift');
        T::eq($knoepfe($x), [['anmelden', '#anmeldung', SW_1], ['bericht', SW_ZIEL, SW_2], ['aufzeichnung', '#lp-a1-aufzeichnung', SW_3]], 'drei Knöpfe mit Ziel und Text');
        T::eq($x->query('//a[@target]')->length, 0, 'kein Link öffnet ein neues Fenster (Zurück-Taste im Facebook-Browser)');
        T::eq($x->query('//*[@data-lp-zoom]')->length, 0, 'kein Zoom-Platz mehr');
        $kn = iterator_to_array($x->query('//nav[contains(@class,"lp-schnellwahl")]//a'));
        T::ok(count($kn) === 3 && array_reduce($kn, static fn (bool $c, DOMElement $e): bool => $c && (bool) preg_match('/^btn btn-[a-z]+ lp-knopf lp-schnellwahl-knopf$/', $e->getAttribute('class')), true), 'jeder Knopf: btn btn-… lp-knopf lp-schnellwahl-knopf');
        T::ok(str_contains($x->query('//a[@data-lp-knopf="anmelden"]')->item(0)?->getAttribute('class') ?? '', 'btn-primary'), 'Knopf 1 ist der Hauptknopf');
        $auf = $x->query('//section[contains(@class,"lp-hero")]//*[@id="lp-a1-aufzeichnung"][contains(@class,"lp-aufzeichnung")]');
        T::eq($auf->length, 1, 'die Aufzeichnung steht im Kopfbereich, Ziel von Knopf 3');
        $a = $auf->item(0);
        T::eq($x->query('.//button[contains(@class,"lp-video-start")][@data-lp-kennung="' . SW_YT . '"]//img', $a)->length, 1, 'Startbild in einem Knopf mit der Kennung (zwei Klicks)');
        T::eq($x->query('.//img', $a)->item(0)?->getAttribute('src'), 'es/bilder/beispiel-vortrag.webp', 'das Startbild liegt im Seitenordner');
        foreach ($x->query('//*[@src or @srcset]') as $e) {
            T::ok(!preg_match('#(^|,\s*)(https?:)?//#', $e->getAttribute('src') . ',' . $e->getAttribute('srcset')), 'nur lokale Quellen');
        }
        T::eq($x->query('.//*[contains(@class,"lp-video-link")]', $a)->length, 1, 'Hinweis vor dem Tipp');
        T::eq($x->query('.//iframe', $a)->length + $x->query('//iframe')->length, 0, 'kein iframe vor dem Tipp');
        T::ok(!preg_match('~(src|srcset|poster|data)=["\'][^"\']*(ytimg|youtube-nocookie|youtube\.com/embed|googleapis|gstatic|google\.)~i', $html), 'nichts lädt von Google vor dem Tipp');
        $zweiter = $x->query('.//a[@href="#anmeldung"][contains(@class,"btn-primary")]', $a);
        T::eq($zweiter->length, 1, 'unter der Aufzeichnung ein zweiter Anmeldeknopf');
        T::eq(trim((string) $zweiter->item(0)?->textContent), SW_1, 'mit demselben Text wie Knopf 1');
        T::ok(!$zweiter->item(0)?->hasAttribute('data-lp-knopf'), 'der zweite Anmeldeknopf zählt nicht als vierter Knopf');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")][not(@data-lp-knopf)][not(ancestor::*[contains(@class,"lp-aufzeichnung")])]')->length, 0, 'der Knopf des Kopfbereichs entfällt (Knopf 1 ersetzt ihn)');
    },

    'SW04 Knopf 2 ohne eigene Adresse: Erfahrungen vor Video, sonst keiner; ohne Anmeldung kein Knopf 1' => static function () use ($seite, $b, $bau, $knoepfe): void {
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin'), $b('video'), $b('erfahrungen', ['anker' => 'stimmen'])]));
        T::eq($knoepfe($x)[1] ?? null, ['bericht', '#stimmen', SW_2], 'Erfahrungen mit Anker: Sprung zum Anker, auch wenn ein Video davor steht');
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin'), $b('video')]));
        T::eq($knoepfe($x)[1] ?? null, ['bericht', '#lp-a3-titel', SW_2], 'nur Video: Sprung zu seiner Überschrift');
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin')]));
        T::eq(array_column($knoepfe($x), 0), ['anmelden'], 'ohne Adresse, Erfahrungen und Video: kein Knopf 2');
        [$html, $x] = $bau($seite(['schnellwahl' => true, 'knopf' => ['text' => 'Mehr erfahren', 'ziel' => 'https://beispiel.invalid/mehr']], [$b('termin'), $b('erfahrungen')], false));
        T::eq(array_column($knoepfe($x), 0), ['bericht'], 'ohne Anmeldung: nur Knopf 2');
        [$html, $x] = $bau($seite(['schnellwahl' => true, 'knopf' => ['text' => 'Mehr erfahren', 'ziel' => 'https://beispiel.invalid/mehr']], [$b('termin')], false));
        T::ok(!str_contains($html, 'lp-schnellwahl'), 'kein Knopf möglich: kein leerer Stapel');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")]')->length, 1, 'dann bleibt der Knopf des Kopfbereichs');
    },

    'SW05 Kampagnen: Zuordnung als Datenattribut; Schema prüft https, Hosts, Form und Abhängigkeiten' => static function () use ($seite, $b, $bau, $schema, $aufzeichnung): void {
        $kamp = [['kampagne' => 'herbst-2026', 'ziel' => 'https://beispiel.invalid/b2'], ['kampagne' => 'video_a', 'ziel' => 'https://beispiel.invalid/b3?x=1&y=2']];
        [, $x] = $bau($seite(['schnellwahl' => true, 'bericht_ziel' => SW_ZIEL, 'bericht_kampagnen' => $kamp], [$b('termin')]));
        $knopf = $x->query('//a[@data-lp-knopf="bericht"]')->item(0);
        T::eq($knopf?->getAttribute('href'), SW_ZIEL, 'ohne Skript: die Vorgabe');
        T::eq(json_decode((string) $knopf?->getAttribute('data-lp-ziele'), true), ['herbst-2026' => 'https://beispiel.invalid/b2', 'video_a' => 'https://beispiel.invalid/b3?x=1&y=2'], 'Zuordnung Kampagne → Adresse');
        [$html] = $bau($seite(['schnellwahl' => true, 'bericht_ziel' => SW_ZIEL], [$b('termin')]));
        T::ok(!str_contains($html, 'data-lp-ziele'), 'ohne Zuordnung kein Datenattribut');
        $o = H::ordner($seite());
        $f = static fn (array $hero): string => $schema($seite($hero, [$b('termin')]), $o);
        T::eq($f(['schnellwahl' => true, 'bericht_ziel' => SW_ZIEL, 'bericht_kampagnen' => $kamp] + $aufzeichnung), '', 'alles richtig: gültig');
        T::ok(str_contains($f(['schnellwahl' => true, 'bericht_ziel' => 'http://beispiel.invalid/x']), '[sperrend] bausteine.0.bericht_ziel'), 'http: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'bericht_ziel' => 'https://fremd.invalid/x']), '[sperrend] bausteine.0.bericht_ziel'), 'fremder Host: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'bericht_ziel' => 'javascript:alert(1)']), '[sperrend] bausteine.0.bericht_ziel'), 'javascript: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'bericht_kampagnen' => $kamp]), '[sperrend] bausteine.0.bericht_kampagnen'), 'Kampagnen ohne Vorgabe: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'bericht_ziel' => SW_ZIEL, 'bericht_kampagnen' => [['kampagne' => 'Herbst!', 'ziel' => SW_ZIEL]]]), '[sperrend] bausteine.0.bericht_kampagnen.0.kampagne'), 'Kampagne in falscher Form: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'bericht_ziel' => SW_ZIEL, 'bericht_kampagnen' => [['kampagne' => 'a', 'ziel' => 'http://beispiel.invalid/']]]), '[sperrend] bausteine.0.bericht_kampagnen.0.ziel'), 'Kampagnen-Ziel ohne https: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'aufzeichnung_kennung' => SW_YT]), '[sperrend] bausteine.0.aufzeichnung_kennung'), 'Aufzeichnung ohne Startbild: sperrend');
        T::ok(str_contains($f(['schnellwahl' => true, 'aufzeichnung_kennung' => 'zu-kurz']), '[sperrend] bausteine.0.aufzeichnung_kennung'), 'Kennung in falscher Form: sperrend');
        T::ok(str_contains($f(['bericht_ziel' => SW_ZIEL]), '[sperrend] bausteine.0.bericht_ziel'), 'ohne schnellwahl: sperrend');
    },

    'SW06 Schema: schnellwahl muss ja/nein sein' => static function () use ($seite, $schema): void {
        $o = H::ordner($seite());
        T::eq($schema($seite(['schnellwahl' => true]), $o), '', 'true: gültig');
        T::eq($schema($seite(['schnellwahl' => false]), $o), '', 'false: gültig');
        T::ok(str_contains($schema($seite(['schnellwahl' => 'ja']), $o), '[sperrend] bausteine.0.schnellwahl'), '„ja“ als Text: sperrend');
    },

    'SW07 lp.js: Adresse je Kampagne nur https, Klick je Knopf, fbclid; kein Zoom-Abruf, kein Tag Manager, kein Pixel dazu' => static function () use ($js): void {
        $s = $js();
        T::ok(!str_contains($s, "'zoom')") && !str_contains($s, 'lpZoom'), 'kein Abruf eines Zoom-Links mehr');
        T::ok(str_contains($s, 'lpZiele') && str_contains($s, "get('utm_campaign')"), 'Knopf 2: Adresse nach utm_campaign');
        T::ok((bool) preg_match('~lpZiele.*?/\^https:\\\\/\\\\//\.test\(~s', $s), 'nur https-Adressen werden übernommen');
        T::ok(!preg_match("~target\s*=|'_blank'~", $s), 'kein neues Fenster');
        T::ok(str_contains($s, "melden('klick'") && str_contains($s, 'lpKnopf'), 'Klick-Ereignis mit dem Namen des Knopfs');
        T::ok(str_contains($s, "get('fbclid')") && str_contains($s, '[A-Za-z0-9_-]'), 'fbclid nur in erlaubter Form ins Formular');
        T::ok(!preg_match('~googletagmanager|gtag\(|dataLayer~', $s), 'kein Google Tag Manager');
        T::eq(substr_count($s, 'fbq('), substr_count((string) shell_exec('git -C ' . escapeshellarg(H::wurzel()) . ' show 5a1b4a5:lp/lp.js 2>/dev/null'), 'fbq('), 'kein zusätzlicher Meta-Pixel-Aufruf');
    },

    'SW08 CSS und Musterbuch' => static function () use ($css): void {
        $c = $css();
        foreach (['.lp-schnellwahl', '.lp-schnellwahl-knopf', '.lp-aufzeichnung', '.lp-einwilligung-hinweis'] as $sel) {
            T::ok(str_contains($c, $sel), "CSS gestaltet $sel");
        }
        T::ok(!str_contains($c, 'lp-schnellwahl-zoom') && !str_contains($c, 'lp-schnellwahl-wartet'), 'keine Regeln für den Zoom-Platz mehr');
        T::ok((bool) preg_match('~\.lp-schnellwahl-knopf[^{]*\{[^}]*min-height:\s*2\.75rem~s', $c), 'Tippfläche mindestens 2,75 rem (44 px)');
        preg_match_all('~/\* Drei Knöpfe.*?(?=\n/\* [A-ZÄÖÜ]|\z)~s', $c, $m);
        T::ok(isset($m[0][0]) && !preg_match('~#[0-9a-fA-F]{3,6}\b|rgb\(~', $m[0][0]), 'nur Tokens, keine festen Farben');
        $muster = (string) @file_get_contents(H::wurzel() . '/lp/musterbuch/hero--schlicht--schnellwahl.json');
        T::ok(str_contains($muster, '"schnellwahl": true') && str_contains($muster, '"bericht_ziel"') && str_contains($muster, '"aufzeichnung_kennung"'), 'Musterbuch zeigt alle drei Knöpfe');
        T::ok(str_contains($muster, '"einwilligung_hinweis": true'), 'Musterbuch zeigt den Hinweis statt des Häkchens');
    },

    'SW09 Anmeldung: Hinweis statt Pflicht-Häkchen, Kurzsumme des Hinweises; Newsletter-Häkchen bleibt' => static function () use ($seite, $b, $bau): void {
        $profil = json_decode((string) file_get_contents(H::testprofil() . '/profil.json'), true);
        [$html, $x] = $bau($seite([], [], true, ['einwilligung_hinweis' => true, 'newsletter' => true]));
        T::eq($x->query('//input[@name="einwilligung"][@type="checkbox"]')->length, 0, 'kein Pflicht-Häkchen');
        T::eq($x->query('//input[@name="einwilligung"][@type="hidden"][@value="hinweis"]')->length, 1, 'einwilligung=hinweis als verstecktes Feld');
        $hinweis = $x->query('//form//p[contains(@class,"lp-einwilligung-hinweis")]');
        T::eq($hinweis->length, 1, 'ein Hinweis im Formular');
        $text = Profil::hinweisText($profil);
        T::ok(str_contains(trim((string) $hinweis->item(0)?->textContent), $text), 'der Hinweistext des Profils');
        T::ok(str_contains($text, 'nur für diesen Vortrag'), 'der Hinweis nennt den Zweck');
        T::eq($x->query('.//a[@href="datenschutz.html"]', $hinweis->item(0))->length, 1, 'mit Link zur Datenschutzerklärung');
        T::eq($x->query('//input[@name="einwilligung_kurz"]')->item(0)?->getAttribute('value'), substr(hash('sha256', $text), 0, 12), 'Kurzsumme des Hinweistexts (Nachweis im Panel)');
        T::eq($x->query('//input[@name="newsletter"][@type="checkbox"]')->length, 1, 'das freiwillige Newsletter-Häkchen bleibt');
        T::eq($x->query('//*[@required]')->length, $x->query('//input[@type!="checkbox"][@required]')->length, 'kein Häkchen ist Pflicht');
        T::ok(str_contains($html, 'name="website"'), 'der Honigtopf bleibt');
        [, $x] = $bau($seite());
        T::eq($x->query('//input[@name="einwilligung"][@type="checkbox"][@required]')->length, 1, 'ohne das Feld: das Pflicht-Häkchen wie bisher');
        T::eq($x->query('//p[contains(@class,"lp-einwilligung-hinweis")]')->length, 0, 'ohne das Feld: kein Hinweis');
        $sie = Profil::hinweisText(['anrede' => 'Sie'] + $profil);
        T::ok(str_contains($sie, 'Ihre Angaben') && $sie !== $text, 'Anrede Sie: eigener Wortlaut');
    },

    'SW10 Anmeldung: verstecktes Feld fbclid, im HTML leer' => static function () use ($seite, $bau): void {
        [, $x] = $bau($seite());
        $f = $x->query('//form//input[@type="hidden"][@name="fbclid"]');
        T::eq($f->length, 1, 'ein verstecktes Feld fbclid');
        T::eq($f->item(0)?->getAttribute('value'), '', 'leer — erst lp.js trägt es aus der Adresse ein');
    },
];
