<?php
/**
 * Knopf nach dem Ende des eigenen Videos (3.75.0): Im Kopfbereich zeigt `video_ende_knopf: true` nach dem Ende des Videos das letzte Bild verschwommen und
 * abgedunkelt, darüber den Anmelde-Knopf des Kopfbereichs (derselbe Text, dasselbe Ziel) und daneben „Nochmal ansehen“. Vorgabe AUS: ohne das Feld baut jede Seite
 * Byte für Byte wie bisher (die Musterseiten ändern sich nicht). Das Verhalten im Browser prüft ci/browser.js — php8.2 tests/run.php videoende
 *
 *   VE01 Katalog: Feld video_ende_knopf (janein, seit 3.75.0, braucht Videodatei und Knopf), Klassen, Beispiele unverändert
 *   VE02 Schema: false immer gültig; true nur mit eigenem Video und Knopf; nur ein Wahrheitswert
 *   VE03 Bau AUS (Feld fehlt oder false): Byte für Byte derselbe Kopfbereich wie vor 3.75.0
 *   VE04 Bau AN: Rahmen mit Marke, Überlagerung (hidden) mit Knopf (Text und Ziel des Kopfbereichs) und „Nochmal ansehen“; das Video bleibt wie es war
 *   VE05 Maskierung: ein Knopftext mit Markup bleibt Text; kein Inline-Style, keine Fremdquelle
 *   VE06 CSS: nur Tokens, Verwischung und Abdunkelung am Video, Überlagerung füllt den Rahmen, Druck zeigt sie nie
 *   VE07 lp.js: hört auf „ended“, sperrt das Video (inert) solange die Überlagerung steht, „Nochmal ansehen“ startet nur auf Klick
 *   VE08 Musterbuch, Skill und Werkstatt-Text kennen das Feld
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Schema;

const VE_DATEI = 'vid/eigen-0123456789abcdef.mp4';
/** Kleinste „MP4-Datei“ für die Prüfung der Magic Bytes: 24 Byte, „ftyp“ an Stelle 4–8. */
const VE_KOPF = "\0\0\0\x18ftypmp42\0\0\0\0mp42isom";

$k = Katalog::laden();
$schema = static fn (array $s, string $ordner): string => H::texte(Schema::pruefen($s, $k, Profil::laden(H::testprofil()), $ordner));
/** Kopfbereich des Katalog-Beispiels mit Änderungen (null löscht ein Feld). */
$seite = static function (array $hero = []): array {
    $s = H::seite();
    $s['bausteine'][0] = array_filter($hero + $s['bausteine'][0], static fn ($w) => $w !== null);
    return $s;
};
/** Ordner mit der Probedatei des Videos für den Bauplan. */
$mitDatei = static function (array $plan): string {
    $o = H::ordner($plan);
    @mkdir("$o/vid", 0700, true);
    file_put_contents("$o/" . VE_DATEI, VE_KOPF . str_repeat('x', 100));
    return $o;
};
$datei = static fn (array $mehr = []): array => $mehr + ['video_datei' => VE_DATEI];
$bau = static function (string $ordner): array {
    T::eq(H::texte(Bauer::bauen($ordner)->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$ordner/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d)];
};
/** Der Kopfbereich im gebauten HTML (von <section …lp-hero…> bis </section>). */
$kopf = static function (string $html): string {
    preg_match('~<section class="lp-block lp-hero.*?</section>~s', $html, $m);
    return $m[0] ?? '';
};
$css = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.css');
$js = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.js');

