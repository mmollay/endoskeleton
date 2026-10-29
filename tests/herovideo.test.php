<?php
/**
 * Kopfbereich mit YouTube-Video (3.61.0): Das Bild bleibt als Startbild mit Play-Knopf, erst der Klick lädt das Video
 * (derselbe Zwei-Klick-Weg wie beim Baustein „video“) — php8.2 tests/run.php herovideo
 *
 *   HV01 Katalog: Feld video_kennung (nur bei bild-rechts, seit 3.61.0), Klasse lp-hero-medien
 *   HV02 Prüfer: gültige Kennungen ohne Befund, ungültige (auch eine ganze Adresse, Zeilenumbruch am Ende) sperrend
 *   HV03 Prüfer: nur bei bild-rechts; bild-hinten und schlicht lehnen das Feld ab; ohne Bild gibt es kein Video
 *   HV04 Bau mit Video: Knopf mit Kennung/Titel/Beschriftung, Startbild ohne eigenen Alt-Text, Ersatzlink, kein iframe, keine Fremdquelle
 *   HV05 Bau ohne Video: wie bisher (Bild ohne Knopf, kein Hinweis)
 *   HV06 Anrede: „du“ und „Sie“ im Hinweis
 *   HV07 Kennung zählt nicht als Text (Sperrliste) und erscheint nur in den dafür vorgesehenen Attributen
 *   HV08 CSS: Rahmen 16:9 im Kopfbereich nur mit Tokens, Spalte schmal; keine Inline-Styles im HTML
 *   HV09 Musterbuch und Skill zeigen das Feld; ein einziger Klick-Handler in lp.js (keine zweite Kopie)
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Pruefer;
use Endo\Lp\Schema;

$k = Katalog::laden();
$kennung = 'AbCdEfGhIjk';
/** Der Kopfbereich des Katalog-Beispiels (bild-rechts mit Bild), optional mit Änderungen; dazu die Pflichtbausteine. */
$seite = static function (array $hero = []) use ($k): array {
    $s = H::seite();
    $s['bausteine'][0] = $hero + $s['bausteine'][0];
    return $s;
};
$mitVideo = static fn (string $id = 'AbCdEfGhIjk') => $seite(['video_kennung' => $id]);
/** Befunde des Schemas für diese Seite (sperrend, als Text). */
$schema = static fn (array $s): string => H::texte(Schema::pruefen($s, $k, Profil::laden(H::testprofil()), T::tmp()));
/** Baut eine Seite und gibt [html, xpath] zurück. */
$bau = static function (array $s, ?string $profil = null): array {
    $o = H::ordner($s, null, $profil);
    T::eq(H::texte(Bauer::bauen($o)->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$o/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d)];
};
$css = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.css');

