<?php
/**
 * Checkliste (lp/src/Checkliste.php, lp/checkliste.php): Barrierefreiheit und Formular als eigene Punkte, getrennt von der Freigabe-Prüfung.
 * Je Regel: die saubere, gebaute Seite ergibt keinen Punkt; dieselbe Seite, nach dem Bau gezielt verschlechtert, ergibt genau den erwarteten.
 *
 *   CL01  saubere Seiten (alle Bausteine, Newsletter-Häkchen, Bild als Schmuck, Startbild im Videoknopf, base.css-Fokusregeln): kein Punkt
 *   CL02  Bild ohne alt oder mit leerem alt, obwohl es kein Schmuck ist: Mangel am Feld des Bauplans
 *   CL03  Bildbeschreibung als Dateiname oder nur „Bild“/„Foto“: Mangel; über 150 Zeichen: Hinweis
 *   CL04  Logo ohne Beschreibung oder nur „Logo“: ein Mangel (nicht je Seite einer)
 *   CL05  Überschriften: nicht genau eine H1 = Mangel; übersprungene Ebene (auch im Rechtstext) = Hinweis
 *   CL06  Links und Knöpfe: ohne Namen = Mangel; „hier“, „Mehr“ = Hinweis am Feld des Bauplans; aria-label zählt als Name
 *   CL07  Sprache: <html lang> fehlt oder ist kein Sprachkürzel = Mangel
 *   CL08  Tastatur: tabindex über 0 = Mangel, 0 und -1 nicht
 *   CL09  Fokusrahmen: outline none/0 in einer :focus-Regel ohne Ersatz = Mangel; mit Ersatz oder :focus-visible-Gegenregel nicht
 *   CL10  Formular: kein Link auf datenschutz.html im Formular oder seinem Abschnitt = Mangel
 *   CL11  Formular: Einwilligung fehlt, ist kein Häkchen oder ein Häkchen ist vorangehakt = Mangel
 *   CL12  Formular: Feld ohne Beschriftung = Mangel
 *   CL13  Formular: Pflichtfeld ohne required, „freiwillig“ mit required = Mangel
 *   CL14  Formular: Honigtopf fehlt oder ist für Tastatur/Bildschirmleser erreichbar = Mangel
 *   CL15  Formular: Ziel ohne https oder ohne Ziel = Mangel am Profilfeld formularziel
 *   CL16  Musterbuch: keine Seite unter musterbuch/*\/ hat einen Mangel
 *   CL17  Prüfer unberührt: Pruefer::pruefen gibt für jede Musterbuch-Seite vor und nach der Checkliste dieselben Befunde; die Checkliste schreibt nichts
 *   CL18  Kommandozeile lp/checkliste.php: JSON-Lines {art, bereich, pfad, technik, kunde}, Exit 0/1/2
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Checkliste;
use Endo\Lp\Dateien;
use Endo\Lp\Katalog;
use Endo\Lp\Pruefer;

$k = Katalog::laden();
$b = static fn (string $typ): array => $k->baustein($typ)['beispiel'];
$person = ['variante' => 'bild-links', 'bild' => ['datei' => 'bestand/beispiel-landschaft.webp']] + $b('person');
$alle = array_merge(array_map($b, ['termin', 'themen', 'video', 'erfahrungen', 'faq']), [$person, $b('abschluss')]);
$w = H::wurzel();

/** @param list<\Endo\Lp\Pruefpunkt> $punkte */
$texte = static fn (array $punkte): string => implode("\n", array_map(static fn ($p) => "[$p->art/$p->bereich] $p->pfad | $p->technik | $p->kunde", $punkte));
/** Baut die Seite (ohne sperrenden Befund) und gibt den Ordner zurück. */
$bau = static function (array $seite, ?string $profil = null): string {
    $o = H::ordner($seite, null, $profil);
    T::eq(H::texte(H::sperrend(Bauer::bauen($o)->befunde)), '', 'baut ohne sperrenden Befund');
    return $o;
};
/** Ändert eine gebaute Datei von Hand; was ersetzt wird, muss darin stehen (sonst prüfte der Test nichts). */
$tausch = static function (string $o, string $von, string $nach, string $datei = 'index.html'): string {
    $inhalt = (string) file_get_contents("$o/$datei");
    T::ok(str_contains($inhalt, $von), "$datei enthält „{$von}“");
    file_put_contents("$o/$datei", str_replace($von, $nach, $inhalt));
    return $o;
};
$einbau = static fn (string $o, string $html): string => $tausch($o, '</main>', "$html\n</main>");
/** Genau ein Punkt, und zwar der erwartete: Art, Bereich, Pfad und ein Stück des Technik-Texts. */
$genau = static function (string $o, string $art, string $bereich, string $pfad, string $teil, string $was) use ($texte): void {
    $p = Checkliste::pruefen($o);
    T::ok(count($p) === 1 && $p[0]->art === $art && $p[0]->bereich === $bereich && $p[0]->pfad === $pfad && str_contains($p[0]->technik, $teil),
        "$was — erwartet genau [$art/$bereich] $pfad mit „{$teil}“, bekommen:\n" . $texte($p));
};
$keine = static fn (string $o, string $was) => T::eq($texte(Checkliste::pruefen($o)), '', $was);
$anmeldung = static fn (array $seite): int => count($seite['bausteine']) - 1;   // H::seite: die Anmeldung steht zuletzt