return [
    'VE01 Katalog: video_ende_knopf im Kopfbereich (janein, seit 3.75.0, braucht Videodatei und Knopf), neue Klassen, Beispiele unverändert' => static function () use ($k): void {
        $hero = $k->baustein('hero');
        $f = $hero['felder']['video_ende_knopf'] ?? null;
        T::ok(is_array($f), 'Feld video_ende_knopf im Kopfbereich');
        T::eq($f['typ'] ?? null, 'janein', 'Typ janein');
        T::eq($f['seit'] ?? null, '3.75.0', 'seit 3.75.0');
        T::eq($f['an_braucht'] ?? null, ['video_datei', 'knopf'], 'an nur mit eigenem Video und Knopf');
        T::ok(!($f['pflicht'] ?? false) && !isset($f['pflicht_bei']) && !isset($f['pflicht_wenn']), 'freiwillig');
        T::ok(!isset($f['nur_bei']), 'ohne nur_bei: false bleibt in jeder Darstellung gültig (das Panel schreibt es nach einem Wechsel der Darstellung)');
        T::ok(!isset($hero['beispiel']['video_ende_knopf']), 'das Katalog-Beispiel bleibt ohne den Knopf');
        T::eq($hero['geaendert'], '3.76.0', 'Kopfbereich geändert 3.76.0 (drei Knöpfe oben, nach 3.75.0)');
        foreach (['lp-video-ende', 'lp-video-ende-knopf', 'lp-video-nochmal'] as $klasse) {
            T::eq($hero['klassen'][$klasse] ?? null, '3.75.0', "Klasse $klasse seit 3.75.0");
        }
        T::eq($k->baustein('video')['geaendert'], '3.62.0', 'Baustein video bleibt unverändert (die Marke gilt nur im Kopfbereich)');
    },
    'VE02 Schema: false immer gültig; true nur mit eigenem Video und Knopf; nur ein Wahrheitswert' => static function () use ($schema, $seite, $datei, $mitDatei): void {
        $o = $mitDatei($seite($datei()));
        T::eq($schema($seite($datei(['video_ende_knopf' => true])), $o), '', 'true mit Video und Knopf: gültig');
        T::eq($schema($seite($datei(['video_ende_knopf' => false])), $o), '', 'false mit Video: gültig');
        T::eq($schema($seite(['video_ende_knopf' => false]), $o), '', 'false ohne Video: gültig');
        T::eq($schema($seite(['video_ende_knopf' => false, 'variante' => 'schlicht', 'bild' => null]), $o), '', 'false bei „schlicht“: gültig');
        T::eq($schema($seite(), $o), '', 'ohne das Feld: unverändert gültig');
        $t = $schema($seite(['video_ende_knopf' => true]), $o);
        T::contains($t, '[sperrend] bausteine.0.video_ende_knopf', 'true ohne Video: sperrend am Feld');
        T::contains($t, 'Eigenes Video', 'Kundentext nennt, was fehlt');
        T::contains($t, 'Abschnitt 1', 'Kundentext nennt den Abschnitt');
        $t = $schema($seite(['video_kennung' => 'AbCdEfGhIjk', 'video_ende_knopf' => true]), $o);
        T::contains($t, '[sperrend] bausteine.0.video_ende_knopf', 'true mit YouTube-Video: sperrend (nur das eigene Video meldet sein Ende)');
        $t = $schema($seite($datei(['video_ende_knopf' => true, 'knopf' => null])), $o);
        T::contains($t, '[sperrend] bausteine.0.video_ende_knopf', 'true ohne Knopf im Kopfbereich: sperrend');
        T::contains($t, 'Knopf', 'Kundentext nennt den Knopf');
        foreach (['ja' => 'ja', 'eins' => 1, 'Text wahr' => 'true', 'Liste' => [true], 'leer' => '', 'null' => null] as $was => $x) {
            $s = $seite($datei());
            $s['bausteine'][0]['video_ende_knopf'] = $x;
            T::contains($schema($s, $o), '[sperrend] bausteine.0.video_ende_knopf', "kein Wahrheitswert ($was): sperrend");
        }
    },
    'VE03 Bau AUS (Feld fehlt oder false): derselbe Kopfbereich Byte für Byte, keine Überlagerung' => static function () use ($seite, $datei, $mitDatei, $bau, $kopf): void {
        [$ohne] = $bau($mitDatei($seite($datei())));
        [$aus, $x] = $bau($mitDatei($seite($datei(['video_ende_knopf' => false]))));
        T::eq($kopf($aus), $kopf($ohne), 'false baut denselben Kopfbereich wie ohne das Feld');
        T::contains($kopf($ohne), '<video class="lp-video-datei"', 'Testaufbau: der Kopfbereich hat sein Video');
        foreach ([$ohne, $aus] as $html) {
            T::notContains($html, 'lp-video-ende', 'keine Überlagerung im HTML');
            T::notContains($html, 'data-lp-videoende', 'keine Marke am Rahmen');
            T::notContains($html, 'lp-video-nochmal', 'kein „Nochmal ansehen“');
        }
        T::eq($x->query('//*[@data-lp-videoende]')->length, 0, 'kein Rahmen mit Marke');
    },
    'VE04 Bau AN: Rahmen mit Marke, Überlagerung (hidden) mit Knopf von oben und „Nochmal ansehen“, das Video bleibt wie es war' => static function () use ($seite, $datei, $mitDatei, $bau, $kopf): void {
        [$ohne] = $bau($mitDatei($seite($datei())));
        [$html, $x] = $bau($mitDatei($seite($datei(['video_ende_knopf' => true]))));
        $rahmen = $x->query('//section[contains(@class,"lp-hero--bild-rechts")]//div[contains(@class,"lp-hero-medien")]//div[contains(@class,"lp-video-rahmen")][@data-lp-videoende]');
        T::eq($rahmen->length, 1, 'ein Rahmen mit der Marke data-lp-videoende im Medien-Block des Kopfbereichs');
        $video = $x->query('.//video', $rahmen->item(0));
        T::eq($video->length, 1, 'das Video steht im Rahmen');
        T::eq($video->item(0)?->getAttribute('class'), 'lp-video-datei', 'Klasse des Videos unverändert');
        foreach (['controls', 'playsinline'] as $a) { T::ok($video->item(0)?->hasAttribute($a) === true, "$a bleibt"); }
        foreach (['autoplay', 'muted', 'loop', 'src', 'style', 'inert'] as $a) { T::ok($video->item(0)?->hasAttribute($a) === false, "kein Attribut $a am <video>"); }
        T::eq($video->item(0)?->getAttribute('preload'), 'none', 'preload=none bleibt');
        $ende = $x->query('.//div[contains(@class,"lp-video-ende")]', $rahmen->item(0));
        T::eq($ende->length, 1, 'eine Überlagerung im Rahmen');
        T::ok($ende->item(0)?->hasAttribute('hidden') === true, 'ohne Skript und vor dem Ende bleibt sie verborgen (hidden)');
        T::eq($ende->item(0)?->getAttribute('class'), 'lp-video-ende', 'Klasse lp-video-ende');
        $knopf = $x->query('.//a', $ende->item(0));
        T::eq($knopf->length, 1, 'ein Link in der Überlagerung');
        T::eq($knopf->item(0)?->getAttribute('href'), '#anmeldung', 'dasselbe Ziel wie der Knopf des Kopfbereichs');
        T::eq(trim((string) $knopf->item(0)?->textContent), 'Kostenlos anmelden', 'derselbe Text wie der Knopf des Kopfbereichs');
        $klassen = preg_split('/\s+/', (string) $knopf->item(0)?->getAttribute('class')) ?: [];
        T::eq(array_values(array_diff(['btn', 'btn-primary', 'lp-knopf', 'lp-video-ende-knopf'], $klassen)), [], 'Knopf wie der des Kopfbereichs (btn btn-primary lp-knopf) plus lp-video-ende-knopf');
        $nochmal = $x->query('.//button[@type="button"][contains(@class,"lp-video-nochmal")]', $ende->item(0));
        T::eq($nochmal->length, 1, 'ein „Nochmal ansehen“ als Knopf (type=button)');
        T::eq(trim((string) $nochmal->item(0)?->textContent), 'Nochmal ansehen', 'Text „Nochmal ansehen“');
        // Der Knopf des Kopfbereichs links bleibt, die Überlagerung ersetzt ihn nicht.
        T::eq($x->query('//div[contains(@class,"lp-hero-text")]//a[contains(@class,"lp-knopf")]')->length, 1, 'der Knopf neben dem Titel bleibt');
        // Außer der Überlagerung und der Marke ändert sich am Kopfbereich nichts.
        $bereinigt = preg_replace(['~\n<div class="lp-video-ende" hidden>.*?</div>~s', '~ data-lp-videoende(?=>)~'], '', $kopf($html));
        T::eq($bereinigt, $kopf($ohne), 'ohne Überlagerung und Marke derselbe Kopfbereich wie ohne das Feld');
        T::eq(substr_count($html, 'class="lp-video-ende"'), 1, 'genau eine Überlagerung auf der Seite');
    },
    'VE05 Maskierung: Knopftext mit Markup bleibt Text; kein Inline-Style, keine Fremdquelle' => static function () use ($seite, $datei, $mitDatei, $bau, $kopf): void {
        $plan = $seite($datei(['video_ende_knopf' => true]));
        $plan['bausteine'][0]['knopf']['text'] = '<b onclick="x()">Jetzt</b> & "los"';
        [$html, $x] = $bau($mitDatei($plan));
        $ende = $x->query('//div[contains(@class,"lp-video-ende")]//a');
        T::eq(trim((string) $ende->item(0)?->textContent), '<b onclick="x()">Jetzt</b> & "los"', 'der Text steht als Text im Knopf');
        T::eq($x->query('//div[contains(@class,"lp-video-ende")]//b | //div[contains(@class,"lp-video-ende")]//*[@onclick]')->length, 0, 'kein Element aus dem Knopftext');
        T::notContains($kopf($html), 'style=', 'kein Inline-Style im Kopfbereich');
        T::notContains($kopf($html), 'http://', 'keine Fremdquelle (http) im Kopfbereich');
        T::eq($x->query('//iframe | //script[@src][not(starts-with(@src,"es/"))] | //link[@href][not(starts-with(@href,"es/"))]')->length, 0, 'kein iframe, kein fremdes Skript');
    },
    'VE06 CSS: nur Tokens, Verwischung und Abdunkelung am Video, Überlagerung füllt den Rahmen, Druck zeigt sie nie' => static function () use ($css): void {
        $c = $css();
        preg_match_all('/[^{}]*\.lp-video-(?:ende|nochmal)[^{]*\{[^}]*\}/', $c, $m);
        T::ok(count($m[0]) >= 4, 'Regeln gefunden (' . count($m[0]) . ')');
        foreach ($m[0] as $regel) {
            T::ok(!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|\b\d+px\b/', $regel), 'nur Tokens, keine festen Farben oder px: ' . trim(substr($regel, 0, 50)));
        }
        T::ok(preg_match('/:has\(\.lp-video-ende:not\(\[hidden\]\)\)\s+\.lp-video-datei\s*\{[^}]*filter:\s*blur\([^)]+\)\s*brightness\([^)]+\)/', $c) === 1, 'das Video wird verwischt (blur) und abgedunkelt (brightness), solange die Überlagerung nicht hidden ist (:has, keine Klasse von JS)');
        T::ok(preg_match('/\.lp-video-ende\s*\{[^}]*position:\s*absolute;[^}]*inset:\s*0;/', $c) === 1, 'die Überlagerung füllt den Rahmen');
        T::ok(preg_match('/\.lp-video-ende\[hidden\]\s*\{[^}]*display:\s*none/', $c) === 1, 'hidden gewinnt gegen display: flex');
        T::ok(preg_match('/\.lp-video-ende-knopf:focus-visible|\.lp-video-nochmal:focus-visible/', $c) === 1, 'sichtbarer Fokus für Knopf und „Nochmal ansehen“');
        T::ok(preg_match('/\.lp-video-nochmal:focus-visible\s*\{[^}]*var\(--focus\)/', $c) === 1, 'Fokusring mit dem Fokus-Token');
        $druck = (string) preg_replace('/^.*?@media print\s*\{/s', '', $c, 1);
        T::ok(preg_match('/\.lp-video-ende\b/', substr($druck, 0, (int) strpos($druck, '@media (prefers-reduced-motion'))) === 1, 'der Druck kennt die Überlagerung (versteckt sie)');
        // „weniger Bewegung“: die Überlagerung blendet höchstens ein, und die Regel für alle Übergänge der Seite gilt auch für sie.
        T::ok(preg_match('/prefers-reduced-motion:\s*reduce\)\s*\{[^@]*\.lp-seite \*,/', $c) === 1, 'bei „weniger Bewegung“ gibt es für die Seite keine Übergänge (gilt auch für die Überlagerung)');
        T::notContains($c, '@keyframes lp-video-ende', 'keine Keyframe-Animation, nur ein Übergang (Deckkraft), den „weniger Bewegung“ abstellt');
    },
    'VE07 lp.js: „ended“ zeigt die Überlagerung, das Video ist solange gesperrt, „Nochmal ansehen“ startet nur auf Klick, ohne autoplay' => static function () use ($js): void {
        $s = $js();
        T::contains($s, "'.lp-video-rahmen[data-lp-videoende]'", 'nur Rahmen mit der Marke');
        T::contains($s, "addEventListener('ended'", 'wartet auf das Ende des Videos');
        T::contains($s, '.inert = ', 'das Video ist gesperrt, solange die Überlagerung steht (Tab und Zeiger erreichen die Bedienleiste dahinter nicht)');
        T::notContains($s, 'classList', 'keine Klasse von JS: die Verwischung hängt per :has() an der Überlagerung');
        T::contains($s, "addEventListener('click'", 'Klick auf „Nochmal ansehen“');
        T::contains($s, 'currentTime = 0', 'beginnt von vorn');
        T::contains($s, 'exitFullscreen', 'aus dem Vollbild zurück, sonst bliebe die Überlagerung unsichtbar');
        T::contains($s, 'preventScroll: true', 'der Fokus auf den Knopf scrollt nicht selbst');
        T::eq(substr_count($s, '.play()'), 1, 'genau ein play(): nur „Nochmal ansehen“');
    },
    'VE08 Musterbuch, Skill und Werkstatt-Text kennen das Feld' => static function (): void {
        $w = H::wurzel();
        $m = json_decode((string) file_get_contents("$w/lp/musterbuch/hero--bild-rechts--video-ende.json"), true);
        T::eq($m['bausteine'][0]['video_ende_knopf'] ?? null, true, 'Musterbuch: Kopfbereich mit Knopf nach dem Video');
        T::eq($m['bausteine'][0]['video_datei'] ?? null, 'vid/eigen-0a1b2c3d4e5f6071.mp4', 'Musterbuch: mit der Probedatei der anderen Videoseite');
        $aus = json_decode((string) file_get_contents("$w/lp/musterbuch/hero--bild-rechts--video-datei.json"), true);
        T::ok(!isset($aus['bausteine'][0]['video_ende_knopf']), 'die bestehende Videoseite des Musterbuchs bleibt ohne das Feld');
        T::ok(is_file("$w/musterbuch/hero--bild-rechts--video-ende/index.html"), 'gebautes Musterbuch hat die Seite (php ci/musterbuch-bauen.php)');
        T::contains((string) file_get_contents("$w/lp/SKILL.md"), 'video_ende_knopf', 'lp/SKILL.md nennt das Feld (php ci/skill-bauen.php)');
        T::contains((string) file_get_contents("$w/lp/SKILL.md"), 'braucht', 'lp/SKILL.md sagt, was an das Feld gebunden ist');
        T::contains((string) file_get_contents("$w/CHANGELOG.md"), '`lp-video-ende`', 'CHANGELOG nennt die neue Klasse');
    },
];
