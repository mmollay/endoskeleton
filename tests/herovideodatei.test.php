<?php
/**
 * Eigene Videodatei (3.62.0): Kopfbereich (video_datei) und Baustein „video“ (anbieter „datei“). Das Bild ist das Startbild (poster),
 * ausgeliefert wird ein <video controls preload="none" playsinline> ohne Autoplay — php8.2 tests/run.php herovideodatei
 *
 *   VD01 Katalog: Felder, Regeln (nur_wenn, pflicht_wenn, nicht_mit), Klasse lp-video-datei, Beispiele unverändert
 *   VD02 Pfadmuster: nur vid/eigen-<16 Hex>.mp4 (\z, nie $) — alles andere sperrend, auch wenn die Datei da ist
 *   VD03 Datei: muss da sein, gewöhnlich (kein Link), ftyp an Stelle 4–8, höchstens 40 MB
 *   VD04 Kopfbereich: nur bild-rechts, nur mit Bild; nie zusammen mit video_kennung
 *   VD05 Bau Kopfbereich: <video controls preload="none" playsinline poster>, kein Autoplay, Ersatzlink, keine Fremdquelle
 *   VD06 Baustein video: anbieter datei und youtube (Standard), Regeln gegenseitig
 *   VD07 Am Prüfer vorbei: Vorlage und Kontext lassen nie eine ungültige Datei durch
 *   VD08 Gesamtprüfer: vid/ gehört dem Bauplan, Poster/Link/Autoplay-Prüfung
 *   VD09 Dateiname ist kein Fließtext (Sperrliste); du/Sie im Hinweis
 *   VD10 CSS nur Tokens, kein Inline-Style; Musterbuch und Skill zeigen die Felder; lp.js ohne Video-Abspiellogik
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\BauFehler;
use Endo\Lp\Katalog;
use Endo\Lp\Kontext;
use Endo\Lp\Profil;
use Endo\Lp\Pruefer;
use Endo\Lp\Schema;
use Endo\Lp\Videodatei;

const VD_DATEI = 'vid/eigen-0123456789abcdef.mp4';
/** Kleinste „MP4-Datei“ für die Prüfung der Magic Bytes: 24 Byte, „ftyp“ an Stelle 4–8. */
const VD_KOPF = "\0\0\0\x18ftypmp42\0\0\0\0mp42isom";

$k = Katalog::laden();
/** Ordner mit den Dateien (Pfad => Inhalt) und gibt ihn zurück. */
$ordnerMit = static function (array $dateien): string {
    $o = T::tmp() . '/seite';
    foreach ($dateien as $pfad => $inhalt) {
        @mkdir(dirname("$o/$pfad"), 0700, true);
        file_put_contents("$o/$pfad", $inhalt);
    }
    @mkdir($o, 0700, true);
    return $o;
};
$schema = static fn (array $s, string $ordner): string => H::texte(Schema::pruefen($s, $k, Profil::laden(H::testprofil()), $ordner));
/** Kopfbereich des Katalog-Beispiels mit Änderungen (null löscht ein Feld). */
$seite = static function (array $hero = []): array {
    $s = H::seite();
    $s['bausteine'][0] = array_filter($hero + $s['bausteine'][0], static fn ($w) => $w !== null);
    return $s;
};
$heroDatei = static fn (string $pfad = VD_DATEI) => $seite(['video_datei' => $pfad]);
/** Seite mit dem Baustein video (anbieter datei, Standard: gültig) nach dem Kopfbereich. */
$videoSeite = static function (array $video = []) use ($k): array {
    $b = array_filter($video + ['anbieter' => 'datei', 'datei' => VD_DATEI, 'kennung' => null] + $k->baustein('video')['beispiel'], static fn ($w) => $w !== null);
    return H::seite([$b]);
};
$mitDatei = static function (array $plan, string $pfad = VD_DATEI) use ($ordnerMit): string {
    $o = H::ordner($plan);
    @mkdir("$o/vid", 0700, true);
    file_put_contents("$o/$pfad", VD_KOPF . str_repeat('x', 100));
    return $o;
};
$bau = static function (string $ordner): array {
    T::eq(H::texte(Bauer::bauen($ordner)->befunde), '', 'Bau ohne Befunde');
    $html = (string) @file_get_contents("$ordner/index.html");
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . ($html ?: '<p></p>'), LIBXML_NONET);
    libxml_clear_errors();
    return [$html, new DOMXPath($d)];
};
$css = static fn (): string => (string) file_get_contents(H::wurzel() . '/lp/lp.css');

