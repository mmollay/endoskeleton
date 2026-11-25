<?php
/**
 * Drei Knöpfe oben im Kopfbereich (3.76.0, Feld `schnellwahl`): A „Für den nächsten Zoom-Vortrag anmelden“ (Sprung zur Anmeldung), B „Jetzt ohne E-Mail
 * beim Zoom-Vortrag dabei sein“ (Zoom-Link vom Panel, nur 15 Minuten vor Beginn bis Ende; nie im HTML) und C „Echten Erfolgsbericht ansehen (ohne E-Mail)“
 * (Sprung zu Erfahrungen oder Video). Jeder Knopf meldet seinen Klick an die cookiefreie Statistik — php8.2 tests/run.php schnellwahl
 *
 *   SW01 Katalog: Feld schnellwahl (janein, seit 3.76.0), neue Klassen, Beispiel unverändert
 *   SW02 Ohne das Feld (oder false): kein Knopf-Stapel, der Knopf des Kopfbereichs wie bisher
 *   SW03 Alle drei: Reihenfolge A, B, C direkt unter der Überschrift; Ziele, Texte, data-lp-knopf; B verborgen und ohne Link; A ersetzt den Knopf des Kopfbereichs
 *   SW04 C folgt dem Inhalt: Erfahrungen vor Video, Anker vor Überschrift; ohne beides kein C
 *   SW05 B nur mit Online-Termin und Anmeldung; ohne Anmeldung weder A noch B; ohne alles kein Stapel (der Knopf des Kopfbereichs bleibt)
 *   SW06 Schema: schnellwahl muss ja/nein sein
 *   SW07 lp.js: Zoom-Link nur vom Panel (…/zoom am Formularziel, ohne Cookies), Fenster 15 Minuten, Klick-Ereignis je Knopf; kein Tag Manager, kein Pixel dazu
 *   SW08 CSS: Klassen gestaltet, Tippfläche ≥ 2,75 rem, nur Tokens; Musterbuch zeigt das Feld
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Schema;

$k = Katalog::laden();
$schema = static fn (array $s, string $ordner): string => H::texte(Schema::pruefen($s, $k, Profil::laden(H::testprofil()), $ordner));
/** Seite: Kopfbereich (Beispiel + $hero), dann $mitte, dann Anmeldung (wenn $anmeldung). */
$seite = static function (array $hero = [], array $mitte = [], bool $anmeldung = true) use ($k): array {
    $s = H::seite($mitte);
    $s['bausteine'][0] = array_filter($hero + $s['bausteine'][0], static fn ($w) => $w !== null);
    if (!$anmeldung) {
        array_pop($s['bausteine']);
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
const SW_A = 'Für den nächsten Zoom-Vortrag anmelden';
const SW_B = 'Jetzt ohne E-Mail beim Zoom-Vortrag dabei sein';
const SW_C = 'Echten Erfolgsbericht ansehen (ohne E-Mail)';

return [
    'SW01 Katalog: schnellwahl im Kopfbereich (janein, seit 3.76.0), neue Klassen, Beispiel unverändert' => static function () use ($k): void {
        $hero = $k->baustein('hero');
        $f = $hero['felder']['schnellwahl'] ?? null;
        T::ok(is_array($f), 'Feld schnellwahl im Kopfbereich');
        T::eq($f['typ'] ?? null, 'janein', 'Typ janein');
        T::eq($f['seit'] ?? null, '3.76.0', 'seit 3.76.0');
        T::ok(str_contains((string) ($f['fehlt'] ?? ''), 'Zoom'), 'fehlt erklärt, was ohne das Feld passiert');
        T::ok(!isset($hero['beispiel']['schnellwahl']), 'das Katalog-Beispiel bleibt ohne Stapel');
        T::eq($hero['geaendert'] ?? null, '3.76.0', 'Kopfbereich geändert 3.76.0');
        foreach (['lp-schnellwahl', 'lp-schnellwahl-knopf', 'lp-schnellwahl-zoom', 'lp-schnellwahl-wartet'] as $klasse) {
            T::eq($hero['klassen'][$klasse] ?? null, '3.76.0', "Klasse $klasse seit 3.76.0");
        }
    },

    'SW02 Ohne das Feld oder mit false: kein Stapel, der Knopf des Kopfbereichs wie bisher' => static function () use ($seite, $b, $bau): void {
        foreach ([[], ['schnellwahl' => false]] as $hero) {
            [$html, $x] = $bau($seite($hero, [$b('termin'), $b('erfahrungen')]));
            T::ok(!str_contains($html, 'lp-schnellwahl') && !str_contains($html, 'data-lp-knopf'), 'kein Stapel, kein Klick-Merkmal');
            T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")]')->length, 1, 'der Knopf des Kopfbereichs steht da');
        }
    },

    'SW03 Alle drei: A, B, C unter der Überschrift; B verborgen ohne Link; A ersetzt den Knopf des Kopfbereichs' => static function () use ($seite, $b, $bau, $knoepfe): void {
        [$html, $x] = $bau($seite(['schnellwahl' => true], [$b('termin'), $b('erfahrungen')]));
        $nav = $x->query('//section[contains(@class,"lp-hero")]//nav[contains(@class,"lp-schnellwahl")]');
        T::eq($nav->length, 1, 'genau ein Stapel im Kopfbereich');
        $nav = $nav->item(0);
        T::ok($nav instanceof DOMElement && $nav->getAttribute('aria-label') !== '', 'nav mit Namen');
        $vorher = $nav?->previousSibling;
        while ($vorher !== null && !$vorher instanceof DOMElement) {
            $vorher = $vorher->previousSibling;
        }
        T::eq($vorher?->nodeName, 'h1', 'der Stapel steht direkt nach der Überschrift');
        T::eq($knoepfe($x), [['anmelden', '#anmeldung', SW_A], ['bericht', '#lp-a3-titel', SW_C]], 'A und C mit Ziel und Text (B kommt erst vom Skript)');
        $zoom = $x->query('//nav[contains(@class,"lp-schnellwahl")]/*[contains(@class,"lp-schnellwahl-zoom")]');
        T::eq($zoom->length, 1, 'B: der Platz für den Zoom-Knopf ist da');
        $z = $zoom->item(0);
        T::ok($z instanceof DOMElement && $z->hasAttribute('hidden') && $z->hasAttribute('data-lp-zoom'), 'B ist verborgen (ohne Skript nie sichtbar) und trägt data-lp-zoom');
        T::eq($z?->getAttribute('data-lp-zoom-text'), SW_B, 'B: der Text des Knopfs liegt bereit');
        T::eq($x->query('.//a', $z)->length, 0, 'B: kein Link im HTML');
        T::eq($x->query('.//*[contains(@class,"lp-schnellwahl-wartet")][@aria-live="polite"]', $z)->length, 1, 'B: Wartetext mit aria-live');
        T::ok(!preg_match('~zoom\.us|https://[^"]*zoom~i', $html), 'kein Zoom-Link irgendwo im HTML');
        $kn = iterator_to_array($x->query('//nav[contains(@class,"lp-schnellwahl")]//a'));
        T::ok($kn !== [] && array_reduce($kn, static fn (bool $c, DOMElement $e): bool => $c && (bool) preg_match('/^btn btn-[a-z]+ lp-knopf lp-schnellwahl-knopf$/', $e->getAttribute('class')), true), 'jeder Knopf: btn btn-… lp-knopf lp-schnellwahl-knopf');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")][not(@data-lp-knopf)]')->length, 0, 'der Knopf des Kopfbereichs entfällt (A ersetzt ihn)');
        T::ok(str_contains($x->query('//a[@data-lp-knopf="anmelden"]')->item(0)?->getAttribute('class') ?? '', 'btn-primary'), 'A ist der Hauptknopf');
    },

    'SW04 C folgt dem Inhalt: Erfahrungen vor Video, Anker vor Überschrift; ohne beides kein C' => static function () use ($seite, $b, $bau, $knoepfe): void {
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin'), $b('video'), $b('erfahrungen', ['anker' => 'stimmen'])]));
        T::eq($knoepfe($x)[1] ?? null, ['bericht', '#stimmen', SW_C], 'Erfahrungen mit Anker: Sprung zum Anker, auch wenn ein Video davor steht');
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin'), $b('video')]));
        T::eq($knoepfe($x)[1] ?? null, ['bericht', '#lp-a3-titel', SW_C], 'nur Video: Sprung zu seiner Überschrift');
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin')]));
        T::eq(array_column($knoepfe($x), 0), ['anmelden'], 'ohne Erfahrungen und Video: kein C');
    },

    'SW05 B nur mit Online-Termin und Anmeldung; ohne Anmeldung weder A noch B; ohne alles kein Stapel' => static function () use ($seite, $b, $bau, $knoepfe): void {
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('erfahrungen')]));
        T::eq($x->query('//*[@data-lp-zoom]')->length, 0, 'ohne Termin: kein B');
        [, $x] = $bau($seite(['schnellwahl' => true], [$b('termin', ['art' => 'vor_ort', 'ort' => 'Graz, Saal 1'])]));
        T::eq($x->query('//*[@data-lp-zoom]')->length, 0, 'Termin vor Ort: kein B');
        [$html, $x] = $bau($seite(['schnellwahl' => true, 'knopf' => ['text' => 'Mehr erfahren', 'ziel' => 'https://beispiel.invalid/mehr']], [$b('termin'), $b('erfahrungen')], false));
        T::eq(array_column($knoepfe($x), 0), ['bericht'], 'ohne Anmeldung: nur C');
        T::eq($x->query('//*[@data-lp-zoom]')->length, 0, 'ohne Anmeldung: kein B');
        [$html, $x] = $bau($seite(['schnellwahl' => true, 'knopf' => ['text' => 'Mehr erfahren', 'ziel' => 'https://beispiel.invalid/mehr']], [$b('termin')], false));
        T::ok(!str_contains($html, 'lp-schnellwahl'), 'kein Knopf möglich: kein leerer Stapel');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")]')->length, 1, 'dann bleibt der Knopf des Kopfbereichs');
    },

    'SW06 Schema: schnellwahl muss ja/nein sein' => static function () use ($seite, $schema): void {
        $o = H::ordner($seite());
        T::eq($schema($seite(['schnellwahl' => true]), $o), '', 'true: gültig');
        T::eq($schema($seite(['schnellwahl' => false]), $o), '', 'false: gültig');
        T::ok(str_contains($schema($seite(['schnellwahl' => 'ja']), $o), '[sperrend] bausteine.0.schnellwahl'), '„ja“ als Text: sperrend');
    },

    'SW07 lp.js: Zoom-Link nur vom Panel, Fenster 15 Minuten, Klick je Knopf; kein Tag Manager, kein Pixel dazu' => static function () use ($js): void {
        $s = $js();
        T::ok(str_contains($s, "replace(/anmelden$/, 'zoom')"), 'Quelle: Formularziel …/anmelden → …/zoom (wie die Statistik)');
        T::ok((bool) preg_match("~quelle\\.pathname = quelle\\.pathname\\.replace\\(/anmelden\\$/, 'zoom'\\).*fetch\\(quelle\\.href, \\{ credentials: 'omit'~s", $s), 'Abfrage ohne Cookies');
        T::ok(str_contains($s, '15 * 60000'), 'Fenster: 15 Minuten vor Beginn');
        T::ok(str_contains($s, "/^https:\\/\\//.test(") , 'nur https-Links werden zum Knopf');
        T::ok(str_contains($s, "melden('klick'") && str_contains($s, 'lpKnopf'), 'Klick-Ereignis mit dem Namen des Knopfs');
        T::ok(!preg_match('~googletagmanager|gtag\(|dataLayer~', $s), 'kein Google Tag Manager');
        T::eq(substr_count($s, 'fbq('), substr_count((string) shell_exec('git -C ' . escapeshellarg(H::wurzel()) . ' show 5a1b4a5:lp/lp.js 2>/dev/null'), 'fbq('), 'kein zusätzlicher Meta-Pixel-Aufruf');
    },

    'SW08 CSS und Musterbuch' => static function () use ($css): void {
        $c = $css();
        foreach (['.lp-schnellwahl', '.lp-schnellwahl-knopf', '.lp-schnellwahl-wartet'] as $sel) {
            T::ok(str_contains($c, $sel), "CSS gestaltet $sel");
        }
        T::ok((bool) preg_match('~\.lp-schnellwahl-knopf[^{]*\{[^}]*min-height:\s*2\.75rem~s', $c), 'Tippfläche mindestens 2,75 rem (44 px)');
        preg_match_all('~/\* Drei Knöpfe.*?(?=\n/\* [A-ZÄÖÜ]|\z)~s', $c, $m);
        T::ok(isset($m[0][0]) && !preg_match('~#[0-9a-fA-F]{3,6}\b|rgb\(~', $m[0][0]), 'nur Tokens, keine festen Farben');
        $muster = H::wurzel() . '/lp/musterbuch/hero--schlicht--schnellwahl.json';
        T::ok(is_file($muster) && str_contains((string) file_get_contents($muster), '"schnellwahl": true'), 'Musterbuch-Beispiel hero--schlicht--schnellwahl');
    },
];
