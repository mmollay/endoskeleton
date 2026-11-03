<?php
/**
 * Adressen im Fließtext der Seite (Absätze und Listenpunkte) werden Links, wenn der Host in link_hosts des Profils steht —
 * in Überschriften, Knöpfen, Beschriftungen, Meta-Angaben und JSON-LD nie (lp/src/Kontext.php, lp/src/Text.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Bauer;
use Endo\Lp\Katalog;
use Endo\Lp\Kontext;
use Endo\Lp\Profil;

/** Baut eine Seite, deren Texte überall Adressen enthalten, und gibt [Dokument, HTML, Befunde] zurück. */
$bauen = static function (): array {
    $k = Katalog::laden();
    $hero = ['titel' => 'Alles auf www.example.com', 'dachzeile' => 'www.example.com', 'text' => "Mehr auf www.example.com.\nOder schreib an office@beispiel.invalid.\n\nFremd: www.fremd.test und office@fremd.test",
        'knopf' => ['text' => 'Zu www.example.com', 'ziel' => '#anmeldung']] + $k->baustein('hero')['beispiel'];
    $themen = ['titel' => 'Alles auf www.example.com', 'text' => 'Unter https://www.example.com/themen.',
        'punkte' => ['Alles unter **www.example.com**', 'Fremd: www.fremd.test', 'Mail: office@beispiel.invalid'], 'typ' => 'themen', 'variante' => 'haken'];
    $faq = ['typ' => 'faq', 'titel' => 'Fragen zu www.example.com', 'fragen' => [['frage' => 'Wo steht es: www.example.com?', 'antwort' => 'Auf www.example.com/faq.']]];
    $s = H::seite([$themen, $faq], ['titel' => 'Seite auf www.example.com', 'beschreibung' => 'Siehe www.example.com und office@beispiel.invalid.']);
    $s['bausteine'][0] = $hero;
    $o = H::ordner($s);
    $erg = Bauer::bauen($o);
    $html = (string) @file_get_contents("$o/index.html");
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
    return [new DOMXPath($dom), $html, $erg->befunde];
};

return [
    'Seite: Adressen im Fließtext sind Links auf die eigenen Hosts, der Bau bleibt ohne Befunde' => static function () use ($bauen): void {
        [$x, $html, $befunde] = $bauen();
        T::eq(H::texte($befunde), '', 'Bau und Prüfer ohne Befunde');
        T::ok($x->query("//section[contains(@class,'lp-hero')]//p//a[@href='https://www.example.com']")->length === 1, 'Hero-Text: www. wird Link');
        T::ok($x->query("//section[contains(@class,'lp-hero')]//p//a[@href='mailto:office@beispiel.invalid']")->length === 1, 'Hero-Text: E-Mail wird Link');
        T::ok($x->query("//section[contains(@class,'lp-themen')]//p//a[@href='https://www.example.com/themen']")->length === 1, 'Themen-Text: https:// wird Link, Punkt draußen');
        T::ok($x->query("//li[contains(@class,'lp-themen-punkt')]//strong/a[@href='https://www.example.com']")->length === 1, 'Listenpunkt: Link im Fettdruck');
        T::ok($x->query("//li[contains(@class,'lp-themen-punkt')]//a[@href='mailto:office@beispiel.invalid']")->length === 1, 'Listenpunkt: E-Mail');
        T::ok($x->query("//details//a[@href='https://www.example.com/faq']")->length === 1, 'Antwort einer häufigen Frage');
    },
    'Seite: fremde Hosts bleiben Text' => static function () use ($bauen): void {
        [$x, $html] = $bauen();
        T::eq($x->query("//a[contains(@href,'fremd')]")->length, 0, 'kein Link auf den fremden Host');
        T::contains($html, 'www.fremd.test', 'der Text steht noch da');
        T::contains($html, 'office@fremd.test', 'die fremde Mailadresse steht noch da');
    },
    'Seite: nie in Überschriften, Knöpfen, Fragen, Meta-Angaben und JSON-LD' => static function () use ($bauen): void {
        [$x, $html] = $bauen();
        T::eq($x->query('//h1//a|//h2//a|//h3//a|//summary//a|//button//a|//a//a|//title//a')->length, 0, 'kein Link in Überschrift, Frage, Knopf; keiner im Link');
        T::eq($x->query("//*[contains(@class,'lp-knopf')]//a")->length, 0, 'kein Link im Knopf');
        T::ok($x->query("//*[contains(@class,'lp-knopf') and contains(.,'Zu www.example.com')]")->length >= 1, 'Knopftext unverändert');
        T::contains((string) $x->query('//meta[@name="description"]/@content')->item(0)?->nodeValue, 'Siehe www.example.com und office@beispiel.invalid.', 'Meta-Beschreibung unverändert');
        foreach ($x->query('//script[@type="application/ld+json"]') as $ld) {
            T::notContains($ld->textContent, '<a ', 'JSON-LD ohne Link');
        }
        T::notContains($html, '<a href="https://www.example.com"><', 'kein leerer Link');
        T::eq($x->query('//a[@target]|//a[@onclick]|//a[@rel]')->length, 0, 'keine Zusatzattribute am Link');
    },
    'Kontext: absaetze und inline nehmen die Hosts aus dem Profil' => static function (): void {
        $k = new Kontext(Profil::laden(H::testprofil()), [], H::testprofil());
        T::eq($k->absaetze('Siehe www.example.com'), '<p>Siehe <a href="https://www.example.com">www.example.com</a></p>', 'absaetze');
        T::eq($k->inline('Siehe www.example.com'), 'Siehe <a href="https://www.example.com">www.example.com</a>', 'inline');
        T::eq($k->absaetze('Siehe www.fremd.test'), '<p>Siehe www.fremd.test</p>', 'fremder Host');
        T::eq($k->e('www.example.com'), 'www.example.com', 'e() verlinkt nie');
    },
    'Stil: Links im Fließtext (Absatz und Listenpunkt) sind unterstrichen, nicht nur farbig' => static function (): void {
        $css = (string) file_get_contents(H::wurzel() . '/lp/lp.css');
        T::ok((bool) preg_match('/\.lp-block p a,\s*\.lp-block li a,[^{]*\{[^}]*text-decoration:\s*underline/', $css), 'p a und li a: underline');
    },
    'Rechtstexte: Linktext wird nie selbst ein Link, bloße Adressen bleiben Text' => static function (): void {
        $html = \Endo\Lp\Rechtstext::html("Siehe [www.example.com](https://www.example.com/x) und www.example.com.\n\n- Mail office@beispiel.invalid", ['www.example.com', 'beispiel.invalid']);
        T::contains($html, '<a href="https://www.example.com/x">www.example.com</a>', 'Markdown-Link wie bisher');
        T::eq(substr_count($html, '<a '), 1, 'nur der Markdown-Link');
        T::contains($html, 'und www.example.com.', 'bloße Adresse bleibt Text');
    },
];
