<?php
/**
 * Was die Marke 3.52.0 für den Gesprächs-Assistenten bringt (Entwurf 2026-10-06, Abschnitt 4): Preis im Termin, Prüfregel
 * „kostenlos trotz Preis“, „was danach passiert“, Plätze in der Anmeldung, Baustein „person“, Vorlage zoom-vortrag.
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Pruefer;
use Endo\Lp\Schema;

$k = Katalog::laden();
/** Baut eine Seite und gibt [html, xpath, ordner] zurück; der Bau selbst hat keine Befunde. */
$bau = static function (array $seite, ?string $profil = null): array {
    $o = H::ordner($seite, null, $profil);
    $e = Bauer::bauen($o);
    T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$o/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d), $o];
};
$events = static function (DOMXPath $x): array {
    $aus = [];
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        $j = json_decode($s->textContent, true);
        if (($j['@type'] ?? '') === 'Event') { $aus[] = $j; }
    }
    return $aus;
};
/** Baut und prüft (Prüfer mit fester Uhr, wie die Musterbuch-Prüfung). */
$pruefe = static function (array $seite, ?string $profil = null): array {
    $o = H::ordner($seite, null, $profil);
    T::eq(H::texte(H::sperrend(Bauer::bauen($o)->befunde)), '', 'Bau ohne sperrende Befunde');
    return Pruefer::pruefen($o, '2026-09-27T12:00');
};
$profilMit = static function (callable $aendern, ?callable $dateien = null): string {
    $p = H::profilKopie();
    $j = json_decode((string) file_get_contents("$p/profil.json"), true);
    file_put_contents("$p/profil.json", json_encode($aendern($j), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($dateien) { $dateien($p); }
    return $p;
};

return [
    'termin: ohne Preis „Teilnahme kostenlos“, mit Preis der Betrag in Euro; JSON-LD passt dazu' => static function () use ($k, $bau, $events): void {
        $t = $k->baustein('termin')['beispiel'];
        [, $x] = $bau(H::seite([$t]));
        T::eq(trim((string) $x->query('//p[contains(@class,"lp-termin-preis")]')->item(0)?->textContent), 'Teilnahme kostenlos', 'ohne Preis');
        $e = $events($x)[0] ?? [];
        T::eq($e['isAccessibleForFree'] ?? null, true, 'JSON-LD: frei zugänglich');
        T::ok(!isset($e['offers']), 'JSON-LD: ohne Angebot');
        $t['preis'] = 25;
        [, $x] = $bau(H::seite([$t]));
        T::eq(trim((string) $x->query('//p[contains(@class,"lp-termin-preis")]')->item(0)?->textContent), 'Teilnahme: 25 €', 'mit Preis');
        $e = $events($x)[0] ?? [];
        T::eq($e['isAccessibleForFree'] ?? null, false, 'JSON-LD: nicht frei');
        T::eq($e['offers'] ?? null, ['@type' => 'Offer', 'price' => 25, 'priceCurrency' => 'EUR'], 'JSON-LD: Angebot in Euro');
    },
    'termin.preis: nur ganze Zahlen von 1 bis 9999' => static function () use ($k): void {
        $p = Profil::laden(H::testprofil());
        foreach ([0, 10000, '25', 2.5] as $falsch) {
            $t = $k->baustein('termin')['beispiel'];
            $t['preis'] = $falsch;
            T::ok(Schema::pruefen(H::seite([$t]), $k, $p, T::tmp()) !== [], 'preis ' . var_export($falsch, true) . ' wird abgewiesen');
        }
        $t = $k->baustein('termin')['beispiel'];
        $t['preis'] = 9999;
        T::eq(H::texte(Schema::pruefen(H::seite([$t]), $k, $p, T::tmp())), '', 'preis 9999 gilt');
    },
    'lp.js blendet den Preis mit aus, wenn kein Termin mehr offen ist' => static function (): void {
        T::contains((string) file_get_contents(H::wurzel() . '/lp/lp.js'), ".lp-termin-liste, .lp-termin-ort, .lp-termin-preis, .lp-knopf", 'Auswahl der ausgeblendeten Teile');
    },
    'Prüfung: mit Preis sperrt „kostenlos“/„gratis“ in jedem Textfeld, ohne Preis nicht' => static function () use ($k, $pruefe): void {
        $trotz = static fn (array $befunde): array => array_values(array_map(static fn ($b) => $b->pfad,
            array_filter(H::sperrend($befunde), static fn ($b) => str_contains($b->technik, 'trotz Preis'))));
        $t = $k->baustein('termin')['beispiel'];
        T::eq($trotz($pruefe(H::seite([$t]))), [], 'ohne Preis: kein Befund');
        $t['preis'] = 25;
        $mit = $pruefe(H::seite([$t]));
        $pfade = $trotz($mit);
        T::ok(in_array('meta.beschreibung', $pfade, true), 'Beschreibung „Kostenloser Online-Abend …“ sperrt — ' . implode(', ', $pfade));
        T::ok(in_array('bausteine.0.dachzeile', $pfade, true), 'Dachzeile sperrt');
        T::ok(in_array('bausteine.0.knopf.text', $pfade, true), 'Knopf „Kostenlos anmelden“ sperrt');
        T::ok(in_array('bausteine.2.titel', $pfade, true), 'Titel der Anmeldung „Jetzt kostenlos anmelden“ sperrt');
        T::contains(implode(' ', array_map(static fn ($b) => $b->kunde, H::sperrend($mit))), 'Abschnitt 1 „Kopfbereich“', 'der Kundentext nennt den Abschnitt');
        $s = H::seite([$t]);
        $s['meta']['beschreibung'] = 'Online-Abend mit Fragerunde.';
        $s['bausteine'][0]['dachzeile'] = 'Online-Abend';
        $s['bausteine'][0]['knopf']['text'] = 'Jetzt anmelden';
        $s['bausteine'][2]['titel'] = 'Jetzt anmelden';
        T::eq($trotz($pruefe($s)), [], 'umformuliert: kein Befund');
        $s['bausteine'][0]['dachzeile'] = 'GRATIS-Abend';
        T::eq($trotz($pruefe($s)), ['bausteine.0.dachzeile'], 'Groß- und Kleinschreibung zählen nicht');
    },
    'Vorlage zoom-vortrag: Anmeldung und Beschreibung ohne „kostenlos“' => static function (): void {
        $v = json_decode((string) file_get_contents(H::wurzel() . '/lp/vorlagen/zoom-vortrag.json'), true);
        T::eq($v['bausteine'][3]['titel'] ?? null, 'Jetzt anmelden', 'Titel der Anmeldung');
        T::eq($v['meta']['beschreibung'] ?? null, '{{Ein Satz für Suchmaschinen: Thema und Datum}}', 'Platzhalter der Beschreibung');
    },
    'anmeldung: „Was danach passiert“ steht vor dem Formular, ohne Angabe kein Element' => static function () use ($bau): void {
        $s = H::seite();
        $s['bausteine'][1]['danach'] = 'Den Zoom-Link bekommst du am Tag vor dem Vortrag per E-Mail.';
        [, $x] = $bau($s);
        $p = $x->query('//section[contains(@class,"lp-anmeldung")]//p[contains(@class,"lp-anmeldung-danach")]')->item(0);
        T::eq(trim((string) $p?->textContent), 'Den Zoom-Link bekommst du am Tag vor dem Vortrag per E-Mail.', 'Satz steht da');
        T::ok($p !== null && (new DOMXPath($p->ownerDocument))->query('following-sibling::form[contains(@class,"lp-formular")]', $p)->length === 1, 'vor dem Formular');
        [, $x] = $bau(H::seite());
        T::eq($x->query('//*[contains(@class,"lp-anmeldung-danach")]')->length, 0, 'ohne Angabe kein Element');
        $s['bausteine'][1]['danach'] = str_repeat('x', 301);
        T::ok(Schema::pruefen($s, Katalog::laden(), Profil::laden(H::testprofil()), T::tmp()) !== [], 'mehr als 300 Zeichen werden abgewiesen');
    },
    'anmeldung: „Was danach passiert“ hält die Absatz-Regeln des Katalogs (**fett**, Leerzeile, nur Leerraum)' => static function () use ($bau): void {
        $s = H::seite();
        $s['bausteine'][1]['danach'] = "**Wichtig:** Den Zoom-Link bekommst du per E-Mail.\n\nKommt keiner an, schau bitte im Spam-Ordner nach.";
        [, $x] = $bau($s);
        $ps = $x->query('//section[contains(@class,"lp-anmeldung")]//p[contains(@class,"lp-anmeldung-danach")]');
        T::eq($ps->length, 2, 'Leerzeile = zwei Absätze');
        T::eq($ps->length > 0 ? $x->query('strong', $ps->item(0))->item(0)?->textContent : null, 'Wichtig:', '**fett** wird <strong>');
        T::ok($ps->length > 0 && !str_contains((string) $ps->item(0)->textContent, '**'), 'keine Sternchen auf der Seite');
        T::ok($ps->length === 2 && $x->query('following-sibling::*[1][self::form[contains(@class,"lp-formular")]]', $ps->item(1))->length === 1, 'letzter Absatz direkt vor dem Formular');
        $s['bausteine'][1]['danach'] = " \n ";
        [, $x] = $bau($s);
        T::eq($x->query('//*[contains(@class,"lp-anmeldung-danach")]')->length, 0, 'nur Leerraum: kein Element');
    },
    'anmeldung: Plätze — fester Text, Quelle aus dem Formularziel, versteckte Meldung „ausgebucht“' => static function () use ($bau, $profilMit): void {
        $s = H::seite();
        $s['bausteine'][1]['plaetze'] = 30;
        [, $x] = $bau($s);
        $p = $x->query('//form[contains(@class,"lp-formular")]/p[contains(@class,"lp-anmeldung-plaetze")]')->item(0);
        T::eq(trim((string) $p?->textContent), 'Begrenzt auf 30 Plätze.', 'fester Text ohne Skript');
        T::eq($p?->getAttribute('data-lp-plaetze-liste'), 'beispiel-abend', 'Kennung der Liste');
        T::eq($p?->getAttribute('data-lp-plaetze-quelle'), 'https://beispiel.invalid/lp/plaetze', 'Quelle = Host des Formularziels + /lp/plaetze');
        T::eq($p?->getAttribute('aria-live'), 'polite', 'Änderungen werden angesagt');
        $voll = $x->query('//form[contains(@class,"lp-formular")]/p[contains(@class,"lp-anmeldung-voll")]')->item(0);
        T::ok($voll !== null && $voll->hasAttribute('hidden') && $voll->getAttribute('role') === 'status' && $voll->getAttribute('tabindex') === '-1', 'Meldung versteckt bereit');
        T::eq(trim((string) $voll?->textContent), 'Leider ausgebucht — alle Plätze sind vergeben.', 'Wortlaut');
        $s['bausteine'][1]['plaetze'] = 1;
        [, $x] = $bau($s);
        T::eq(trim((string) $x->query('//*[contains(@class,"lp-anmeldung-plaetze")]')->item(0)?->textContent), 'Begrenzt auf 1 Platz.', 'Einzahl');
        [, $x] = $bau(H::seite());
        T::eq($x->query('//*[contains(@class,"lp-anmeldung-plaetze")]')->length, 0, 'ohne Plätze keine Anzeige');
        T::eq($x->query('//*[contains(@class,"lp-anmeldung-voll")]')->length, 1, 'die Meldung „ausgebucht“ steht trotzdem bereit');
        $s['bausteine'][1]['plaetze'] = 30;
        [, $x] = $bau($s, $profilMit(static fn ($j) => ['formularziel' => ''] + $j));
        T::eq($x->query('//*[contains(@class,"lp-anmeldung-plaetze")]')->length, 0, 'ohne Formularziel keine Abfrage');
        foreach ([0, 10001, '30'] as $falsch) {
            $s['bausteine'][1]['plaetze'] = $falsch;
            T::ok(Schema::pruefen($s, Katalog::laden(), Profil::laden(H::testprofil()), T::tmp()) !== [], 'plaetze ' . var_export($falsch, true) . ' wird abgewiesen');
        }
    },
    'lp.js: Plätze ohne Cookies abfragen, keine feste Adresse, Rücksprung „voll“' => static function (): void {
        $js = (string) file_get_contents(H::wurzel() . '/lp/lp.js');
        T::contains($js, "credentials: 'omit'", 'Abfrage ohne Cookies');
        T::contains($js, "voll: '.lp-anmeldung-voll'", 'Rücksprung voll');
        T::notContains($js, 'panel.ssi.at', 'keine feste Adresse im Skript');
    },
];
