<?php
/**
 * Hinweis-Kasten, längere Punkte, Mail-Links in „danach“, freies Feld im Formular (3.72.0).
 *
 *   HW01  Katalog: Baustein hinweis (Kennzeichnung 40, Titel 80, Text 600, vor_anmeldung), Punkte 200, danach 500, freifeld + freifeld_frage — alles mit seit
 *   HW02  Hinweis-Kasten: eigener Abschnitt mit Kennzeichnung über der H2 und dem Text; ohne Text wird nichts gebaut
 *   HW03  Stelle: mit vor_anmeldung steht der Kasten direkt vor der Anmeldung (wo er auch im Bauplan steht), sonst an seinem Platz; ohne Anmeldung an seinem Platz
 *   HW04  danach: jede E-Mail-Adresse nach strengem Muster wird ein mailto:-Link (auch fremde Domains), alles andere bleibt maskierter Text
 *   HW05  Freifeld: aus = Formular unverändert; an = Textfeld „anliegen“ (freiwillig, höchstens 1000 Zeichen, Beschriftung, aria-describedby)
 *   HW06  Grenzen sperren: Punkt 201, danach 501, Kennzeichnung 41, Text 601, Frage 101 Zeichen
 *   HW07  Checkliste: Seite mit Hinweis-Kasten und Freifeld ohne Mangel; Maskierung der neuen Felder
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Checkliste;
use Endo\Lp\Katalog;
use Endo\Lp\Kontext;
use Endo\Lp\Profil;

$k = Katalog::laden();
$hinweis = static fn (array $mehr = []): array => $mehr + ['typ' => 'hinweis', 'variante' => 'standard', 'kennzeichnung' => 'Wichtiger Hinweis',
    'titel' => 'Keine Heilversprechen', 'text' => "Der Vortrag ersetzt keine ärztliche Beratung.\nBei Beschwerden wende dich an deine Ärztin oder deinen Arzt."];
/** Baut die Seite und gibt [Befunde, XPath, HTML, Ordner] zurück. */
$bau = static function (array $seite, ?callable $profil = null): array {
    $p = null;
    if ($profil) { $p = H::profilKopie(); $profil($p); }
    $o = H::ordner($seite, null, $p);
    $e = Bauer::bauen($o);
    $html = (string) @file_get_contents("$o/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return [$e->befunde, new DOMXPath($d), $html, $o];
};
/** Reihenfolge der Abschnitte auf der gebauten Seite (Typ aus der Klasse lp-<typ>). */
$folge = static function (DOMXPath $x): array {
    $typen = [];
    foreach ($x->query('//main//section[contains(@class,"lp-block")]') as $s) {
        preg_match('/\blp-block lp-([a-z]+)\b/', (string) $s->getAttribute('class'), $m);
        $typen[] = $m[1] ?? '?';
    }
    return $typen;
};
$anmeldung = static fn (array $mehr = []): array => $mehr + $k->baustein('anmeldung')['beispiel'];

return [
    'HW01 Katalog: hinweis, Punkte 200, danach 500, Freifeld — mit seit 3.72.0' => static function () use ($k): void {
        $h = $k->baustein('hinweis');
        T::ok($h !== null && $h['seit'] === '3.72.0', 'Baustein hinweis seit 3.72.0');
        T::eq($h['felder']['kennzeichnung']['max'] ?? null, 40, 'Kennzeichnung höchstens 40');
        T::eq([$h['felder']['titel']['max'] ?? null, $h['felder']['titel']['pflicht'] ?? null], [80, true], 'Titel Pflicht, höchstens 80');
        T::eq([$h['felder']['text']['typ'] ?? null, $h['felder']['text']['max'] ?? null], ['absatz', 600], 'Text: Absatz bis 600');
        T::eq($h['felder']['vor_anmeldung']['typ'] ?? null, 'janein', 'vor_anmeldung: ja/nein');
        T::ok(!str_contains((string) json_encode($h['felder']), '_wenn'), 'keine Bedingung *_wenn (das Panel versteht sie nicht)');
        T::eq($k->baustein('themen')['felder']['punkte']['eintrag']['max'] ?? null, 200, 'Themenpunkte bis 200');
        $a = $k->baustein('anmeldung');
        T::eq($a['felder']['danach']['max'] ?? null, 500, 'danach bis 500');
        T::eq([$a['felder']['freifeld']['typ'] ?? null, $a['felder']['freifeld']['seit'] ?? null], ['janein', '3.72.0'], 'freifeld: ja/nein seit 3.72.0');
        T::eq([$a['felder']['freifeld_frage']['typ'] ?? null, $a['felder']['freifeld_frage']['max'] ?? null, $a['felder']['freifeld_frage']['seit'] ?? null],
            ['zeile', 100, '3.72.0'], 'freifeld_frage: Zeile bis 100 seit 3.72.0');
        T::eq([$a['seit'], $a['geaendert'], $k->baustein('themen')['geaendert']], ['3.51.0', '3.77.0', '3.72.0'], 'anmeldung geändert 3.77.0 (Hinweis statt Häkchen), themen 3.72.0');
        foreach (['lp-hinweis', 'lp-hinweis--standard', 'lp-hinweis-kasten', 'lp-hinweis-kennzeichnung', 'lp-hinweis-text'] as $klasse) {
            T::eq($h['klassen'][$klasse] ?? null, '3.72.0', "Klasse $klasse seit 3.72.0");
        }
        T::eq([$a['klassen']['lp-freifeld'] ?? null, $a['klassen']['lp-freifeld-hinweis'] ?? null], ['3.72.0', '3.72.0'], 'Klassen des Freifelds seit 3.72.0');
    },
    'HW02 Hinweis-Kasten: Kennzeichnung über der H2, Text; ohne Text nichts' => static function () use ($bau, $hinweis): void {
        [$befunde, $x] = $bau(H::seite([$hinweis()]));
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut ohne sperrenden Befund');
        $s = '//section[contains(@class,"lp-hinweis") and contains(@class,"lp-hinweis--standard")]';
        T::eq($x->query($s)->length, 1, 'ein Abschnitt lp-hinweis--standard');
        T::eq($x->query("$s//div[contains(@class,'lp-hinweis-kasten')]/p[contains(@class,'lp-hinweis-kennzeichnung')][following-sibling::h2[1][contains(@class,'lp-titel')]]")->length, 1,
            'Kennzeichnung im Kasten direkt vor der Überschrift');
        T::eq(trim((string) $x->query("$s//p[contains(@class,'lp-hinweis-kennzeichnung')]")->item(0)?->textContent), 'Wichtiger Hinweis', 'Kennzeichnung');
        T::eq(trim((string) $x->query("$s//h2")->item(0)?->textContent), 'Keine Heilversprechen', 'Überschrift');
        T::eq($x->query("$s//div[contains(@class,'lp-hinweis-text')]/p/br")->length, 1, 'Text als Absatz mit Zeilenumbruch');
        [, $x2] = $bau(H::seite([$hinweis(['kennzeichnung' => ''])]));
        T::eq($x2->query('//p[contains(@class,"lp-hinweis-kennzeichnung")]')->length, 0, 'leere Kennzeichnung: keine Zeile');
        $ohne = $hinweis();
        unset($ohne['text']);
        [$befunde3, $x3] = $bau(H::seite([$ohne]));
        T::eq(H::texte(H::sperrend($befunde3)), '', 'ohne Text: baut');
        T::eq($x3->query('//section[contains(@class,"lp-hinweis")]')->length, 0, 'ohne Text: kein Abschnitt');
    },
    'HW03 Stelle: vor_anmeldung stellt den Kasten direkt vor die Anmeldung, sonst bleibt er an seinem Platz' => static function () use ($bau, $hinweis, $folge, $k): void {
        $abschluss = $k->baustein('abschluss')['beispiel'];
        $seite = H::seite([]);
        $seite['bausteine'][] = $abschluss;
        $seite['bausteine'][] = $hinweis(['vor_anmeldung' => true]);
        [$befunde, $x] = $bau($seite);
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut');
        T::eq($folge($x), ['hero', 'hinweis', 'anmeldung', 'abschluss'], 'vor_anmeldung: direkt vor der Anmeldung');
        T::eq($x->query('//section[contains(@class,"lp-hinweis")]/@aria-labelledby')->item(0)?->nodeValue, 'lp-a4-titel', 'Nummer bleibt die Stelle im Bauplan');
        $seite['bausteine'][3] = $hinweis(['vor_anmeldung' => false]);
        T::eq($folge($bau($seite)[1]), ['hero', 'anmeldung', 'abschluss', 'hinweis'], 'ohne vor_anmeldung: am Ende');
        unset($seite['bausteine'][3]['vor_anmeldung']);
        T::eq($folge($bau($seite)[1]), ['hero', 'anmeldung', 'abschluss', 'hinweis'], 'fehlt: am Ende');
        $ohneAnmeldung = ['format' => 1, 'meta' => $seite['meta'], 'bausteine' => [$seite['bausteine'][0], $hinweis(['vor_anmeldung' => true]), $abschluss]];
        $ohneAnmeldung['bausteine'][2]['knopf'] = ['text' => 'Nach oben', 'ziel' => '#start'];
        $ohneAnmeldung['bausteine'][0]['anker'] = 'start';
        $ohneAnmeldung['bausteine'][0]['knopf']['ziel'] = '#start';
        [$b4, $x4] = $bau($ohneAnmeldung);
        T::eq(H::texte(H::sperrend($b4)), '', 'ohne Anmeldung: baut');
        T::eq($folge($x4), ['hero', 'hinweis', 'abschluss'], 'ohne Anmeldung: an seinem Platz');
    },
    'HW04 danach: E-Mail-Adressen werden mailto:-Links, sonst maskierter Text' => static function () use ($bau, $anmeldung): void {
        $danach = "Keine Zeit? Schreib an post@fremd-beispiel.test, dann schicken wir dir die Aufzeichnung.\n"
            . 'Nicht: <b>fett</b>, a?cc=x@fremd-beispiel.test, "x"@fremd-beispiel.test, x@localhost, x%0a@fremd-beispiel.test, javascript:x@fremd-beispiel.test';
        $seite = H::seite([]);
        $seite['bausteine'][1] = $anmeldung(['danach' => $danach, 'text' => 'Auch hier: info@fremd-beispiel.test']);
        [$befunde, $x, $html] = $bau($seite);
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut');
        $d = '//p[contains(@class,"lp-anmeldung-danach")]';
        T::eq($x->query("$d//a")->length, 1, 'genau ein Link in danach');
        T::eq($x->query("$d//a[@href='mailto:post@fremd-beispiel.test']")->item(0)?->textContent, 'post@fremd-beispiel.test', 'fremde Domain wird mailto:, Komma bleibt draußen');
        T::contains($html, '&lt;b&gt;fett&lt;/b&gt;', 'HTML aus dem Kundentext bleibt maskiert');
        T::notContains($html, 'href="javascript', 'kein javascript:');
        T::eq($x->query('//section[contains(@class,"lp-anmeldung")]//p[not(contains(@class,"lp-anmeldung-danach"))]//a[contains(@href,"fremd-beispiel")]')->length, 0,
            'im Text des Bausteins bleiben fremde Adressen Text (nur danach verlinkt alle)');
        $kx = new Kontext(Profil::laden(H::testprofil()), [], T::tmp());
        T::eq($kx->absaetze('Mail an a.b-c+d@x-y.example.org.', '', true), '<p>Mail an <a href="mailto:a.b-c+d@x-y.example.org">a.b-c+d@x-y.example.org</a>.</p>', 'Kontext::absaetze mit Mail-Links');
        T::eq($kx->absaetze('Mail an a.b@x.example.org.'), '<p>Mail an a.b@x.example.org.</p>', 'ohne Schalter: wie bisher');
        T::eq($kx->absaetze('Siehe www.example.com', '', true), '<p>Siehe <a href="https://www.example.com">www.example.com</a></p>', 'Adressen auf link_hosts bleiben Links');
    },
    'HW05 Freifeld: aus = Formular wie bisher, an = freiwilliges Textfeld anliegen' => static function () use ($bau, $anmeldung): void {
        $ohne = H::seite([]);
        [, , $htmlOhne] = $bau($ohne);
        $aus = $ohne;
        $aus['bausteine'][1] = $anmeldung(['freifeld' => false, 'freifeld_frage' => 'Egal?']);
        [, $xAus, $htmlAus] = $bau($aus);
        T::eq($xAus->query('//textarea')->length, 0, 'aus: kein Textfeld');
        preg_match('#<form.*</form>#s', $htmlOhne, $a);
        preg_match('#<form.*</form>#s', $htmlAus, $b);
        T::eq($b[0] ?? '', $a[0] ?? 'x', 'aus: Formular Byte für Byte wie ohne Feld');
        $an = $ohne;
        $an['bausteine'][1] = $anmeldung(['freifeld' => true]);
        [$befunde, $x] = $bau($an);
        T::eq(H::texte(H::sperrend($befunde)), '', 'an: baut');
        $t = $x->query('//form[contains(@class,"lp-formular")]//div[contains(@class,"lp-feld") and contains(@class,"lp-freifeld")]/textarea[@name="anliegen"]')->item(0);
        T::ok($t instanceof DOMElement, 'an: Textfeld anliegen im Feld lp-freifeld');
        if ($t instanceof DOMElement) {
            T::eq([$t->getAttribute('id'), $t->getAttribute('maxlength'), $t->hasAttribute('required')], ['lp-f-anliegen', '1000', false], 'id, höchstens 1000, freiwillig');
            T::eq(trim((string) $x->query('//label[@for="lp-f-anliegen"]')->item(0)?->textContent), 'Worum geht es dir? (freiwillig)', 'Vorgabe der Frage in der Du-Form, „(freiwillig)“');
            $hilfe = $t->getAttribute('aria-describedby');
            T::eq($x->query("//p[@id='$hilfe'][contains(@class,'lp-freifeld-hinweis')]")->length, 1, 'aria-describedby zeigt auf den Hinweis');
            T::contains((string) $x->query("//p[@id='$hilfe']")->item(0)?->textContent, '1000 Zeichen', 'Hinweis nennt die Grenze');
            T::ok($x->query('//textarea[@name="anliegen"]/following::input[@name="einwilligung"]')->length === 1, 'Textfeld vor der Einwilligung');
        }
        $an['bausteine'][1] = $anmeldung(['freifeld' => true, 'freifeld_frage' => 'Was ist <dein> Anliegen?']);
        [, $x2, $html2] = $bau($an, static function (string $p): void {
            $j = json_decode((string) file_get_contents("$p/profil.json"), true);
            $j['anrede'] = 'Sie';
            file_put_contents("$p/profil.json", json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
        T::contains($html2, 'Was ist &lt;dein&gt; Anliegen? <span class="lp-freiwillig">(freiwillig)</span>', 'eigene Frage maskiert');
        $an['bausteine'][1] = $anmeldung(['freifeld' => true]);
        [, $x3] = $bau($an, static function (string $p): void {
            $j = json_decode((string) file_get_contents("$p/profil.json"), true);
            $j['anrede'] = 'Sie';
            file_put_contents("$p/profil.json", json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
        T::eq(trim((string) $x3->query('//label[@for="lp-f-anliegen"]')->item(0)?->textContent), 'Worum geht es Ihnen? (freiwillig)', 'Vorgabe in der Sie-Form');
    },
    'HW06 Grenzen sperren' => static function () use ($bau, $hinweis, $anmeldung, $k): void {
        $themen = $k->baustein('themen')['beispiel'];
        $faelle = [
            'Punkt 200' => [[$themen + ['punkte' => [str_repeat('a', 200), 'b']]], false],
            'Punkt 201' => [[array_merge($themen, ['punkte' => [str_repeat('a', 201), 'b']])], true],
            'Kennzeichnung 41' => [[$hinweis(['kennzeichnung' => str_repeat('k', 41)])], true],
            'Text 600' => [[$hinweis(['text' => str_repeat('t', 600)])], false],
            'Text 601' => [[$hinweis(['text' => str_repeat('t', 601)])], true],
        ];
        foreach ($faelle as $name => [$mitte, $sperrt]) {
            T::eq(H::sperrend($bau(H::seite($mitte))[0]) !== [], $sperrt, $name . ($sperrt ? ' sperrt' : ' geht'));
        }
        foreach (['danach' => [500, 501], 'freifeld_frage' => [100, 101]] as $feld => [$gut, $zuviel]) {
            $s = H::seite([]);
            $s['bausteine'][1] = $anmeldung([$feld => str_repeat('d', $gut), 'freifeld' => true]);
            T::eq(H::sperrend($bau($s)[0]), [], "$feld $gut geht");
            $s['bausteine'][1] = $anmeldung([$feld => str_repeat('d', $zuviel), 'freifeld' => true]);
            T::ok(H::sperrend($bau($s)[0]) !== [], "$feld $zuviel sperrt");
        }
    },
    'HW07 Checkliste ohne Mangel, neue Felder maskiert' => static function () use ($bau, $hinweis, $anmeldung): void {
        $s = H::seite([$hinweis(['vor_anmeldung' => true, 'kennzeichnung' => '<img src=x onerror=alert(1)>', 'titel' => '"><script>alert(1)</script>'])]);
        $s['bausteine'][2] = $anmeldung(['freifeld' => true, 'freifeld_frage' => '"><script>alert(2)</script>', 'danach' => 'Fragen? <script>x</script> an post@fremd-beispiel.test']);
        [$befunde, $x, $html, $o] = $bau($s);
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut');
        T::notContains($html, '<script>alert', 'kein Skript aus dem Bauplan');
        T::notContains($html, '<img src=x', 'kein Bild aus dem Bauplan');
        $maengel = array_filter(Checkliste::pruefen($o), static fn ($b) => $b->art === 'mangel');
        T::eq(H::texte(array_values($maengel)), '', 'Checkliste: kein Mangel');
    },
];
