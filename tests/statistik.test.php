<?php
/**
 * Besucherzähler für Webseiten (statistik.js, 3.63.0): der Vertrag v1 mit dem Panel (POST panel.ssi.at/lp/treffer), die Hostprüfung, die Abschalter
 * (Do Not Track, Global Privacy Control, data-statistik="aus"), kein Cookie und kein Web-Speicher, jeder Fehler verschluckt, höchstens 2 KB.
 * Das Verhalten läuft in tests/statistik-lauf.mjs (Node, eine Attrappe der Seite; ohne Node wird nur der Quelltext geprüft), den echten Browser
 * prüft ci/browser-statistik.js. Das Panel nimmt genau diese Nachricht an (App\Services\Statistik\Treffer/Annahme, Webseiten-Spezifikation Abschnitt 1).
 */

declare(strict_types=1);

return (static function (): array {
    $root = dirname(__DIR__);
    $js = (string) @file_get_contents("$root/statistik.js");
    // Quelltext ohne Kommentare: Wörter wie „Cookie“ stehen im Kopfkommentar, im Code dürfen sie nicht vorkommen.
    $code = (string) preg_replace(['~/\*.*?\*/~s', '~(?<!:)//[^\n]*~'], '', $js);

    $lauf = null;
    $holen = static function () use (&$lauf, $root): ?array {
        if ($lauf === null) {
            $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
            $ausgabe = $node === '' ? '' : (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg("$root/tests/statistik-lauf.mjs") . ' 2>&1');
            $lauf = json_decode($ausgabe, true);
            if ($node === '') {
                fwrite(STDERR, "statistik.test.php: node fehlt — das Verhalten von statistik.js wird nicht ausgeführt, nur der Quelltext geprüft.\n");
            }
            $lauf = is_array($lauf) ? $lauf : ($node === '' ? [] : ['fehler' => $ausgabe]);
        }
        if ($lauf !== [] && !isset($lauf['normal'])) {
            T::ok(false, 'tests/statistik-lauf.mjs lieferte kein Ergebnis: ' . substr((string) ($lauf['fehler'] ?? ''), 0, 300));

            return null;
        }

        return $lauf === [] ? null : $lauf;
    };
    $nachrichten = static fn (array $l): array => array_map(static fn (array $g): array => json_decode($g['text'], true), $l['gesendet']);
    $arten = static fn (array $l): string => implode(',', array_column($nachrichten($l), 'art'));

    return [
        'statistik.js liegt neben shared.js, ist klein und nennt die Einbindungszeile' => static function () use ($root, $js): void {
            T::ok(strlen($js) > 1500, 'statistik.js fehlt oder ist leer');
            T::ok(substr_count($js, "\n") <= 100, 'statistik.js ist mit ' . substr_count($js, "\n") . ' Zeilen zu groß (höchstens 100)');
            T::ok(is_file("$root/shared.js"), 'shared.js (Nachbar) fehlt');
            T::contains($js, '<script src="statistik.js" data-quelle="web:beispiel.at" defer></script>', 'Einbindungszeile im Kopfkommentar');
        },
        'ohne globale Variable, in einer eigenen Funktion mit use strict, nichts Kundenspezifisches' => static function () use ($code, $holen): void {
            T::ok(str_starts_with(trim($code), '(function () {') && str_ends_with(trim($code), '})();'), 'der Code steht nicht in einer sofort ausgeführten Funktion');
            T::contains($code, "'use strict';", 'use strict');
            preg_match_all('~https?://([^/\s"\']+)~i', $code, $m);
            T::eq(array_values(array_unique($m[1])), ['panel.ssi.at'], 'der einzige Host im Code ist das Panel');
            T::ok(!preg_match('/[a-z0-9._%+-]+@[a-z0-9-]+\./i', $code), 'E-Mail-Adresse im Code');
            if (($l = $holen()) !== null) {
                foreach (['normal', 'mitWww', 'groesstFall'] as $name) { T::eq($l[$name]['neueGlobale'], [], "$name: neue globale Variablen"); }
            }
        },
        'Quelle aus data-quelle: nur web:<domain> (höchstens 56 Zeichen, mit Punkt, klein) sendet' => static function () use ($code, $holen, $nachrichten): void {
            T::contains($code, 'dataset.quelle', 'liest data-quelle');
            if (($l = $holen()) === null) { return; }
            T::eq(count($l['quellen']), 14, 'Anzahl Quellen-Fälle');
            foreach ($l['quellen'] as $quelle => $ergebnis) {
                $gueltig = $quelle === 'web:' . str_repeat('a', 53) . '.at';
                T::eq(count($ergebnis['gesendet']) > 0, $gueltig, 'Quelle „' . addcslashes((string) $quelle, "\n") . '“ ' . ($gueltig ? 'sendet' : 'sendet nicht'));
            }
            T::eq($nachrichten($l['normal'])[0]['liste'], 'web:beispiel.test', 'liste = Inhalt von data-quelle');
            T::eq(count($l['ohneSkript']['gesendet']) + count($l['skriptOhneDaten']['gesendet']), 0, 'ohne Einbindungszeile (kein currentScript) nichts');
        },
        'Hostprüfung: nur auf der Domain und auf www.<Domain>, nie in Vorschau, Kopie oder Sicherung' => static function () use ($holen, $nachrichten): void {
            if (($l = $holen()) === null) { return; }
            T::eq(count($l['normal']['gesendet']), 2, 'auf der Domain selbst: aufruf und verlassen');
            T::eq(count($l['mitWww']['gesendet']), 1, 'auf www.<Domain>: aufruf');
            T::eq(count($l['fremd']), 7, 'Anzahl fremder Hosts');
            foreach ($l['fremd'] as $host => $ergebnis) { T::eq(count($ergebnis['gesendet']), 0, 'fremder Host „' . $host . '“ sendet nicht'); }
            T::eq(count($l['andereDomain']['gesendet']), 1, 'eine andere Domain mit eigener Quelle sendet für sich selbst');
            T::eq($nachrichten($l['andereDomain'])[0]['liste'], 'web:anders.example', 'und nennt ihre eigene Quelle');
        },
        'Nachricht an das Panel: POST per sendBeacon als text/plain an panel.ssi.at/lp/treffer' => static function () use ($code, $holen): void {
            T::contains($code, 'navigator.sendBeacon(', 'sendBeacon');
            T::ok(!preg_match('/\bfetch\s*\(|XMLHttpRequest|new Image\b|application\/json/', $code), 'ein anderer Sendeweg als sendBeacon');
            if (($l = $holen()) === null) { return; }
            foreach ($l['normal']['gesendet'] as $g) {
                T::eq($g['adresse'], 'https://panel.ssi.at/lp/treffer', 'Adresse');
                T::eq($g['typ'], 'text/plain;charset=UTF-8', 'Inhaltstyp (einfache Anfrage ohne Vorabfrage)');
            }
        },
        'Felder des Vertrags v1: aufruf und verlassen, ohne Formular und Absenden' => static function () use ($holen, $nachrichten, $arten): void {
            if (($l = $holen()) === null) { return; }
            $n = $nachrichten($l['normal']);
            T::eq($arten($l['normal']), 'aufruf,verlassen', 'Arten (pagehide und Verstecken zusammen senden genau einmal)');
            $felder = static function (array $m): string { $k = array_keys($m); sort($k); return implode(',', $k); };
            T::eq($felder($n[0]), 'art,breite,liste,pfad,ref,utm,v', 'Felder des aufruf');
            T::eq($felder($n[1]), 'art,breite,dauer,liste,pfad,ref,tiefe,utm,v', 'Felder des verlassen');
            T::eq($felder($n[0]['utm']), 'kampagne,medium,quelle', 'Felder von utm');
            T::ok($n[0]['v'] === 1 && $n[0]['pfad'] === '/seite/' && $n[0]['breite'] === 390, 'v, pfad und breite');
            T::eq($n[0]['utm']['quelle'], 'Insta gramb', 'UTM nur Buchstaben, Ziffern, Leerzeichen und _ . -');
            T::eq($n[0]['utm']['kampagne'], str_repeat('a', 60), 'UTM höchstens 60 Zeichen');
            T::eq($n[0]['utm']['medium'], 'social', 'UTM medium');
        },
        'ref: nur der Host in Kleinbuchstaben, die eigene Domain (auch mit www.) nie' => static function () use ($holen, $nachrichten): void {
            if (($l = $holen()) === null) { return; }
            T::eq($nachrichten($l['normal'])[0]['ref'], 'www.verweis.test', 'Verweis nur als Host, klein, ohne Pfad und Abfrage');
            T::eq($nachrichten($l['eigenerVerweis'])[0]['ref'], '', 'eigene Domain als Verweis bleibt leer');
        },
        'verlassen: dauer in ganzen sichtbaren Sekunden (höchstens 3600), tiefe aus 0|25|50|75|100' => static function () use ($holen, $nachrichten): void {
            if (($l = $holen()) === null) { return; }
            $v = $nachrichten($l['normal'])[1];
            T::ok($v['dauer'] === 12 && $v['tiefe'] === 75, 'dauer 12 und tiefe 75 (weitester unterer Fensterrand 2700 von 4000 Pixeln): ' . json_encode([$v['dauer'], $v['tiefe']]));
            foreach (['tiefe0' => 0, 'tiefe25' => 25, 'tiefe50' => 50, 'tiefe100' => 100] as $name => $tiefe) {
                T::eq($nachrichten($l[$name])[1]['tiefe'], $tiefe, "$name: tiefe");
            }
            T::eq($nachrichten($l['dauerGrenze'])[1]['dauer'], 3600, 'dauer höchstens 3600');
            T::eq($nachrichten($l['zurueckgescrollt'])[1]['tiefe'], 100, 'tiefe ist das Maximum: erst ganz nach unten, dann zurück nach oben zählt 100');
            $v = $nachrichten($l['versteckterTab']);
            T::eq(count($v), 2, 'ein versteckter Tab meldet aufruf und verlassen, ohne dass pagehide nötig ist');
            T::ok(($v[1]['art'] ?? '') === 'verlassen' && $v[1]['dauer'] === 5 && $v[1]['tiefe'] === 75, 'verstecken des Tabs: verlassen mit dauer 5 und tiefe 75: ' . json_encode($v[1] ?? null));
        },
        'aufruf erst, wenn die Seite sichtbar ist; ohne Aufruf kein verlassen' => static function () use ($holen, $arten): void {
            if (($l = $holen()) === null) { return; }
            T::eq(count($l['unsichtbar']['gesendet']), 0, 'ein unsichtbar geladener Tab sendet nichts, auch beim Verlassen');
            T::eq($arten($l['spaeterAufruf']), 'aufruf', 'wird die Seite sichtbar, genau ein aufruf (auch beim zweiten Wechsel)');
        },
        'Do Not Track, Global Privacy Control und data-statistik="aus" schalten den Zähler ab' => static function () use ($code, $holen): void {
            T::contains($code, 'dataset.statistik', 'liest data-statistik am body');
            if (($l = $holen()) === null) { return; }
            foreach (['dnt', 'gpc', 'body', 'ohneBody'] as $name) { T::eq(count($l['aus'][$name]['gesendet']), 0, "aus/$name: nichts gesendet, auch kein verlassen"); }
            T::eq(count($l['aus']['dntEgal']['gesendet']), 2, 'DNT „0“, GPC aus und data-statistik="an" schalten nichts ab');
            T::eq(count($l['beaconFehlt']['gesendet']), 0, 'ohne sendBeacon nichts');
        },
        'kein Cookie, kein Web-Speicher, keine Kennung' => static function () use ($code, $holen): void {
            T::ok(!preg_match('/cookie|localStorage|sessionStorage|indexedDB|openDatabase|Math\.random|crypto\b|fingerprint|canvas/i', $code), 'Cookie, Speicher oder Kennung im Code');
            if (($l = $holen()) === null) { return; }
            T::eq($l['normal']['zugriffe'], [], 'kein Zugriff auf Cookie, localStorage, sessionStorage oder indexedDB im Lauf');
            T::eq($l['ohneSpeicher']['zugriffe'], [], 'auch nicht beim Verlassen');
            T::eq($l['spionWirkt']['zugriffe'], ['localStorage', 'cookie'], 'der Spion schlägt an (Gegenprobe der Messung)');
        },
        'jeder Fehler wird verschluckt: Einrichtung, Aufruf, Verlassen' => static function () use ($code, $holen): void {
            T::ok(substr_count($code, 'catch (e)') >= 3, 'zu wenige try/catch im Code (Einrichtung, Aufruf, Verlassen)');
            if (($l = $holen()) === null) { return; }
            foreach (['beaconWirft', 'beaconWirftBeiSichtbar', 'hoeheWirft', 'skriptOhneDaten', 'ohneSkript', 'beaconFehlt'] as $name) {
                T::eq($l[$name]['fehler'], null, "$name: kein Fehler nach außen");
            }
            T::eq(count($l['beaconWirft']['gesendet']), 0, 'ein werfender sendBeacon sendet nichts, die Seite merkt nichts');
        },
        'Nachricht höchstens 2 KB (Grenze des Panels), auch im größten Fall' => static function () use ($holen): void {
            if (($l = $holen()) === null) { return; }
            T::eq(count($l['groesstFall']['gesendet']), 2, 'größter Fall sendet');
            foreach ($l['groesstFall']['gesendet'] as $g) { T::ok(strlen($g['text']) <= 2048, 'Nachricht mit ' . strlen($g['text']) . ' Byte (höchstens 2048)'); }
        },
        'dieselben Grenzen wie der Zähler der Landingpages (lp/lp.js)' => static function () use ($root, $code): void {
            $lp = (string) @file_get_contents("$root/lp/lp.js");
            T::ok(strlen($lp) > 5000, 'lp/lp.js fehlt');
            foreach (['.slice(0, 60)', '.slice(0, 120)', '.slice(0, 100)', "/[^A-Za-z0-9 _.-]/g", 'Math.min(3600,', '* 4) * 25', "'text/plain;charset=UTF-8'", 'window.innerWidth', 'navigator.globalPrivacyControl'] as $stelle) {
                T::contains($code, $stelle, 'statistik.js');
                T::contains($lp, $stelle, 'lp/lp.js');
            }
        },
    ];
})();