return [
    'HV01 Katalog: Feld video_kennung nur bei bild-rechts, seit 3.61.0; neue Klasse lp-hero-medien' => static function () use ($k): void {
        $hero = $k->baustein('hero');
        $f = $hero['felder']['video_kennung'] ?? null;
        T::ok(is_array($f), 'Feld video_kennung im Kopfbereich');
        T::eq($f['typ'] ?? null, 'kennung', 'Typ kennung (wie beim Baustein video)');
        T::eq($f['label'] ?? null, 'YouTube-Video (Kennung)', 'Beschriftung');
        T::eq($f['nur_bei'] ?? null, ['bild-rechts'], 'nur bei bild-rechts');
        T::eq($f['seit'] ?? null, '3.61.0', 'seit 3.61.0');
        T::ok(!($f['pflicht'] ?? false) && !isset($f['pflicht_bei']), 'freiwillig');
        T::eq($hero['klassen']['lp-hero-medien'] ?? null, '3.61.0', 'Klasse lp-hero-medien seit 3.61.0');
        T::ok(version_compare((string) $hero['geaendert'], '3.61.0', '>='), 'Kopfbereich zuletzt geändert ab 3.61.0 (3.62.0: video_datei)');
        T::ok(!isset($hero['beispiel']['video_kennung']), 'das Katalog-Beispiel bleibt ohne Video (sonst kopiert es jeder mit)');
    },
    'HV02 Prüfer: Format ^[A-Za-z0-9_-]{11}$ — gültig ohne Befund, ungültig sperrend' => static function () use ($schema, $mitVideo, $seite): void {
        foreach (['AbCdEfGhIjk', 'a-_B0123456', '___________', '-----------'] as $id) {
            T::eq($schema($mitVideo($id)), '', "gültig: $id");
        }
        $schlecht = ['zu kurz' => 'AbCdEfGhIj', 'zu lang' => 'AbCdEfGhIjkl', 'leer' => '', 'Leerzeichen' => 'AbCdEf hIjk', 'Schrägstrich' => 'AbCdEf/hIjk',
            'Zeilenumbruch am Ende' => "AbCdEfGhIj\n", 'Umlaut' => 'AbCdEfGhIjä', 'ganze Adresse' => 'https://www.youtube-nocookie.com/embed/AbCdEfGhIjk',
            'watch-Adresse' => 'https://www.youtube.com/watch?v=AbCdEfGhIjk', 'Anführungszeichen' => 'AbCdEf"hIjk', 'Spitzklammer' => 'AbCdEf<hIjk'];
        foreach ($schlecht as $was => $id) {
            $t = $schema($mitVideo($id));
            T::contains($t, '[sperrend] bausteine.0.video_kennung', "ungültig ($was) ist sperrend am Feld");
        }
        foreach ([12345678901, true, ['AbCdEfGhIjk'], null] as $i => $x) {
            T::contains($schema($seite(['video_kennung' => $x])), '[sperrend] bausteine.0.video_kennung', "kein Text (Fall $i) ist sperrend");
        }
    },
    'HV03 Prüfer: nur bild-rechts; bild-hinten und schlicht lehnen es ab, ohne Bild gibt es kein Video' => static function () use ($schema, $seite): void {
        T::eq($schema($seite(['video_kennung' => 'AbCdEfGhIjk'])), '', 'bild-rechts mit Bild: gültig');
        foreach (['bild-hinten', 'schlicht'] as $v) {
            $t = $schema($seite(['variante' => $v, 'video_kennung' => 'AbCdEfGhIjk']));
            T::contains($t, '[sperrend] bausteine.0.video_kennung', "$v: Feld abgelehnt");
            T::contains($t, 'bild-rechts', "$v: Kundentext nennt die erlaubte Variante");
            T::contains($t, 'Abschnitt 1', "$v: Kundentext nennt den Abschnitt");
        }
        $ohneBild = $seite(['video_kennung' => 'AbCdEfGhIjk']);
        unset($ohneBild['bausteine'][0]['bild']);
        $t = $schema($ohneBild);
        T::contains($t, 'bausteine.0.bild', 'bild-rechts ohne Bild: das Bild fehlt (Pflicht), also auch kein Video');
        T::eq($schema($seite()), '', 'ohne das Feld ändert sich nichts');
    },
    'HV04 Bau mit Video: Startbild im Knopf, Kennung und Titel am Knopf, Ersatzlink, kein iframe vor dem Klick' => static function () use ($bau, $mitVideo): void {
        [$h, $x] = $bau($mitVideo());
        T::notContains($h, '<iframe', 'kein iframe im HTML');
        T::notContains($h, 'youtube-nocookie', 'keine Einbettungsadresse im HTML');
        $knopf = $x->query('//section[contains(@class,"lp-hero--bild-rechts")]//button[contains(@class,"lp-video-start")]');
        T::eq($knopf->length, 1, 'ein Start-Knopf im Kopfbereich');
        $k1 = $knopf->item(0);
        T::eq($k1?->getAttribute('type'), 'button', 'type=button');
        T::eq($k1?->getAttribute('data-lp-kennung'), 'AbCdEfGhIjk', 'Kennung am Knopf');
        T::eq($k1?->getAttribute('data-lp-titel'), 'Gesund durch den Winter', 'Titel des Kopfbereichs fürs iframe');
        T::eq($k1?->getAttribute('aria-label'), 'Video abspielen: Gesund durch den Winter (lädt Inhalte von YouTube)', 'Knopf beschriftet (Hinweis auf YouTube)');
        $img = $x->query('//section[contains(@class,"lp-hero--bild-rechts")]//button[contains(@class,"lp-video-start")]/img');
        T::eq($img->length, 1, 'das Startbild steckt im Knopf');
        T::eq($img->item(0)?->getAttribute('alt'), '', 'Startbild ohne eigenen Alt-Text (der Knopf trägt die Beschriftung)');
        T::eq($img->item(0)?->getAttribute('src'), 'es/bilder/beispiel-vortrag.webp', 'lokales Bild');
        T::eq($img->item(0)?->getAttribute('fetchpriority'), 'high', 'Startbild bleibt das wichtige Bild der Seite');
        T::eq($x->query('//img[contains(@class,"lp-hero-bild")]')->length, 0, 'kein zweites, loses Kopfbild daneben');
        T::eq($x->query('//div[contains(@class,"lp-hero-medien")]//div[contains(@class,"lp-video-rahmen")]')->length, 1, 'Rahmen im Medien-Block');
        $link = $x->query('//div[contains(@class,"lp-hero-medien")]//p[contains(@class,"lp-video-link")]/a[@href="https://www.youtube.com/watch?v=AbCdEfGhIjk"]');
        T::eq($link->length, 1, 'Link zu YouTube als Ersatz ohne JavaScript');
        T::contains(trim((string) $x->query('//p[contains(@class,"lp-video-link")]')->item(0)?->textContent), 'lädst du das Video von YouTube', 'Hinweis auf das Laden von YouTube');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//h1')->length, 1, 'genau eine H1');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")]')->length, 1, 'der Knopf des Kopfbereichs bleibt');
        foreach ($x->query('//*[@src]') as $e) { T::ok(!preg_match('#^(https?:)?//#', $e->getAttribute('src')), 'nur lokale Quellen: ' . $e->getAttribute('src')); }
        $adressen = [];
        foreach ($x->query('//section[contains(@class,"lp-hero")]//*[@href]') as $e) {
            if (preg_match('#^(https?:)?//#', $e->getAttribute('href'))) { $adressen[] = $e->getAttribute('href'); }
        }
        T::eq($adressen, ['https://www.youtube.com/watch?v=AbCdEfGhIjk'], 'außer dem Ersatzlink keine fremde Adresse im Kopfbereich');
    },
    'HV05 Bau ohne Video: wie bisher' => static function () use ($bau, $seite): void {
        [$h, $x] = $bau($seite());
        $img = $x->query('//section[contains(@class,"lp-hero--bild-rechts")]/div/img[contains(@class,"lp-hero-bild")]');
        T::eq($img->length, 1, 'loses Kopfbild');
        T::eq($img->item(0)?->getAttribute('alt'), 'Stilisierte Bühne mit Rednerpult und Publikum', 'Alt-Text bleibt');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//button')->length, 0, 'kein Knopf');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//*[contains(@class,"lp-hero-medien") or contains(@class,"lp-video-link")]')->length, 0, 'kein Medien-Block, kein Hinweis');
        T::notContains($h, 'youtube', 'nichts von YouTube im HTML');
        $s = $seite(['variante' => 'bild-hinten']);
        [, $x] = $bau($s);
        T::eq($x->query('//section[contains(@class,"lp-hero--bild-hinten")]//img[contains(@class,"lp-hero-bild")]')->length, 1, 'bild-hinten unverändert');
    },
    'HV06 Anrede: du und Sie' => static function () use ($bau, $mitVideo): void {
        [, $x] = $bau($mitVideo());
        T::contains((string) $x->query('//p[contains(@class,"lp-video-link")]')->item(0)?->textContent, 'lädst du das Video von YouTube. Du kannst es auch', 'du');
        $p = H::profilKopie();
        $j = json_decode((string) file_get_contents("$p/profil.json"), true);
        $j['anrede'] = 'Sie';
        file_put_contents("$p/profil.json", json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        [, $x] = $bau($mitVideo(), $p);
        T::contains((string) $x->query('//p[contains(@class,"lp-video-link")]')->item(0)?->textContent, 'laden Sie das Video von YouTube. Sie können es auch', 'Sie');
    },
    'HV07 Die Kennung ist kein Fließtext: nie als Sperrwort gefunden, nur in den dafür vorgesehenen Attributen' => static function () use ($mitVideo): void {
        $id = 'garantiertA';   // 11 Zeichen und beginnt mit einem Sperrwort
        $p = H::profilMitListen(['garantiert'], ['Wirkung']);
        $o = H::ordner($mitVideo($id), null, $p);
        T::eq(H::texte(H::sperrend(Bauer::bauen($o)->befunde)), '', 'Bau ohne sperrende Befunde');
        $liste = array_filter(Pruefer::pruefen($o, '2026-10-29T12:00'), static fn ($b) => str_starts_with($b->technik, 'Sperrliste: ') || str_starts_with($b->technik, 'Grenzwort: '));
        T::eq(H::texte(array_values($liste)), '', 'die Kennung wird nicht auf Sperrwörter geprüft');
        $html = (string) file_get_contents("$o/index.html");
        T::eq(substr_count($html, $id), 2, 'zweimal im HTML: Knopf (data-lp-kennung) und Ersatzlink (href)');
    },
    'HV08 CSS: Medien-Block im Kopfbereich nur mit Tokens, ohne Inline-Styles' => static function () use ($css, $bau, $mitVideo): void {
        $c = $css();
        T::ok(preg_match('/\.lp-hero-medien\s*\{[^}]*display:\s*grid;[^}]*\}/', $c) === 1, '.lp-hero-medien ordnet Rahmen und Hinweis untereinander');
        T::ok(preg_match('/\.lp-hero-medien\s*\{[^}]*gap:\s*var\(--space-/', $c) === 1, 'Abstand aus den Tokens');
        T::ok(preg_match('/\.lp-hero-medien\s*\{[^}]*min-width:\s*0;/', $c) === 1, 'Spalte darf schrumpfen (min-width: 0), sonst sprengt das Video die Seite');
        preg_match_all('/\.lp-hero-medien[^{]*\{[^}]*\}/', $c, $m);
        foreach ($m[0] as $regel) {
            T::ok(!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|\b\d+px\b/', $regel), 'nur Tokens, keine festen Farben oder px: ' . substr($regel, 0, 50));
        }
        T::ok(preg_match('/\.lp-video-rahmen\s*\{[^}]*aspect-ratio:\s*16\s*\/\s*9;/', $c) === 1, 'der Videorahmen bleibt 16:9 (geteilt mit dem Baustein video)');
        [$h] = $bau($mitVideo());
        T::notContains($h, ' style=', 'kein Inline-Style im HTML');
    },
    'HV09 Musterbuch und Skill zeigen das Feld; lp.js hat genau einen Klick-Handler für Videos' => static function () use ($k): void {
        $w = H::wurzel();
        $f = json_decode((string) file_get_contents("$w/lp/musterbuch/hero--bild-rechts.json"), true);
        T::eq($f['bausteine'][0]['video_kennung'] ?? null, 'AbCdEfGhIjk', 'Musterbuch hero--bild-rechts trägt das Video');
        $skill = (string) file_get_contents("$w/lp/SKILL.md");
        T::contains($skill, '`video_kennung`', 'Skill nennt das Feld');
        T::contains($skill, 'nur bei bild-rechts', 'Skill nennt die Varianten-Einschränkung');
        T::contains($skill, 'seit 3.61.0', 'Skill nennt die Version des Feldes');
        $js = (string) file_get_contents("$w/lp/lp.js");
        T::eq(substr_count($js, "'.lp-video-start'"), 1, 'ein Handler für .lp-video-start');
        T::eq(substr_count($js, 'youtube-nocookie.com/embed/'), 1, 'die Einbettungsadresse steht nur einmal in lp.js');
        $php = (string) file_get_contents("$w/lp/bausteine/hero.php") . (string) file_get_contents("$w/lp/bausteine/video.php");
        T::notContains($php, 'youtube-nocookie.com', 'keine Einbettungsadresse in den Vorlagen');
        T::eq(substr_count($php, 'lp-video-start'), 0, 'die Vorlagen schreiben den Knopf nicht selbst, sondern über eine gemeinsame Hilfe');
    },
    'HV10 Der Baustein allein (am Prüfer vorbei): nur bild-rechts zeigt ein Video — bild-hinten und schlicht nie, auch wenn video_kennung im Plan steht' => static function () use ($seite): void {
        $plan = $seite();
        $o = H::ordner($plan);
        $kontext = new \Endo\Lp\Kontext(Profil::laden("$o/profil"), $plan['meta'], $o);
        $zeige = static function (string $variante) use ($plan, $kontext): string {
            $vars = ['b' => ['variante' => $variante, 'video_kennung' => 'AbCdEfGhIjk'] + $plan['bausteine'][0], 'k' => $kontext, 'nr' => 1];
            ob_start();
            (static function (array $__vars): void { extract($__vars); require H::wurzel() . '/lp/bausteine/hero.php'; })($vars);
            return (string) ob_get_clean();
        };
        T::contains($zeige('bild-rechts'), 'lp-video-start', 'bild-rechts: das Video ist da (Gegenstück)');
        foreach (['bild-hinten', 'schlicht'] as $v) {
            $h = $zeige($v);
            T::notContains($h, 'lp-video-start', "$v: kein Start-Knopf");
            T::notContains($h, 'lp-hero-medien', "$v: kein Medien-Block");
            T::notContains($h, 'youtube', "$v: nichts von YouTube");
        }
        T::contains($zeige('bild-hinten'), 'lp-hero-bild', 'bild-hinten: das Bild bleibt ein Hintergrundbild');
    },
];
