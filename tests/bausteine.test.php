<?php
/** Was jeder Baustein im gebauten HTML verspricht (Vorlagen unter lp/bausteine/). */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;

$k = Katalog::laden();
$b = static fn (string $typ): array => $k->baustein($typ)['beispiel'];
/** Baut eine Seite und gibt [html, xpath] zurück. */
$bau = static function (array $seite, ?string $profil = null): array {
    $o = H::ordner($seite, null, $profil);
    $e = Bauer::bauen($o);
    T::eq(H::texte($e->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$o/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d)];
};
$jsonLd = static function (DOMXPath $x): array {
    $aus = [];
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) { $aus[] = json_decode($s->textContent, true); }
    return $aus;
};
$profilMit = static function (callable $aendern): string {
    $p = H::profilKopie();
    $j = json_decode((string) file_get_contents("$p/profil.json"), true);
    file_put_contents("$p/profil.json", json_encode($aendern($j), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $p;
};

return [
    'hero: H1, Bild mit Maßen und Vorrang, Varianten' => static function () use ($bau): void {
        [$h, $x] = $bau(H::seite());
        T::eq($x->query('//section[contains(@class,"lp-hero--bild-rechts")]//h1')->length, 1, 'H1 im Kopfbereich');
        $img = $x->query('//img[contains(@class,"lp-hero-bild")]')->item(0);
        T::ok($img !== null && $img->getAttribute('width') === '1200' && $img->getAttribute('height') === '800', 'Maße aus der Datei');
        T::eq($img?->getAttribute('fetchpriority'), 'high', 'fetchpriority');
        T::eq($img?->getAttribute('alt'), 'Stilisierte Bühne mit Rednerpult und Publikum', 'Alt-Text aus dem Bildbestand');
        T::eq($img?->getAttribute('src'), 'es/bilder/beispiel-vortrag.webp', 'Bestandsbild nach es/bilder/');
        T::eq($x->query('//a[contains(@class,"lp-knopf") and @href="#anmeldung"]')->length, 1, 'Knopf zur Anmeldung');
        $s = H::seite(); $s['bausteine'][0]['variante'] = 'schlicht';
        [, $x] = $bau($s);
        T::eq($x->query('//section[contains(@class,"lp-hero--schlicht")]//img')->length, 0, 'schlicht ohne Bild');
    },
    'termin: time, deutsches Datum, Ende, Ersatztext, JSON-LD' => static function () use ($bau, $b, $jsonLd): void {
        $t = $b('termin');
        $t['termine'][] = ['beginn' => '2099-12-01T09:30'];
        [$h, $x] = $bau(H::seite([$t]));
        $zeiten = $x->query('//time[contains(@class,"lp-termin-zeit")]');
        T::eq($zeiten->length, 2, 'zwei Termine');
        T::eq($zeiten->item(0)?->getAttribute('datetime'), '2099-10-06T19:00:00+02:00', 'datetime mit Wiener Sommerzeit');
        T::eq(trim((string) $zeiten->item(0)?->textContent), 'Dienstag, 6. Oktober 2099, 19:00 Uhr', 'deutsches Datum');
        T::eq($zeiten->item(1)?->getAttribute('datetime'), '2099-12-01T09:30:00+01:00', 'Winterzeit');
        $eintraege = $x->query('//*[contains(@class,"lp-termin-eintrag")]');
        T::eq($eintraege->item(0)?->getAttribute('data-lp-ende'), '2099-10-06T20:00:00+02:00', 'Ende = Beginn + Dauer');
        T::eq($eintraege->item(1)?->getAttribute('data-lp-ende'), '2099-12-01T09:30:00+01:00', 'ohne Dauer endet er mit dem Beginn');
        $leer = $x->query('//*[contains(@class,"lp-termin-leer")]')->item(0);
        T::ok($leer !== null && $leer->hasAttribute('hidden'), 'Ersatztext ohne JavaScript versteckt');
        $events = array_values(array_filter($jsonLd($x), static fn ($j) => ($j['@type'] ?? '') === 'Event'));
        T::eq(count($events), 2, 'ein Event je Termin');
        T::eq($events[0]['startDate'] ?? '', '2099-10-06T19:00:00+02:00', 'startDate');
        T::eq($events[0]['endDate'] ?? '', '2099-10-06T20:00:00+02:00', 'endDate');
        T::eq($events[0]['eventAttendanceMode'] ?? '', 'https://schema.org/OnlineEventAttendanceMode', 'online');
        T::eq($events[0]['name'] ?? '', 'Gesund durch den Winter', 'Name = Seitentitel');
        T::eq($events[0]['organizer']['name'] ?? '', 'Beispiel Akademie GmbH', 'Veranstalter aus dem Profil');
        $t['art'] = 'vor_ort';
        [, $x] = $bau(H::seite([$t]));
        $e = array_values(array_filter($jsonLd($x), static fn ($j) => ($j['@type'] ?? '') === 'Event'))[0] ?? [];
        T::eq($e['location']['@type'] ?? '', 'Place', 'vor Ort = Place');
        T::eq($e['eventAttendanceMode'] ?? '', 'https://schema.org/OfflineEventAttendanceMode', 'offline');
    },
    'themen: ein Listenpunkt je Punkt' => static function () use ($bau, $b): void {
        [, $x] = $bau(H::seite([$b('themen')]));
        T::eq($x->query('//ul[contains(@class,"lp-themen-liste") and @role="list"]/li[contains(@class,"lp-themen-punkt")]')->length, 3, 'drei Punkte');
    },
    'video: kein iframe, keine Anfrage vor dem Klick, Ersatzlink' => static function () use ($bau, $b): void {
        [$h, $x] = $bau(H::seite([$b('video')]));
        T::notContains($h, '<iframe', 'kein iframe im HTML');
        T::notContains($h, 'youtube-nocookie', 'keine Einbettungsadresse im HTML');
        $knopf = $x->query('//button[contains(@class,"lp-video-start")]')->item(0);
        T::eq($knopf?->getAttribute('data-lp-kennung'), 'AbCdEfGhIjk', 'Kennung am Knopf');
        T::eq($knopf?->getAttribute('data-lp-titel'), 'Kurz erklärt: Worum geht es?', 'Titel für das iframe');
        T::contains((string) $knopf?->getAttribute('aria-label'), 'Kurz erklärt: Worum geht es?', 'Knopf beschriftet');
        T::eq($x->query('//button[contains(@class,"lp-video-start")]//img[@alt=""]')->length, 1, 'Vorschaubild im Knopf ohne eigenen Alt-Text');
        T::eq($x->query('//a[@href="https://www.youtube.com/watch?v=AbCdEfGhIjk"]')->length, 1, 'Link zum Video als Ersatz');
        foreach ($x->query('//*[@src]') as $e) { T::ok(!preg_match('#^(https?:)?//#', $e->getAttribute('src')), 'nur lokale Quellen: ' . $e->getAttribute('src')); }
    },
    'erfahrungen: Zitat, Name, Quelle' => static function () use ($bau, $b): void {
        [, $x] = $bau(H::seite([$b('erfahrungen')]));
        T::eq(trim((string) $x->query('//blockquote[contains(@class,"lp-erfahrung-text")]')->item(0)?->textContent), 'Sehr verständlich erklärt, ich habe viel mitgenommen.', 'Zitat');
        T::contains((string) $x->query('//*[contains(@class,"lp-erfahrung-name")]')->item(0)?->textContent, 'Anna B., Graz', 'Name und Ort');
        T::eq($x->query('//a[contains(@class,"lp-erfahrung-quelle") and @href="https://www.example.com/erfahrungen/1"]')->length, 1, 'Quelle verlinkt');
    },
    'faq: details/summary und FAQPage, </script> bricht nichts' => static function () use ($bau, $b, $jsonLd): void {
        $f = $b('faq');
        $f['fragen'][] = ['frage' => 'Was passiert mit </script><script>alert(1)</script>?', 'antwort' => 'Nichts </script> passiert.'];
        $f['fragen'][] = ['frage' => 'Und fett?', 'antwort' => 'Das ist **wichtig**.'];
        [$h, $x] = $bau(H::seite([$f]));
        T::eq($x->query('//details[contains(@class,"lp-faq-frage")]/summary')->length, 3, 'drei Fragen');
        $faq = array_values(array_filter($jsonLd($x), static fn ($j) => ($j['@type'] ?? '') === 'FAQPage'))[0] ?? [];
        T::eq(count($faq['mainEntity'] ?? []), 3, 'FAQPage mit drei Fragen');
        T::eq($faq['mainEntity'][2]['acceptedAnswer']['text'] ?? '', 'Das ist wichtig.', 'JSON-LD ohne Sternchen');
        T::eq($faq['mainEntity'][1]['acceptedAnswer']['text'] ?? '', 'Nichts </script> passiert.', 'Antwort unverändert im JSON-LD');
        T::eq(substr_count($h, '<script'), substr_count($h, '</script>'), 'jedes Skript sauber geschlossen');
        T::notContains($h, '<script>alert', 'kein eingeschleustes Skript');
    },
    'anmeldung: Felder, Einwilligung, Honigtopf, versteckte Angaben' => static function () use ($bau): void {
        $s = H::seite();
        $s['bausteine'][1]['felder'] = ['vorname', 'nachname', 'email', 'telefon'];
        [$h, $x] = $bau($s);
        $form = $x->query('//form[contains(@class,"lp-formular")]')->item(0);
        T::eq($form?->getAttribute('action'), 'https://beispiel.invalid/anmelden', 'Formularziel aus dem Profil');
        T::eq($form?->getAttribute('method'), 'post', 'POST');
        foreach (['vorname' => 'given-name', 'nachname' => 'family-name', 'email' => 'email', 'telefon' => 'tel'] as $f => $auto) {
            $in = $x->query("//input[@name=\"$f\"]")->item(0);
            T::eq($in?->getAttribute('autocomplete'), $auto, "$f: autocomplete");
            T::eq($x->query('//label[@for="' . $in?->getAttribute('id') . '"]')->length, 1, "$f: Label");
        }
        T::eq($x->query('//input[@name="email" and @type="email" and @required]')->length, 1, 'E-Mail Pflicht');
        T::eq($x->query('//input[@name="telefon" and @required]')->length, 0, 'Telefon freiwillig');
        $ein = $x->query('//input[@name="einwilligung"]')->item(0);
        T::ok($ein !== null && $ein->getAttribute('type') === 'checkbox' && $ein->hasAttribute('required') && !$ein->hasAttribute('checked'), 'Einwilligung: Pflicht, nie vorangehakt');
        T::contains((string) $x->query('//label[@for="' . $ein?->getAttribute('id') . '"]')->item(0)?->textContent, 'Diese Einwilligung kann ich jederzeit widerrufen.', 'Einwilligungstext aus dem Profil');
        T::eq($x->query('//label[@for="' . $ein?->getAttribute('id') . '"]/a[@href="datenschutz.html"]')->length, 1, 'Link zur Datenschutzerklärung');
        $hp = $x->query('//*[contains(@class,"form-hp")]//input')->item(0);
        T::ok($hp !== null && $hp->getAttribute('tabindex') === '-1' && $hp->getAttribute('autocomplete') === 'off', 'Honigtopf nicht fokussierbar');
        T::eq($x->query('//input[@type="hidden" and @name="liste" and @value="beispiel-abend"]')->length, 1, 'Liste');
        $text = json_decode((string) file_get_contents(H::testprofil() . '/profil.json'), true)['einwilligung'];
        T::eq($x->query('//input[@type="hidden" and @name="einwilligung_kurz" and @value="' . substr(hash('sha256', $text), 0, 12) . '"]')->length, 1, 'Kurzprüfsumme des Einwilligungstexts');
        foreach ($x->query('//input[@required]') as $in) {
            T::eq($x->query('//*[@id="' . $in->getAttribute('id') . '-fehler" and @hidden]')->length, 1, $in->getAttribute('name') . ': Fehlertext vorbereitet');
            T::ok(!$in->hasAttribute('aria-describedby'), $in->getAttribute('name') . ': Fehlertext erst nach dem Absenden als Beschreibung (lp.js)');
        }
        T::ok(!$form?->hasAttribute('novalidate'), 'ohne JavaScript prüft der Browser selbst (novalidate setzt erst lp.js)');
        T::eq($x->query('//fieldset[@disabled]')->length, 0, 'mit Ziel nicht gesperrt');
        T::notContains($h, 'wird gerade eingerichtet', 'kein Hinweis');
    },
    'anmeldung ohne Formularziel: sichtbar gesperrt' => static function () use ($bau, $profilMit): void {
        [$h, $x] = $bau(H::seite(), $profilMit(static fn ($j) => ['formularziel' => ''] + $j));
        T::eq($x->query('//form[contains(@class,"lp-formular")]/fieldset[@disabled]')->length, 1, 'Felder gesperrt');
        T::eq($x->query('//form[@action]')->length, 0, 'kein Ziel');
        T::contains((string) $x->query('//*[contains(@class,"lp-anmeldung-hinweis")]')->item(0)?->textContent, 'wird gerade eingerichtet', 'Hinweis sichtbar');
    },
    'anmeldung: Rückmeldungen nach dem Absenden (?anmeldung=ok|bereits|fehler) vorbereitet, versteckt, du/Sie' => static function () use ($bau, $profilMit): void {
        [$h, $x] = $bau(H::seite());
        $meldung = static fn (string $klasse) => $x->query("//form[contains(@class,\"lp-formular\")]/p[contains(concat(' ', @class, ' '), ' $klasse ')]");
        foreach (['lp-anmeldung-danke', 'lp-anmeldung-bereits', 'lp-anmeldung-fehler'] as $klasse) {
            T::eq($meldung($klasse)->length, 1, "$klasse: genau einmal im Formular");
            $p = $meldung($klasse)->item(0);
            T::ok($p !== null && $p->hasAttribute('hidden'), "$klasse: ohne Rückmeldung versteckt");
            T::eq($p?->getAttribute('role'), 'status', "$klasse: role=status");
            T::eq($p?->getAttribute('tabindex'), '-1', "$klasse: fokussierbar für lp.js, aber kein Tab-Halt");
        }
        T::eq(trim((string) $meldung('lp-anmeldung-danke')->item(0)?->textContent), 'Danke! Bitte bestätige deine Anmeldung in der E-Mail.', 'ok: Dankestext aus dem Bauplan');
        T::contains((string) $meldung('lp-anmeldung-bereits')->item(0)?->textContent, 'Du bist bereits angemeldet.', 'bereits: du');
        T::contains((string) $meldung('lp-anmeldung-fehler')->item(0)?->textContent, 'Bitte prüfe deine Angaben und versuche es noch einmal.', 'fehler: du');
        T::eq($x->query('//form[contains(@class,"lp-formular")]/p[contains(@class,"lp-anmeldung-fehler")]/following-sibling::fieldset')->length, 1, 'fehler: Meldung steht vor den Feldern');
        [$h] = $bau(H::seite(), $profilMit(static fn ($j) => ['anrede' => 'Sie'] + $j));
        T::contains($h, 'Sie sind bereits angemeldet.', 'bereits: Sie');
        T::contains($h, 'Bitte prüfen Sie Ihre Angaben und versuchen Sie es noch einmal.', 'fehler: Sie');
        T::notContains($h, 'Du bist bereits', 'bereits: kein du');
        T::notContains($h, 'Bitte prüfe deine', 'fehler: kein du');
    },
    'anmeldung ohne JavaScript: Hinweis im <noscript> erklärt den Ablauf, du/Sie; ohne Formularziel keiner' => static function () use ($bau, $profilMit): void {
        [$h, $x] = $bau(H::seite());
        $hinweis = $x->query('//form[contains(@class,"lp-formular")]/noscript/p[contains(@class,"lp-anmeldung-hinweis")]');
        T::eq($hinweis->length, 1, 'genau ein <noscript>-Hinweis im Formular');
        T::contains((string) $hinweis->item(0)?->textContent, 'Nach dem Absenden bekommst du eine E-Mail von uns.', 'du');
        T::eq($x->query('//form[contains(@class,"lp-formular")]/fieldset/following-sibling::noscript')->length, 1, 'steht nach den Feldern (beim Knopf)');
        [$h] = $bau(H::seite(), $profilMit(static fn ($j) => ['anrede' => 'Sie'] + $j));
        T::contains($h, 'Nach dem Absenden bekommen Sie eine E-Mail von uns.', 'Sie');
        [$h] = $bau(H::seite(), $profilMit(static fn ($j) => ['formularziel' => ''] + $j));
        T::notContains($h, '<noscript>', 'ohne Formularziel kein Hinweis zum Absenden');
    },
    'Anrede aus dem Profil' => static function () use ($bau, $profilMit): void {
        [$h] = $bau(H::seite());
        T::contains($h, 'Bitte gib deine E-Mail-Adresse ein.', 'du');
        [$h] = $bau(H::seite(), $profilMit(static fn ($j) => ['anrede' => 'Sie'] + $j));
        T::contains($h, 'Bitte geben Sie Ihre E-Mail-Adresse ein.', 'Sie');
        T::notContains($h, 'Bitte gib', 'kein du');
    },
    'abschluss: Knopf und Variante' => static function () use ($bau, $b): void {
        [, $x] = $bau(H::seite([$b('abschluss')]));
        T::eq($x->query('//section[contains(@class,"lp-abschluss--akzent")]//a[contains(@class,"lp-knopf") and @href="#anmeldung"]')->length, 1, 'Knopf');
    },
    'Rahmen: Sprunglink, Logo, Pflichtfußzeile' => static function () use ($bau): void {
        [, $x] = $bau(H::seite());
        T::eq($x->query('//a[contains(@class,"skip-link") and @href="#inhalt"]')->length, 1, 'Sprunglink');
        T::eq($x->query('//main[@id="inhalt"]')->length, 1, 'Hauptinhalt');
        T::eq($x->query('//header//img[@src="es/logo.svg" and @alt="Beispiel Akademie" and @width="180" and @height="48"]')->length, 1, 'Logo');
        $fuss = (string) $x->query('//footer')->item(0)?->textContent;
        foreach (['Beispiel Akademie GmbH', 'Musterweg 1', '1010 Wien', 'office@beispiel.invalid'] as $t) { T::contains($fuss, $t, "Fußzeile: $t"); }
        T::eq($x->query('//footer//a[@href="impressum.html"]')->length + $x->query('//footer//a[@href="datenschutz.html"]')->length, 2, 'Rechtsseiten verlinkt');
        T::eq($x->query('//html[@lang="de-AT"]')->length, 1, 'Sprache');
    },
];
