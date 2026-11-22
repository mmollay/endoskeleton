<?php
/**
 * Meta-Pixel nur mit Einwilligung (3.73.0).
 *
 *   PX01  Katalog: meta.meta_pixel_id (Kennung, nur Ziffern, 10–20 Stellen, seit 3.73.0), Klassen des Banners seit 3.73.0
 *   PX02  Ohne Pixel: kein Banner, kein Knopf in der Fußzeile, keine Spur von Meta — Seite Byte für Byte wie ohne das Feld
 *   PX03  Mit Pixel: Banner (versteckt bis lp.js, Region mit Namen, Zustimmen und Ablehnen gleich gewichtet, nichts vorausgewählt),
 *         Rückfalltext nennt Meta (du und Sie), Link auf die Datenschutzerklärung, Widerruf-Knopf in der Fußzeile — auf allen drei Seiten;
 *         im HTML keine Adresse von Meta (geladen wird erst in lp.js nach der Zustimmung)
 *   PX04  Text aus dem Profil (einwilligung_pixel, maskiert); das Profil prüft 20–600 Zeichen
 *   PX05  Falsche Pixel-ID sperrt (9 und 21 Ziffern, Buchstaben, Zahl statt Text)
 *   PX06  Prüfer: Pixel ohne Satz zu Meta in der Datenschutzerklärung sperrt; mit Satz kein Befund; ohne Pixel kein Befund
 *   PX07  Checkliste: Seite mit Banner ohne Mangel; lp.js lädt connect.facebook.net nur aus der Zustimmung heraus
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Checkliste;
use Endo\Lp\Katalog;
use Endo\Lp\Profil;
use Endo\Lp\Pruefer;

const PX_ID = '123456789012345';
const PX_HEUTE = '2026-09-27T12:00';

$k = Katalog::laden();
/** Profilkopie; $j ändert profil.json, $ds ersetzt die Datenschutzerklärung. */
$profil = static function (?callable $j = null, ?string $ds = null): string {
    $p = H::profilKopie();
    if ($j) {
        $d = json_decode((string) file_get_contents("$p/profil.json"), true);
        $j($d);
        file_put_contents("$p/profil.json", json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    if ($ds !== null) { file_put_contents("$p/rechtliches/datenschutz.txt", $ds); }
    return $p;
};
/** Baut die Seite und gibt [Befunde, XPath je Datei, HTML index, Ordner] zurück. */
$bau = static function (array $meta = [], ?string $profil = null): array {
    $o = H::ordner(H::seite([], $meta), null, $profil);
    $e = Bauer::bauen($o);
    $x = [];
    foreach (['index', 'impressum', 'datenschutz'] as $n) {
        $d = new DOMDocument();
        libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="UTF-8">' . (string) @file_get_contents("$o/$n.html"));
        libxml_clear_errors();
        $x[$n] = new DOMXPath($d);
    }
    return [$e->befunde, $x, (string) @file_get_contents("$o/index.html"), $o];
};
$mitMeta = "## Messung von Anzeigen\n\nMit deiner Einwilligung nutzen wir das Meta-Pixel von Meta Platforms Ireland Ltd.\n";

return [
    'PX01 Katalog: meta_pixel_id und die Klassen des Banners seit 3.73.0' => static function () use ($k): void {
        $f = $k->seite['felder']['meta_pixel_id'] ?? [];
        T::eq([$f['typ'] ?? null, $f['seit'] ?? null, $f['pflicht'] ?? false], ['kennung', '3.73.0', false], 'meta_pixel_id: Kennung, freiwillig, seit 3.73.0');
        foreach (['1234567890' => true, '12345678901234567890' => true, '123456789' => false, '123456789012345678901' => false, '12345abc901' => false, "1234567890\n" => false] as $w => $soll) {
            T::eq((bool) preg_match('~' . ($f['muster'] ?? '(?!)') . '~', (string) $w), $soll, 'Muster bei ' . json_encode((string) $w));
        }
        foreach (['lp-banner', 'lp-banner-innen', 'lp-banner-titel', 'lp-banner-text', 'lp-banner-knoepfe', 'lp-banner-knopf', 'lp-fuss-banner'] as $klasse) {
            T::eq($k->rahmen['klassen'][$klasse] ?? null, '3.73.0', "Klasse $klasse seit 3.73.0");
        }
    },
    'PX02 Ohne Pixel: kein Banner, keine Spur von Meta' => static function () use ($bau): void {
        [$befunde, $x, $html] = $bau();
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut ohne Befund');
        T::eq($x['index']->query('//*[contains(@class,"lp-banner") or contains(@class,"lp-fuss-banner") or @data-lp-pixel]')->length, 0, 'kein Banner, kein Knopf');
        T::notContains($html, 'facebook', 'keine Adresse von Meta');
        T::notContains($html, 'Meta', 'Meta wird nicht genannt');
    },
    'PX03 Mit Pixel: Banner, gleich gewichtete Knöpfe, Widerruf in der Fußzeile, kein Laden im HTML' => static function () use ($bau, $profil): void {
        foreach (['du' => 'Mit deiner Zustimmung', 'Sie' => 'Mit Ihrer Zustimmung'] as $anrede => $anfang) {
            [$befunde, $xs, $html] = $bau(['meta_pixel_id' => PX_ID], $profil(static function (array &$d) use ($anrede): void { $d['anrede'] = $anrede; }));
            T::eq(H::texte(H::sperrend($befunde)), '', "$anrede: baut ohne Befund");
            T::notContains($html, 'facebook.net', "$anrede: im HTML keine Adresse von Meta");
            foreach ($xs as $seite => $x) {
                $b = $x->query('//body/div[@id="lp-banner"][contains(@class,"lp-banner")][@hidden][@role="region"][@aria-labelledby="lp-banner-titel"][@data-lp-pixel="' . PX_ID . '"]');
                T::eq($b->length, 1, "$anrede/$seite: Banner versteckt, Region mit Namen, Pixel-ID am Banner");
                T::eq($x->query('//body/*[1][self::a][contains(@class,"skip-link")]/following-sibling::*[1][@id="lp-banner"]')->length, 1, "$anrede/$seite: Banner gleich nach dem Sprunglink (zweiter Halt der Tastatur)");
                T::eq(trim((string) $x->query('//*[@id="lp-banner-titel"]')->item(0)?->textContent), 'Cookies für Anzeigen', "$anrede/$seite: Titel");
                $text = (string) $x->query('//p[contains(@class,"lp-banner-text")]')->item(0)?->textContent;
                T::ok(str_starts_with($text, $anfang) && str_contains($text, 'Meta-Pixel') && str_contains($text, 'Meta Platforms Ireland'), "$anrede/$seite: Rückfalltext nennt Meta ($text)");
                T::eq($x->query('//p[contains(@class,"lp-banner-text")]/a[@href="datenschutz.html"]')->length, 1, "$anrede/$seite: Link auf die Datenschutzerklärung");
                $knoepfe = $x->query('//div[contains(@class,"lp-banner-knoepfe")]/button');
                T::eq($knoepfe->length, 2, "$anrede/$seite: zwei Knöpfe");
                $klassen = $wahl = $namen = [];
                foreach ($knoepfe as $kn) {
                    $klassen[] = $kn->getAttribute('class');
                    $wahl[] = $kn->getAttribute('data-lp-wahl') . '|' . $kn->getAttribute('type');
                    $namen[] = trim($kn->textContent);
                    T::ok(!$kn->hasAttribute('autofocus') && !$kn->hasAttribute('aria-pressed'), "$anrede/$seite: nichts vorausgewählt");
                }
                T::eq([$wahl, $namen], [['ja|button', 'nein|button'], ['Zustimmen', 'Ablehnen']], "$anrede/$seite: Zustimmen und Ablehnen");
                T::ok($klassen[0] === $klassen[1] && str_contains($klassen[0], 'lp-banner-knopf'), "$anrede/$seite: beide Knöpfe gleich gewichtet (" . implode(' / ', $klassen) . ')');
                T::eq($x->query('//footer//nav[@aria-label="Rechtliches"]/button[@type="button"][contains(@class,"lp-fuss-banner")][@hidden][@aria-controls="lp-banner"][@aria-expanded="false"]')->length, 1,
                    "$anrede/$seite: Widerruf-Knopf in der Fußzeile (versteckt bis lp.js)");
                T::eq(trim((string) $x->query('//button[contains(@class,"lp-fuss-banner")]')->item(0)?->textContent), 'Cookie-Einstellungen', "$anrede/$seite: Name des Widerruf-Knopfs");
            }
        }
    },
    'PX04 Text aus dem Profil (maskiert), Profil prüft die Länge' => static function () use ($bau, $profil): void {
        $eigen = 'Wir messen mit dem <b>Meta-Pixel</b>, ob unsere Anzeigen wirken — nur mit deiner Zustimmung.';
        [$befunde, $x, $html] = $bau(['meta_pixel_id' => PX_ID], $profil(static function (array &$d) use ($eigen): void { $d['einwilligung_pixel'] = $eigen; }));
        T::eq(H::texte(H::sperrend($befunde)), '', 'baut ohne Befund');
        T::contains($html, 'Wir messen mit dem &lt;b&gt;Meta-Pixel&lt;/b&gt;', 'Text aus dem Profil, maskiert');
        T::notContains($html, 'Mit deiner Zustimmung', 'kein Rückfalltext');
        foreach (['kurz' => 'zu kurz', 'leer' => '   ', 'lang' => str_repeat('x', 601), 'zahl' => 5] as $fall => $wert) {
            $p = Profil::laden($profil(static function (array &$d) use ($wert): void { $d['einwilligung_pixel'] = $wert; }));
            T::ok(str_contains(H::texte(H::sperrend($p->pruefen())), 'einwilligung_pixel'), "Profil: einwilligung_pixel $fall sperrt");
        }
        T::eq(H::texte(Profil::laden($profil())->pruefen()), '', 'Profil ohne einwilligung_pixel ohne Befund');
    },
    'PX05 Falsche Pixel-ID sperrt' => static function () use ($bau): void {
        foreach (['9 Ziffern' => '123456789', '21 Ziffern' => '123456789012345678901', 'Buchstaben' => '1234567890abc', 'Zahl' => 1234567890123, 'Zeilenende' => "1234567890\n"] as $fall => $w) {
            [$befunde] = $bau(['meta_pixel_id' => $w]);
            T::ok(str_contains(H::texte(H::sperrend($befunde)), 'meta.meta_pixel_id'), "$fall: sperrender Befund an meta.meta_pixel_id");
        }
    },
    'PX06 Prüfer: Pixel braucht einen Satz zu Meta in der Datenschutzerklärung' => static function () use ($profil, $mitMeta): void {
        $pruefen = static function (array $meta, string $p): string {
            $o = H::ordner(H::seite([], $meta), null, $p);
            Bauer::bauen($o);
            return H::texte(H::sperrend(Pruefer::pruefen($o, PX_HEUTE)));
        };
        $ds = (string) preg_replace('/## Messung von Anzeigen.*?(?=## )/su', '', (string) file_get_contents(H::testprofil() . '/rechtliches/datenschutz.txt'));
        T::ok(!str_contains($ds, 'Meta'), 'Datenschutzerklärung ohne Absatz zu Meta');
        $ohne = $pruefen(['meta_pixel_id' => PX_ID], $profil(null, $ds));
        T::ok(str_contains($ohne, 'profil.rechtliches.datenschutz') && str_contains($ohne, 'Meta'), "ohne Satz zu Meta sperrt ($ohne)");
        T::eq($pruefen(['meta_pixel_id' => PX_ID], $profil(null, $ds . "\n" . $mitMeta)), '', 'mit Satz zu Meta kein sperrender Befund');
        T::eq($pruefen(['meta_pixel_id' => PX_ID], $profil(null, $ds . "\nWir verwenden kein Metadatenformat.\n")), $ohne, '„Metadaten“ zählt nicht als Meta');
        T::eq($pruefen([], $profil(null, $ds)), '', 'ohne Pixel kein Befund');
    },
    'PX07 Checkliste ohne Mangel; lp.js lädt Meta nur nach der Zustimmung' => static function () use ($profil, $mitMeta): void {
        $ds = (string) file_get_contents(H::testprofil() . '/rechtliches/datenschutz.txt');
        $o = H::ordner(H::seite([], ['meta_pixel_id' => PX_ID]), null, $profil(null, $ds . "\n" . $mitMeta));
        Bauer::bauen($o);
        $c = Checkliste::pruefen($o);
        T::eq(H::texte(array_values(array_filter($c, static fn ($b) => $b->art === 'mangel'))), '', 'Checkliste ohne Mangel');
        $js = (string) file_get_contents(H::wurzel() . '/lp/lp.js');
        T::eq(substr_count($js, 'connect.facebook.net'), 1, 'lp.js nennt connect.facebook.net genau einmal');
        $a = strpos($js, 'var laden = function'); $u = strpos($js, 'connect.facebook.net'); $z = strpos($js, 'var entziehen = function');
        T::ok($a !== false && $z !== false && $a < $u && $u < $z, 'die Adresse steht in der Ladefunktion der Zustimmung');
        T::contains($js, "try { localStorage.setItem(", 'Entscheidung im Web-Speicher nur mit try');
    },
];