return [
    'CL01 saubere Seiten ergeben keinen Punkt' => static function () use ($bau, $keine, $alle, $b): void {
        $keine($bau(H::seite($alle)), 'alle Bausteine (Video mit Startbild im Knopf, Person mit Bild, Erfahrungen mit aria-label, Fragen)');
        $nl = H::seite(); $nl['bausteine'][1]['newsletter'] = true;
        $keine($bau($nl), 'mit freiwilligem Newsletter-Häkchen');
        $felder = H::seite(); $felder['bausteine'][1]['felder'] = ['vorname', 'nachname', 'email', 'telefon'];
        $keine($bau($felder), 'alle Formularfelder: Telefon heißt „(freiwillig)“ und ist es, die übrigen sind Pflicht');
        $schmuck = H::seite(); $schmuck['bausteine'][0]['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'dekorativ' => true];
        $o = $bau($schmuck);
        T::contains((string) file_get_contents("$o/index.html"), 'alt=""', 'Schmuckbild mit leerem alt gebaut');
        $keine($o, 'Bild als Schmuck (dekorativ) mit leerem alt');
        $sie = H::seite([$b('termin')]); $sie['bausteine'][0]['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'alt' => 'Foto von Anna Beispiel beim Vortrag'];
        $keine($bau($sie), 'eine Beschreibung, die mit „Foto“ beginnt, ist eine Beschreibung');
    },
    'CL02 Bild ohne alt oder mit leerem alt (kein Schmuck): Mangel am Feld des Bauplans' => static function () use ($bau, $tausch, $genau): void {
        $alt = 'alt="Stilisierte Bühne mit Rednerpult und Publikum"';
        $genau($tausch($bau(H::seite()), " $alt", ''), 'mangel', 'barrierefreiheit', 'bausteine.0.bild.alt', 'ohne alt', 'alt fehlt');
        $o = $tausch($bau(H::seite()), $alt, 'alt=""');
        $genau($o, 'mangel', 'barrierefreiheit', 'bausteine.0.bild.alt', 'leerem alt', 'alt leer, im Bauplan nicht dekorativ');
        T::contains(Checkliste::pruefen($o)[0]->kunde ?? '', 'Abschnitt 1 „Kopfbereich“: Dem Bild fehlt die Beschreibung', 'Kundentext nennt den Abschnitt');
    },
    'CL03 Bildbeschreibung als Dateiname oder nur „Bild“: Mangel; sehr lang: Hinweis' => static function () use ($bau, $genau): void {
        $mit = static function (string $alt): array {
            $s = H::seite();
            $s['bausteine'][0]['bild'] = ['datei' => 'bestand/beispiel-vortrag.webp', 'alt' => $alt];
            return $s;
        };
        foreach (['foto1.webp', 'IMG_1234', 'DSC01234', 'Foto 3'] as $alt) {
            $genau($bau($mit($alt)), 'mangel', 'barrierefreiheit', 'bausteine.0.bild.alt', 'Dateiname', "Dateiname „{$alt}“");
        }
        foreach (['Bild', 'Foto.', 'Grafik'] as $alt) {
            $genau($bau($mit($alt)), 'mangel', 'barrierefreiheit', 'bausteine.0.bild.alt', 'sagt nichts', "nur „{$alt}“");
        }
        $lang = str_repeat('Eine Bühne mit Rednerpult, ', 6) . 'davor Publikum.';
        T::ok(mb_strlen($lang) > 150 && mb_strlen($lang) <= 200, 'Testtext über 150 Zeichen, im Bauplan erlaubt');
        $genau($bau($mit($lang)), 'hinweis', 'barrierefreiheit', 'bausteine.0.bild.alt', 'Zeichen', 'sehr lange Beschreibung');
    },
    'CL04 Logo ohne Beschreibung oder nur „Logo“: ein Mangel für alle drei Seiten' => static function () use ($bau, $tausch, $genau): void {
        $o = $bau(H::seite());
        foreach (['index.html', 'impressum.html', 'datenschutz.html'] as $datei) {
            $tausch($o, 'alt="Beispiel Akademie"', 'alt=""', $datei);
        }
        $genau($o, 'mangel', 'barrierefreiheit', 'profil.logo.alt', 'leerem alt', 'Logo mit leerem alt (auch der Link hat dann keinen Namen)');
        $p = H::profilKopie();
        $j = json_decode((string) file_get_contents("$p/profil.json"), true);
        $j['logo']['alt'] = 'Logo';
        file_put_contents("$p/profil.json", json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $genau($bau(H::seite(), $p), 'mangel', 'barrierefreiheit', 'profil.logo.alt', 'sagt nichts', 'Logo nur „Logo“');
    },
    'CL05 Überschriften: zwei H1 = Mangel, übersprungene Ebene = Hinweis' => static function () use ($bau, $tausch, $einbau, $genau, $anmeldung): void {
        $genau($einbau($bau(H::seite()), '<h1>Zweite Hauptüberschrift</h1>'), 'mangel', 'barrierefreiheit', 'index.html', 'genau eine H1', 'zwei H1');
        $s = H::seite();
        $genau($tausch($bau($s), 'Jetzt kostenlos anmelden</h2>', 'Jetzt kostenlos anmelden</h2><h4>Kleingedrucktes</h4>'),
            'hinweis', 'barrierefreiheit', 'bausteine.' . $anmeldung($s), 'H2 auf H4', 'H2 → H4 im Abschnitt der Anmeldung');
        $p = H::profilKopie();
        file_put_contents("$p/rechtliches/datenschutz.txt", "### Gleich Ebene drei\n\nText.\n");
        $genau($bau(H::seite(), $p), 'hinweis', 'barrierefreiheit', 'datenschutz.html', 'H1 auf H3', 'Sprung im Rechtstext');
    },
    'CL06 Links und Knöpfe: ohne Namen = Mangel, nichtssagend = Hinweis' => static function () use ($bau, $einbau, $genau, $keine): void {
        $s = H::seite(); $s['bausteine'][0]['knopf']['text'] = 'Mehr';
        $genau($bau($s), 'hinweis', 'barrierefreiheit', 'bausteine.0.knopf.text', 'nichtssagender Link-Text „Mehr“', 'Knopf „Mehr“ am Feld des Bauplans');
        $genau($einbau($bau(H::seite()), '<p>Alles Weitere <a href="#anmeldung">hier</a>.</p>'), 'hinweis', 'barrierefreiheit', 'index.html', '„hier“', 'Link „hier“');
        $genau($einbau($bau(H::seite()), '<a href="#anmeldung"> </a>'), 'mangel', 'barrierefreiheit', 'index.html', 'Link ohne zugänglichen Namen', 'leerer Link');
        $genau($einbau($bau(H::seite()), '<button type="button"></button>'), 'mangel', 'barrierefreiheit', 'index.html', 'Knopf ohne zugänglichen Namen', 'leerer Knopf');
        $keine($einbau($bau(H::seite()), '<a href="#anmeldung" aria-label="Zur Anmeldung">→</a>'), 'aria-label ist ein Name, auch wenn der Inhalt nur ein Zeichen ist');
    },
    'CL07 Sprache: <html lang> fehlt oder ist kein Sprachkürzel = Mangel' => static function () use ($bau, $tausch, $genau): void {
        $genau($tausch($bau(H::seite()), '<html lang="de-AT">', '<html>'), 'mangel', 'barrierefreiheit', 'index.html', 'lang', 'lang fehlt');
        $genau($tausch($bau(H::seite()), '<html lang="de-AT">', '<html lang="deutsch">', 'impressum.html'), 'mangel', 'barrierefreiheit', 'impressum.html', 'lang', 'lang ist kein Kürzel');
    },
    'CL08 Tastatur: tabindex über 0 = Mangel, 0 und -1 nicht' => static function () use ($bau, $einbau, $genau, $keine): void {
        $genau($einbau($bau(H::seite()), '<p tabindex="2">Zuerst ich</p>'), 'mangel', 'barrierefreiheit', 'index.html', 'tabindex="2"', 'tabindex 2');
        $keine($einbau($bau(H::seite()), '<p tabindex="0">Auch erreichbar</p><p tabindex="-1">Nur per Skript</p>'), 'tabindex 0 und -1');
    },
    'CL09 Fokusrahmen: weggenommen ohne Ersatz = Mangel' => static function () use ($bau, $genau, $keine): void {
        $css = static function (string $regel) use ($bau): string {
            $o = $bau(H::seite());
            file_put_contents("$o/es/lp.css", "\n$regel\n", FILE_APPEND);
            return $o;
        };
        $genau($css('a:focus { outline: none; }'), 'mangel', 'barrierefreiheit', 'es/lp.css', '„a:focus“', 'outline: none ohne Ersatz');
        $genau($css('@media (min-width: 40rem) { .lp-knopf:focus-visible { outline: 0 !important } }'), 'mangel', 'barrierefreiheit', 'es/lp.css', '„.lp-knopf:focus-visible“', 'outline: 0 in @media');
        $keine($css('a:focus { outline: none; box-shadow: 0 0 0 0.2rem #000; }'), 'Ersatz in derselben Regel (Schatten)');
        $keine($css('.lp-knopf:focus { outline: 0; } .lp-knopf:focus-visible { outline: 0.2rem solid #000; }'), 'Ersatz in der Regel für :focus-visible');
        $keine($css('a:focus { outline: 0.1875rem solid #000; }'), 'ein Rahmen von 0,1875 rem ist kein outline: 0');
    },
    'CL10 Formular: Hinweis auf die Datenschutzerklärung fehlt = Mangel' => static function () use ($bau, $tausch, $genau, $keine, $anmeldung): void {
        $s = H::seite();
        $ohne = ' Mehr dazu in der <a href="datenschutz.html">Datenschutzerklärung</a>.';
        $genau($tausch($bau($s), $ohne, ''), 'mangel', 'formular', 'bausteine.' . $anmeldung($s), 'datenschutz.html', 'kein Link im Formular (der in der Fußzeile zählt nicht)');
        $s['bausteine'][$anmeldung($s)]['text'] = 'Trag dich ein.';
        $keine($tausch($tausch($bau($s), $ohne, ''), '<p>Trag dich ein.</p>', '<p>Trag dich ein. Mehr in der <a href="datenschutz.html">Datenschutzerklärung</a>.</p>'), 'Link im Abschnitt über dem Formular genügt');
    },
    'CL11 Formular: Einwilligung fehlt, ist kein Häkchen oder vorangehakt = Mangel' => static function () use ($bau, $tausch, $genau, $anmeldung): void {
        $s = H::seite();
        $a = 'bausteine.' . $anmeldung($s);
        $pflicht = 'name="einwilligung" type="checkbox" value="ja" required';
        $genau($tausch($bau($s), $pflicht, "$pflicht checked"), 'mangel', 'formular', $a, 'vorangehakt', 'Einwilligung vorangehakt');
        $genau($tausch($bau($s), '<input id="lp-f-einwilligung" ' . $pflicht . '>', ''), 'mangel', 'formular', $a, 'Einwilligung (name="einwilligung") fehlt', 'Einwilligung fehlt');
        $genau($tausch($bau($s), $pflicht, 'name="einwilligung" type="hidden" value="ja"'), 'mangel', 'formular', $a, 'kein Häkchen', 'Einwilligung als verstecktes Feld');
        $nl = H::seite(); $nl['bausteine'][1]['newsletter'] = true;
        $genau($tausch($bau($nl), 'name="newsletter" type="checkbox" value="ja"', 'name="newsletter" type="checkbox" value="ja" checked'), 'mangel', 'formular', $a, '„newsletter“ ist vorangehakt', 'Newsletter vorangehakt');
    },
    'CL12 Formular: Feld ohne Beschriftung = Mangel' => static function () use ($bau, $tausch, $genau, $anmeldung): void {
        $s = H::seite();
        $genau($tausch($bau($s), '<label for="lp-f-email">E-Mail-Adresse</label>', ''), 'mangel', 'formular', 'bausteine.' . $anmeldung($s), '„email“ ohne Beschriftung', 'Label fehlt');
        $genau($tausch($bau($s), '<label for="lp-f-email">E-Mail-Adresse</label>', '<label for="lp-f-email"> </label>'), 'mangel', 'formular', 'bausteine.' . $anmeldung($s), '„email“ ohne Beschriftung', 'Label leer');
    },
    'CL13 Formular: Pflichtfeld ohne required, „freiwillig“ mit required = Mangel' => static function () use ($bau, $tausch, $genau, $anmeldung): void {
        $s = H::seite();
        $a = 'bausteine.' . $anmeldung($s);
        $genau($tausch($bau($s), 'autocomplete="email" required>', 'autocomplete="email">'), 'mangel', 'formular', $a, 'Pflichtfeld „email“ ohne required', 'E-Mail ohne required');
        $s['bausteine'][1]['newsletter'] = true;
        $genau($tausch($bau($s), 'name="newsletter" type="checkbox" value="ja"', 'name="newsletter" type="checkbox" value="ja" required'), 'mangel', 'formular', $a, 'heißt freiwillig, ist aber required', 'Newsletter als Pflicht');
    },
    'CL14 Formular: Honigtopf fehlt oder ist erreichbar = Mangel' => static function () use ($bau, $tausch, $genau, $anmeldung): void {
        $s = H::seite();
        $a = 'bausteine.' . $anmeldung($s);
        $o = $bau($s);
        file_put_contents("$o/index.html", (string) preg_replace('#<div class="form-hp".*?</div>\n#s', '', (string) file_get_contents("$o/index.html")));
        $genau($o, 'mangel', 'formular', $a, 'Honigtopf (.form-hp, name="website") fehlt', 'Honigtopf fehlt');
        $genau($tausch($bau($s), 'type="text" tabindex="-1" autocomplete="off"', 'type="text" autocomplete="off"'), 'mangel', 'formular', $a, 'erreichbar', 'Honigtopf per Tastatur erreichbar');
        $genau($tausch($bau($s), '<div class="form-hp" aria-hidden="true">', '<div class="form-hp">'), 'mangel', 'formular', $a, 'erreichbar', 'Honigtopf für Bildschirmleser sichtbar');
    },
    'CL15 Formular: Ziel ohne https oder ohne Ziel = Mangel' => static function () use ($bau, $tausch, $genau): void {
        $genau($tausch($bau(H::seite()), 'action="https://beispiel.invalid/anmelden"', 'action="http://beispiel.invalid/anmelden"'), 'mangel', 'formular', 'profil.formularziel', 'nicht https', 'Ziel mit http');
        $p = H::profilKopie();
        $j = json_decode((string) file_get_contents("$p/profil.json"), true);
        $j['formularziel'] = '';
        file_put_contents("$p/profil.json", json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $o = $bau(H::seite(), $p);
        $genau($o, 'mangel', 'formular', 'profil.formularziel', 'nicht https („“)', 'ohne Formularziel');
        T::contains(Checkliste::pruefen($o)[0]->kunde ?? '', 'noch kein Ziel', 'Kundentext: noch kein Ziel');
    },
    'CL16 Musterbuch: keine Seite hat einen Mangel' => static function () use ($w, $texte): void {
        $seiten = glob("$w/musterbuch/*", GLOB_ONLYDIR) ?: [];
        T::ok(count($seiten) > 20, 'Musterbuch-Seiten gefunden: ' . count($seiten));
        foreach ($seiten as $s) {
            $p = Checkliste::pruefen($s);
            T::eq($texte(array_values(array_filter($p, static fn ($x) => $x->art === 'mangel'))), '', basename($s) . ': keine Mängel');
            T::ok(array_diff(array_column(array_map(static fn ($x) => $x->alsArray(), $p), 'bereich'), ['barrierefreiheit', 'formular']) === [], basename($s) . ': nur bekannte Bereiche');
        }
    },
    'CL17 Prüfer unberührt: dieselben Befunde vor und nach der Checkliste, die Checkliste schreibt nichts' => static function () use ($w): void {
        $stand = static function (string $o): array {
            $h = [];
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($o, FilesystemIterator::SKIP_DOTS)) as $f) {
                $h[substr($f->getPathname(), strlen($o) + 1)] = hash_file('sha256', $f->getPathname()) . ' ' . $f->getMTime();
            }
            ksort($h);
            return $h;
        };
        $als = static fn (array $befunde): array => array_map(static fn ($b) => $b->alsArray(), $befunde);
        foreach (glob("$w/musterbuch/*", GLOB_ONLYDIR) ?: [] as $s) {
            $o = T::tmp() . '/' . basename($s);
            Dateien::kopieren($s, $o);
            $vorher = $als(Pruefer::pruefen($o, '2026-09-27T12:00'));
            $dateien = $stand($o);
            Checkliste::pruefen($o);
            T::eq($stand($o), $dateien, basename($s) . ': Checkliste ändert keine Datei');
            T::eq($als(Pruefer::pruefen($o, '2026-09-27T12:00')), $vorher, basename($s) . ': Prüfer-Befunde unverändert');
            foreach ($vorher as $befund) {
                T::eq(array_keys($befund), ['art', 'pfad', 'technik', 'kunde'], basename($s) . ': Prüfer-Befund ohne Feld bereich');
            }
        }
    },
    'CL18 Kommandozeile: JSON-Lines mit Bereich, Exit 0/1/2' => static function () use ($bau, $tausch, $w): void {
        $lauf = static function (array $args) use ($w): array {
            $p = proc_open(array_merge([PHP_BINARY, "$w/lp/checkliste.php"], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
            return [proc_close($p), $out, $err];
        };
        T::eq($lauf([$bau(H::seite())]), [0, '', ''], 'saubere Seite: Exit 0, keine Ausgabe');
        $s = H::seite(); $s['bausteine'][0]['knopf']['text'] = 'Mehr';
        [$rc, $out] = $lauf([$bau($s)]);
        T::eq($rc, 0, 'nur ein Hinweis: Exit 0');
        T::eq(json_decode(trim($out), true)['art'] ?? null, 'hinweis', 'der Hinweis steht da');
        [$rc, $out] = $lauf([$tausch($bau(H::seite()), '<html lang="de-AT">', '<html>')]);
        T::eq($rc, 1, 'Mangel: Exit 1');
        $z = json_decode(trim($out), true);
        T::eq(array_keys((array) $z), ['art', 'bereich', 'pfad', 'technik', 'kunde'], 'JSON-Line mit art, bereich, pfad, technik, kunde');
        T::eq([$z['art'] ?? '', $z['bereich'] ?? ''], ['mangel', 'barrierefreiheit'], 'Art und Bereich');
        [$rc, , $err] = $lauf([H::ordner(H::seite())]);
        T::eq($rc, 2, 'ungebaute Seite: Exit 2');
        T::contains($err, 'index.html fehlt', 'Meldung auf stderr');
        T::eq($lauf([])[0], 2, 'ohne Ordner: Exit 2');
        T::eq($lauf(['/gibt/es/nicht'])[0], 2, 'fehlender Ordner: Exit 2');
        T::contains((string) file_get_contents("$w/lp/checkliste.php"), "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }", 'nur auf der Kommandozeile');
    },
];