return [
    'VD01 Katalog: video_datei im Kopfbereich, anbieter/kennung/datei im Baustein video, Klasse lp-video-datei, Beispiele unverändert' => static function () use ($k): void {
        $hero = $k->baustein('hero');
        $f = $hero['felder']['video_datei'] ?? null;
        T::ok(is_array($f), 'Feld video_datei im Kopfbereich');
        T::eq($f['typ'] ?? null, 'videodatei', 'Typ videodatei');
        T::eq($f['nur_bei'] ?? null, ['bild-rechts'], 'nur bei bild-rechts');
        T::eq($f['nicht_mit'] ?? null, ['video_kennung'], 'schließt video_kennung aus');
        T::eq($f['seit'] ?? null, '3.62.0', 'seit 3.62.0');
        T::ok(!($f['pflicht'] ?? false) && !isset($f['pflicht_bei']), 'freiwillig');
        T::ok(!isset($hero['beispiel']['video_datei']), 'das Katalog-Beispiel bleibt ohne Video');
        T::eq($hero['geaendert'], '3.62.0', 'Kopfbereich geändert 3.62.0');
        $video = $k->baustein('video');
        T::eq($video['geaendert'], '3.62.0', 'Baustein video geändert 3.62.0');
        T::eq($video['felder']['anbieter']['werte'] ?? null, ['youtube', 'datei'], 'Anbieter youtube und datei');
        T::eq($video['felder']['anbieter']['standard'] ?? null, 'youtube', 'Standard youtube');
        T::ok(!($video['felder']['anbieter']['pflicht'] ?? false), 'Anbieter freiwillig (Standard youtube)');
        T::eq($video['felder']['kennung']['pflicht_wenn'] ?? null, ['anbieter' => 'youtube'], 'Kennung Pflicht bei youtube');
        T::eq($video['felder']['kennung']['nur_wenn'] ?? null, ['anbieter' => 'youtube'], 'Kennung nur bei youtube');
        T::eq($video['felder']['datei']['typ'] ?? null, 'videodatei', 'Feld datei hat den Typ videodatei');
        T::eq($video['felder']['datei']['pflicht_wenn'] ?? null, ['anbieter' => 'datei'], 'Datei Pflicht bei datei');
        T::eq($video['felder']['datei']['nur_wenn'] ?? null, ['anbieter' => 'datei'], 'Datei nur bei datei');
        T::eq($video['felder']['datei']['seit'] ?? null, '3.62.0', 'Datei seit 3.62.0');
        T::ok($video['felder']['vorschau']['pflicht'] ?? false, 'Vorschaubild (= Startbild) bleibt Pflicht');
        T::eq($video['klassen']['lp-video-datei'] ?? null, '3.62.0', 'Klasse lp-video-datei seit 3.62.0');
        T::eq($video['beispiel']['anbieter'] ?? null, 'youtube', 'Beispiel des Bausteins bleibt YouTube');
        T::ok(!isset($video['beispiel']['datei']), 'Beispiel ohne Datei');
    },
    'VD02 Pfadmuster ^vid/eigen-[0-9a-f]{16}\.mp4\z: nur das ist gültig, alles andere sperrend — auch wenn die Datei da ist' => static function () use ($schema, $heroDatei, $ordnerMit): void {
        $o = $ordnerMit([VD_DATEI => VD_KOPF]);
        T::eq($schema($heroDatei(), $o), '', 'gültig');
        $schlecht = [
            'Zeilenumbruch am Ende' => VD_DATEI . "\n", 'Großbuchstaben im Namen' => 'vid/eigen-0123456789ABCDEF.mp4', '15 Hexzeichen' => 'vid/eigen-0123456789abcde.mp4',
            '17 Hexzeichen' => 'vid/eigen-0123456789abcdef0.mp4', 'Endung groß' => 'vid/eigen-0123456789abcdef.MP4', 'zweite Endung' => VD_DATEI . '.exe',
            'ohne eigen-' => 'vid/0123456789abcdef.mp4', 'Unterordner' => 'vid/x/eigen-0123456789abcdef.mp4', 'falscher Ordner' => 'img/eigen-0123456789abcdef.mp4',
            'Hochschritt' => '../vid/eigen-0123456789abcdef.mp4', 'Hochschritt mitten' => 'vid/../vid/eigen-0123456789abcdef.mp4', 'absolut' => '/vid/eigen-0123456789abcdef.mp4',
            'absolut ins System' => '/etc/passwd', 'Adresse http' => 'http://beispiel.invalid/' . VD_DATEI, 'Adresse https' => 'https://beispiel.invalid/' . VD_DATEI,
            'Schema file' => 'file:///' . VD_DATEI, 'Abfrage' => VD_DATEI . '?x=1', 'Anker' => VD_DATEI . '#t=1', 'Nullbyte' => "vid/eigen-0123456789abcdef.mp4\0.png",
            'Leerzeichen' => ' ' . VD_DATEI, 'leer' => '', 'Backslash' => 'vid\\eigen-0123456789abcdef.mp4', 'Prozent' => 'vid/eigen-0123456789abcdef%2emp4',
        ];
        // auch die Dateien mit den falschen Namen liegen da (wo ein Name als Dateiname möglich ist)
        foreach (['vid/eigen-0123456789ABCDEF.mp4', 'vid/eigen-0123456789abcde.mp4', 'vid/eigen-0123456789abcdef0.mp4', "vid/eigen-0123456789abcdef.mp4\n", 'vid/0123456789abcdef.mp4'] as $p) {
            file_put_contents("$o/$p", VD_KOPF);
        }
        foreach ($schlecht as $was => $pfad) {
            T::contains($schema($heroDatei($pfad), $o), '[sperrend] bausteine.0.video_datei', "ungültig ($was) ist sperrend am Feld");
        }
        foreach ([12345, true, [VD_DATEI], null, ['datei' => VD_DATEI]] as $i => $x) {
            $s = $heroDatei();
            $s['bausteine'][0]['video_datei'] = $x;
            T::contains($schema($s, $o), '[sperrend] bausteine.0.video_datei', "kein Text (Fall $i) ist sperrend");
        }
        T::contains($schema($heroDatei('vid/eigen-0123456789ABCDEF.mp4'), $o), 'Videodatei', 'Kundentext nennt das Feld');
    },
    'VD03 Datei: da, gewöhnlich, ftyp an Stelle 4–8, nicht über 40 MB' => static function () use ($schema, $heroDatei, $ordnerMit): void {
        T::eq(Videodatei::MAX_BYTES, 40 * 1024 * 1024, 'Grenze 40 MB als Konstante');
        $s = $heroDatei();
        T::contains($schema($s, $ordnerMit(['seite.json' => ''])), 'nicht gefunden', 'fehlt: nicht gefunden');
        $o = $ordnerMit([]);
        mkdir("$o/vid/eigen-0123456789abcdef.mp4", 0700, true);
        T::contains($schema($s, $o), '[sperrend] bausteine.0.video_datei', 'ein Ordner statt einer Datei');
        // kein ftyp: Text, PNG, leer, zu kurz, ftyp an der falschen Stelle
        $falsch = ['Text mit .mp4-Endung' => "Das ist kein Video, nur Text.\n", 'PNG' => "\x89PNG\r\n\x1a\n\0\0\0\rIHDR", 'leer' => '', 'zu kurz' => "\0\0\0\x18ftyp",
            'ftyp an Stelle 0' => "ftyp\0\0\0\x18mp42isom0000", 'ftyp an Stelle 8' => "\0\0\0\x18\0\0\0\0ftypmp42", 'FTYP groß' => "\0\0\0\x18FTYPmp42\0\0\0\0mp42isom"];
        foreach ($falsch as $was => $inhalt) {
            $t = $schema($s, $ordnerMit([VD_DATEI => $inhalt]));
            T::contains($t, '[sperrend] bausteine.0.video_datei', "$was: sperrend");
            T::contains($t, 'MP4', "$was: Kundentext sagt, dass es kein MP4-Video ist");
        }
        T::eq($schema($s, $ordnerMit([VD_DATEI => VD_KOPF])), '', 'ftyp an Stelle 4–8: gültig');
        // Größe: genau 40 MB gültig, ein Byte mehr nicht (dünn besetzte Datei, kostet nichts)
        foreach ([Videodatei::MAX_BYTES => '', Videodatei::MAX_BYTES + 1 => 'zu groß'] as $groesse => $erwartet) {
            $o = $ordnerMit([VD_DATEI => VD_KOPF]);
            $h = fopen("$o/" . VD_DATEI, 'r+b');
            ftruncate($h, $groesse);
            fclose($h);
            $t = $schema($s, $o);
            if ($erwartet === '') {
                T::eq($t, '', "genau $groesse Byte: gültig");
            } else {
                T::contains($t, '[sperrend] bausteine.0.video_datei', "$groesse Byte: sperrend");
                T::contains($t, '40 MB', 'Kundentext nennt die Grenze');
            }
        }
        // Verknüpfungen: weder die Datei noch der Ordner vid/ darf ein Link sein
        $echt = $ordnerMit([VD_DATEI => VD_KOPF, 'fremd/echt.mp4' => VD_KOPF]);
        unlink("$echt/" . VD_DATEI);
        symlink("$echt/fremd/echt.mp4", "$echt/" . VD_DATEI);
        T::contains($schema($s, $echt), '[sperrend] bausteine.0.video_datei', 'Datei ist ein Link: sperrend');
        $o2 = $ordnerMit(['fremd/' . basename(VD_DATEI) => VD_KOPF]);
        symlink("$o2/fremd", "$o2/vid");
        T::ok(is_file("$o2/" . VD_DATEI), 'Testaufbau: über den Link erreichbar');
        T::contains($schema($s, $o2), '[sperrend] bausteine.0.video_datei', 'Ordner vid ist ein Link: sperrend');
    },
    'VD04 Kopfbereich: nur bild-rechts, nur mit Bild, nie zusammen mit video_kennung' => static function () use ($schema, $heroDatei, $seite, $ordnerMit): void {
        $o = $ordnerMit([VD_DATEI => VD_KOPF]);
        foreach (['bild-hinten', 'schlicht'] as $v) {
            $t = $schema($seite(['variante' => $v, 'video_datei' => VD_DATEI]), $o);
            T::contains($t, '[sperrend] bausteine.0.video_datei', "$v: Feld abgelehnt");
            T::contains($t, 'bild-rechts', "$v: Kundentext nennt die erlaubte Variante");
            T::contains($t, 'Abschnitt 1', "$v: Kundentext nennt den Abschnitt");
        }
        T::contains($schema($seite(['bild' => null, 'video_datei' => VD_DATEI]), $o), 'bausteine.0.bild', 'ohne Bild fehlt das Bild (Pflicht), also auch das Startbild');
        $t = $schema($seite(['video_datei' => VD_DATEI, 'video_kennung' => 'AbCdEfGhIjk']), $o);
        T::contains($t, '[sperrend] bausteine.0.video_datei', 'beide Felder: sperrend am Dateifeld');
        T::contains($t, 'entweder', 'Kundentext: entweder das eine oder das andere');
        T::contains($t, 'Abschnitt 1', 'Kundentext nennt den Abschnitt');
        T::eq($schema($seite(['video_kennung' => 'AbCdEfGhIjk']), $o), '', 'YouTube allein: unverändert gültig');
        T::eq($schema($seite(), $o), '', 'ohne Video: unverändert gültig');
        T::eq($schema($heroDatei(), $o), '', 'Datei allein: gültig');
    },
    'VD05 Bau Kopfbereich: video controls preload=none playsinline poster, ohne Autoplay, mit Ersatzlink, ohne Fremdquelle' => static function () use ($heroDatei, $mitDatei, $bau): void {
        [$h, $x] = $bau($mitDatei($heroDatei()));
        $v = $x->query('//section[contains(@class,"lp-hero--bild-rechts")]//div[contains(@class,"lp-hero-medien")]//div[contains(@class,"lp-video-rahmen")]/video');
        T::eq($v->length, 1, 'ein <video> im Rahmen im Medien-Block des Kopfbereichs');
        $e = $v->item(0);
        T::ok($e?->hasAttribute('controls') === true, 'controls (Bedienleiste)');
        T::eq($e?->getAttribute('preload'), 'none', 'preload=none: nichts laden, bis es gestartet wird');
        T::ok($e?->hasAttribute('playsinline') === true, 'playsinline');
        T::eq($e?->getAttribute('poster'), 'es/bilder/beispiel-vortrag.webp', 'das Kopfbild ist das Startbild (lokal)');
        T::eq($e?->getAttribute('aria-label'), 'Video: Gesund durch den Winter', 'beschriftet mit dem Titel');
        T::eq($e?->getAttribute('class'), 'lp-video-datei', 'Klasse lp-video-datei');
        foreach (['autoplay', 'muted', 'loop', 'src', 'style'] as $a) { T::ok($e?->hasAttribute($a) === false, "kein Attribut $a am <video>"); }
        $q = $x->query('//video/source');
        T::eq($q->length, 1, 'eine Quelle');
        T::eq($q->item(0)?->getAttribute('src'), VD_DATEI, 'Quelle ist die Datei im Seitenordner');
        T::eq($q->item(0)?->getAttribute('type'), 'video/mp4', 'Typ video/mp4');
        $l = $x->query('//div[contains(@class,"lp-hero-medien")]/p[contains(@class,"lp-video-link")]/a[@href="' . VD_DATEI . '"]');
        T::eq($l->length, 1, 'Ersatzlink zur Datei unter dem Rahmen');
        T::contains(trim((string) $x->query('//p[contains(@class,"lp-video-link")]')->item(0)?->textContent), 'Lässt sich das Video nicht abspielen? Du kannst es auch als Datei öffnen.', 'Hinweis');
        T::eq($x->query('//img[contains(@class,"lp-hero-bild")]')->length, 0, 'kein zweites, loses Kopfbild');
        T::eq($x->query('//button[contains(@class,"lp-video-start")]')->length, 0, 'kein Start-Knopf (der ist für YouTube)');
        T::notContains($h, '<iframe', 'kein iframe');
        T::notContains($h, 'youtube', 'nichts von YouTube');
        T::notContains($h, ' style=', 'kein Inline-Style');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//h1')->length, 1, 'genau eine H1');
        T::eq($x->query('//section[contains(@class,"lp-hero")]//a[contains(@class,"lp-knopf")]')->length, 1, 'der Knopf des Kopfbereichs bleibt');
        foreach ($x->query('//*[@src]|//*[@poster]|//a[@href]') as $n) {
            foreach (['src', 'poster', 'href'] as $a) {
                T::ok(!preg_match('#^([a-z]+:)?//#i', $n->getAttribute($a)), "keine Fremdquelle in $a: " . $n->getAttribute($a));
            }
        }
        T::eq(substr_count($h, VD_DATEI), 2, 'der Pfad steht genau zweimal im HTML: Quelle und Ersatzlink');
    },
    'VD06 Baustein video: anbieter datei zeigt das Video, youtube (auch als Standard) bleibt wie bisher, die Felder schließen sich aus' => static function () use ($schema, $videoSeite, $mitDatei, $bau, $ordnerMit, $k): void {
        $o = $mitDatei($videoSeite());
        [$h, $x] = $bau($o);
        $v = $x->query('//section[contains(@class,"lp-video--breit")]//div[contains(@class,"lp-video-rahmen")]/video');
        T::eq($v->length, 1, 'ein <video> im Rahmen des Bausteins');
        T::eq($v->item(0)?->getAttribute('poster'), 'es/bilder/beispiel-video.webp', 'Vorschaubild = Startbild');
        T::eq($v->item(0)?->getAttribute('aria-label'), 'Video: Kurz erklärt: Worum geht es?', 'beschriftet mit dem Titel');
        T::eq($x->query('//section[contains(@class,"lp-video")]//h2[contains(@class,"lp-titel")]')->length, 1, 'Titel als Überschrift');
        T::eq($x->query('//section[contains(@class,"lp-video")]//a[@href="' . VD_DATEI . '"]')->length, 1, 'Ersatzlink');
        T::eq($x->query('//button[contains(@class,"lp-video-start")]')->length, 0, 'kein Start-Knopf');
        T::notContains($h, 'youtube', 'nichts von YouTube');
        // mit-text: der Text steht daneben
        $o2 = $mitDatei($videoSeite(['variante' => 'mit-text', 'text' => 'Kurzer Text zum Video.']));
        [, $x2] = $bau($o2);
        T::eq($x2->query('//section[contains(@class,"lp-video--mit-text")]//div[contains(@class,"lp-video-text")]')->length, 1, 'mit-text: Text vorhanden');
        T::eq($x2->query('//section[contains(@class,"lp-video--mit-text")]//video')->length, 1, 'mit-text: Video vorhanden');
        // youtube ausdrücklich und als Standard
        $yt = H::seite([$k->baustein('video')['beispiel']]);
        $ohneAnbieter = H::seite([array_diff_key($k->baustein('video')['beispiel'], ['anbieter' => 1])]);
        [$hy, $xy] = $bau(H::ordner($yt));
        T::eq($xy->query('//section[contains(@class,"lp-video")]//button[contains(@class,"lp-video-start")]')->length, 1, 'youtube: Start-Knopf wie bisher');
        T::eq($xy->query('//video')->length, 0, 'youtube: kein <video>');
        T::notContains($hy, 'vid/', 'youtube: keine Datei');
        [$hs] = $bau(H::ordner($ohneAnbieter));
        T::eq($hs, $hy, 'ohne Angabe „anbieter“ ist es youtube: dasselbe HTML');
        // Regeln
        $leer = $ordnerMit([VD_DATEI => VD_KOPF]);
        T::eq($schema($videoSeite(), $leer), '', 'datei mit Datei: gültig');
        T::eq($schema($yt, $leer), '', 'youtube mit Kennung: gültig');
        $t = $schema($videoSeite(['kennung' => 'AbCdEfGhIjk']), $leer);
        T::contains($t, '[sperrend] bausteine.1.kennung', 'datei + kennung: Kennung sperrend');
        T::contains($t, 'Abschnitt 2', 'Kundentext nennt den Abschnitt');
        $t = $schema(H::seite([$k->baustein('video')['beispiel'] + ['datei' => VD_DATEI]]), $leer);
        T::contains($t, '[sperrend] bausteine.1.datei', 'youtube + datei: Datei sperrend');
        $t = $schema(H::seite([['anbieter' => 'youtube', 'datei' => VD_DATEI] + $k->baustein('video')['beispiel']]), $leer);
        T::contains($t, '[sperrend] bausteine.1.datei', 'youtube ausdrücklich + datei: Datei sperrend');
        T::contains($schema($videoSeite(['datei' => null]), $leer), '[sperrend] bausteine.1.datei', 'datei ohne Datei: Pflichtfeld fehlt');
        T::contains($schema(H::seite([array_diff_key($k->baustein('video')['beispiel'], ['kennung' => 1])]), $leer), '[sperrend] bausteine.1.kennung', 'youtube ohne Kennung: Pflichtfeld fehlt (wie bisher)');
        T::contains($schema($videoSeite(['vorschau' => null]), $leer), '[sperrend] bausteine.1.vorschau', 'datei ohne Vorschaubild: Pflichtfeld fehlt (das Startbild)');
        T::contains($schema($videoSeite(['titel' => null]), $leer), '[sperrend] bausteine.1.titel', 'datei ohne Titel: Pflichtfeld fehlt (Textalternative)');
        T::contains($schema($videoSeite(['anbieter' => 'vimeo']), $leer), '[sperrend] bausteine.1.anbieter', 'unbekannter Anbieter: sperrend');
        T::contains($schema($videoSeite(['datei' => 'vid/eigen-0123456789ABCDEF.mp4']), $leer), '[sperrend] bausteine.1.datei', 'falscher Pfad im Baustein: sperrend');
        T::contains($schema($videoSeite(['datei' => '../' . VD_DATEI]), $leer), '[sperrend] bausteine.1.datei', 'Hochschritt im Baustein: sperrend');
        T::contains($schema($videoSeite(['datei' => VD_DATEI . "\n"]), $leer), '[sperrend] bausteine.1.datei', 'Zeilenumbruch im Baustein: sperrend');
        T::contains($schema($videoSeite(), $ordnerMit([VD_DATEI => "Text\n"])), '[sperrend] bausteine.1.datei', 'ohne ftyp im Baustein: sperrend');
    },
    'VD07 Am Prüfer vorbei: Kontext und Vorlagen lassen nie eine ungültige Datei oder eine falsche Darstellung durch' => static function () use ($seite, $ordnerMit): void {
        $plan = $seite();
        $o = H::ordner($plan);
        $kontext = new Kontext(Profil::laden("$o/profil"), $plan['meta'], $o);
        $poster = ['datei' => 'bestand/beispiel-vortrag.webp'];
        // gültige Datei: geht
        @mkdir("$o/vid", 0700, true);
        file_put_contents("$o/" . VD_DATEI, VD_KOPF);
        T::contains($kontext->videoDatei(VD_DATEI, 'Titel', $poster), '<video ', 'gültige Datei: ein <video>');
        $schlecht = ['Hochschritt' => '../' . VD_DATEI, 'absolut' => '/etc/passwd', 'Adresse' => 'https://beispiel.invalid/a.mp4', 'Großbuchstaben' => 'vid/eigen-0123456789ABCDEF.mp4',
            'Zeilenumbruch' => VD_DATEI . "\n", 'fehlt' => 'vid/eigen-fedcba9876543210.mp4', 'Endung' => 'vid/eigen-0123456789abcdef.webm'];
        foreach ($schlecht as $was => $pfad) {
            try {
                $kontext->videoDatei($pfad, 'Titel', $poster);
                T::ok(false, "$was: hätte eine Ausnahme werfen müssen");
            } catch (BauFehler $e) {
                T::ok(true, "$was: BauFehler");
            }
        }
        file_put_contents("$o/vid/eigen-aaaaaaaaaaaaaaaa.mp4", "kein Video\n");
        try {
            $kontext->videoDatei('vid/eigen-aaaaaaaaaaaaaaaa.mp4', 'Titel', $poster);
            T::ok(false, 'ohne ftyp: hätte eine Ausnahme werfen müssen');
        } catch (BauFehler $e) {
            T::ok(true, 'ohne ftyp: BauFehler');
        }
        // Vorlage hero.php allein: nur bild-rechts zeigt das Video
        $zeige = static function (string $variante, array $mehr = []) use ($plan, $kontext): string {
            $vars = ['b' => ['variante' => $variante, 'video_datei' => VD_DATEI] + $mehr + $plan['bausteine'][0], 'k' => $kontext, 'nr' => 1];
            ob_start();
            (static function (array $__vars): void { extract($__vars); require H::wurzel() . '/lp/bausteine/hero.php'; })($vars);
            return (string) ob_get_clean();
        };
        T::contains($zeige('bild-rechts'), '<video ', 'bild-rechts: das Video ist da (Gegenstück)');
        foreach (['bild-hinten', 'schlicht'] as $v) {
            $h = $zeige($v);
            T::notContains($h, '<video', "$v: kein Video");
            T::notContains($h, 'lp-hero-medien', "$v: kein Medien-Block");
            T::notContains($h, VD_DATEI, "$v: die Datei kommt nicht vor");
        }
        T::contains($zeige('bild-hinten'), 'lp-hero-bild', 'bild-hinten: das Bild bleibt ein Hintergrundbild');
    },
    'VD08 Gesamtprüfer: vid/ gehört dem Bauplan; Poster, Link und Bedienleiste werden geprüft; kein Autoplay' => static function () use ($heroDatei, $mitDatei, $bau): void {
        $heute = '2026-10-29T12:00';
        $sperrend = static fn (string $o): string => H::texte(H::sperrend(Pruefer::pruefen($o, $heute)));
        $o = $mitDatei($heroDatei());
        $bau($o);
        T::eq($sperrend($o), '', 'Seite mit Video: keine sperrenden Befunde (Link, Quelle und Poster erlaubt)');
        // eine Datei, die der Bauplan nicht nennt
        file_put_contents("$o/vid/eigen-fedcba9876543210.mp4", VD_KOPF);
        T::contains($sperrend($o), 'vid/eigen-fedcba9876543210.mp4 wird im Bauplan nicht verwendet', 'unbenutzte Videodatei sperrend');
        unlink("$o/vid/eigen-fedcba9876543210.mp4");
        file_put_contents("$o/vid/notizen.txt", 'x');
        T::contains($sperrend($o), 'vid/notizen.txt', 'fremde Datei in vid/ sperrend');
        unlink("$o/vid/notizen.txt");
        // ohne den Bauplan-Verweis liegt vid/ im Seitenordner einfach da
        $o2 = H::ordner(H::seite());
        mkdir("$o2/vid");
        file_put_contents("$o2/" . VD_DATEI, VD_KOPF);
        Bauer::bauen($o2);
        T::contains($sperrend($o2), 'wird im Bauplan nicht verwendet', 'Video ohne Verweis im Bauplan sperrend');
        // von Hand verändertes HTML: Autoplay, Schleife, stumm, keine Bedienleiste
        foreach (['autoplay' => ' autoplay', 'loop' => ' loop', 'muted' => ' muted'] as $was => $zusatz) {
            $h = file_get_contents("$o/index.html");
            file_put_contents("$o/index.html", str_replace('<video ', '<video' . $zusatz . ' ', $h));
            $t = $sperrend($o);
            T::contains($t, 'Autoplay', "$was im <video>: sperrend gemeldet");
            file_put_contents("$o/index.html", $h);
        }
        $h = file_get_contents("$o/index.html");
        file_put_contents("$o/index.html", str_replace(' controls', '', $h));
        T::contains($sperrend($o), 'Bedienleiste', '<video> ohne controls: sperrend');
        file_put_contents("$o/index.html", $h);
        // Poster aus fremder Quelle
        file_put_contents("$o/index.html", str_replace('poster="es/', 'poster="https://beispiel.invalid/', $h));
        T::contains($sperrend($o), 'fremde Quelle', 'Poster von einem fremden Server: sperrend');
        file_put_contents("$o/index.html", $h);
        // Poster: nur WebP bis 400 KB (der Kopfbereich hat sonst kein <img>, das geprüft würde)
        $webp = (string) file_get_contents("$o/es/bilder/beispiel-vortrag.webp");
        file_put_contents("$o/es/bilder/poster.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        file_put_contents("$o/index.html", str_replace('poster="es/bilder/beispiel-vortrag.webp"', 'poster="es/bilder/poster.png"', $h));
        T::contains($sperrend($o), 'poster.png ist kein WebP', 'Poster als PNG: sperrend');
        file_put_contents("$o/es/bilder/poster-gross.webp", $webp . str_repeat("\0", 410000));
        file_put_contents("$o/index.html", str_replace('poster="es/bilder/beispiel-vortrag.webp"', 'poster="es/bilder/poster-gross.webp"', $h));
        T::contains($sperrend($o), 'poster-gross.webp ist', 'Poster über 400 KB: sperrend');
        T::contains($sperrend($o), 'KB groß', 'Poster über 400 KB: Größe genannt');
        file_put_contents("$o/index.html", $h);
        // Ersatzlink: nur auf eine Videodatei, die es gibt und die gültig ist — sonst führt er ins Leere
        $link = 'href="' . VD_DATEI . '"';
        file_put_contents("$o/vid/notiz.mp4", VD_KOPF);
        foreach (['vid/eigen-fedcba9876543210.mp4' => 'gültiger Name, Datei fehlt', 'vid/notiz.mp4' => 'Datei da, Name ungültig'] as $ziel => $was) {
            file_put_contents("$o/index.html", str_replace($link, 'href="' . $ziel . '"', $h));
            T::contains($sperrend($o), "interner Link $ziel führt ins Leere", "Link auf $ziel ($was): sperrend");
        }
        file_put_contents("$o/vid/eigen-fedcba9876543210.mp4", 'kein MP4');
        file_put_contents("$o/index.html", str_replace($link, 'href="vid/eigen-fedcba9876543210.mp4"', $h));
        T::contains($sperrend($o), 'interner Link vid/eigen-fedcba9876543210.mp4 führt ins Leere', 'Link auf eine Datei ohne MP4-Kopf: sperrend');
        unlink("$o/vid/eigen-fedcba9876543210.mp4");
        unlink("$o/vid/notiz.mp4");
        unlink("$o/es/bilder/poster.png");
        unlink("$o/es/bilder/poster-gross.webp");
        file_put_contents("$o/index.html", $h);
        T::eq($sperrend($o), '', 'wieder gebaut: sauber');
    },
    'VD09 Der Dateiname ist kein Fließtext (Sperrliste); du und Sie im Hinweis' => static function () use ($heroDatei, $mitDatei, $bau): void {
        $o = $mitDatei($heroDatei());
        $p = H::profilMitListen(['eigen', 'vid', 'mp4'], ['Wirkung']);
        $o2 = H::ordner($heroDatei(), null, $p);
        mkdir("$o2/vid");
        file_put_contents("$o2/" . VD_DATEI, VD_KOPF);
        T::eq(H::texte(H::sperrend(Bauer::bauen($o2)->befunde)), '', 'Bau ohne sperrende Befunde');
        $liste = array_filter(Pruefer::pruefen($o2, '2026-10-29T12:00'), static fn ($b) => str_starts_with($b->technik, 'Sperrliste: ') || str_starts_with($b->technik, 'Grenzwort: '));
        T::eq(H::texte(array_values($liste)), '', 'der Dateiname wird nicht auf Sperrwörter geprüft');
        [, $x] = $bau($o);
        T::contains((string) $x->query('//p[contains(@class,"lp-video-link")]')->item(0)?->textContent, 'Du kannst es auch', 'du');
        $q = H::profilKopie();
        $j = json_decode((string) file_get_contents("$q/profil.json"), true);
        $j['anrede'] = 'Sie';
        file_put_contents("$q/profil.json", json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $o3 = H::ordner($heroDatei(), null, $q);
        mkdir("$o3/vid");
        file_put_contents("$o3/" . VD_DATEI, VD_KOPF);
        [, $x3] = $bau($o3);
        T::contains((string) $x3->query('//p[contains(@class,"lp-video-link")]')->item(0)?->textContent, 'Sie können es auch als Datei öffnen', 'Sie');
    },
    'VD10 CSS nur mit Tokens; Musterbuch und Skill zeigen die Felder; lp.js hat keine Abspiellogik für Dateien' => static function () use ($css): void {
        $c = $css();
        T::ok(preg_match('/\.lp-video-datei\s*\{[^}]*width:\s*100%;[^}]*height:\s*100%;[^}]*\}/', $c) === 1, '.lp-video-datei füllt den 16:9-Rahmen');
        T::ok(preg_match('/\.lp-video-datei\s*\{[^}]*object-fit:\s*contain;/', $c) === 1, 'das Video wird nie abgeschnitten (contain)');
        T::ok(preg_match('/\.lp-video-datei:focus-visible\s*\{[^}]*var\(--focus\)/', $c) === 1, 'sichtbarer Fokus mit dem Fokus-Token');
        preg_match_all('/\.lp-video-datei[^{]*\{[^}]*\}/', $c, $m);
        T::ok(count($m[0]) >= 2, 'Regeln gefunden');
        foreach ($m[0] as $regel) {
            T::ok(!preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|\b\d+px\b/', $regel), 'nur Tokens, keine festen Farben oder px: ' . substr($regel, 0, 40));
        }
        $w = H::wurzel();
        $hero = json_decode((string) file_get_contents("$w/lp/musterbuch/hero--bild-rechts--video-datei.json"), true);
        T::eq($hero['bausteine'][0]['video_datei'] ?? null, 'vid/eigen-0a1b2c3d4e5f6071.mp4', 'Musterbuch: Kopfbereich mit Videodatei');
        T::ok(!isset($hero['bausteine'][0]['video_kennung']), 'Musterbuch: nicht beides');
        $video = json_decode((string) file_get_contents("$w/lp/musterbuch/video--breit--datei.json"), true);
        T::eq($video['bausteine'][1]['anbieter'] ?? null, 'datei', 'Musterbuch: Baustein video mit anbieter datei');
        T::eq($video['bausteine'][1]['datei'] ?? null, 'vid/eigen-0a1b2c3d4e5f6071.mp4', 'Musterbuch: Videodatei');
        $probe = "$w/lp/musterbuch/vid/eigen-0a1b2c3d4e5f6071.mp4";
        T::ok(is_file($probe) && filesize($probe) <= 20 * 1000, 'Probedatei da und höchstens 20 KB');
        T::eq(substr((string) @file_get_contents($probe, false, null, 0, 12), 4, 4), 'ftyp', 'Probedatei ist ein MP4 (ftyp)');
        $skill = (string) file_get_contents("$w/lp/SKILL.md");
        T::contains($skill, '`video_datei`', 'Skill nennt video_datei');
        T::contains($skill, 'vid/eigen-', 'Skill nennt den Pfad');
        T::contains($skill, 'seit 3.62.0', 'Skill nennt die Version');
        T::contains($skill, 'nur wenn', 'Skill nennt die Bedingung am Anbieter');
        $js = (string) file_get_contents("$w/lp/lp.js");
        T::notContains($js, '.play(', 'lp.js spielt nichts selbst ab');
        T::notContains($js, 'lp-video-datei', 'lp.js fasst das eigene Video nicht an');
        T::eq(substr_count($js, "'.lp-video-start'"), 1, 'lp.js: weiter genau ein Handler für den YouTube-Start');
        T::notContains($js, '<video', 'lp.js baut kein <video>');
        $php = (string) file_get_contents("$w/lp/bausteine/hero.php") . (string) file_get_contents("$w/lp/bausteine/video.php");
        T::notContains($php, '<video', 'die Vorlagen schreiben das <video> nicht selbst, sondern über eine gemeinsame Hilfe');
    },
];
