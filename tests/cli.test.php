<?php
/** Kommandozeile: Exit-Codes 0/1/2, JSON-Lines, Anlegen, Profil übernehmen, Sammelordner. */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

/** @return array{0: int, 1: string, 2: string} [exit, stdout, stderr] */
$php = static function (string $skript, array $args): array {
    $p = proc_open(array_merge([PHP_BINARY, H::wurzel() . "/lp/$skript"], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    return [proc_close($p), (string) $out, (string) $err];
};
$zeilen = static function (string $out): array {
    return array_map(static fn ($z) => json_decode($z, true), array_values(array_filter(explode("\n", $out))));
};

return [
    'bauen: 0 bei Erfolg, 1 mit JSON-Lines, 2 bei technischem Fehler' => static function () use ($php, $zeilen): void {
        $o = H::ordner(H::seite());
        [$rc, $out] = $php('bauen.php', [$o]);
        T::eq([$rc, $out], [0, ''], 'Erfolg ohne Ausgabe');
        T::ok(is_file("$o/index.html"), 'gebaut');
        $s = H::seite(); $s['bausteine'][0]['titel'] = '';
        $o = H::ordner($s);
        [$rc, $out] = $php('bauen.php', [$o]);
        T::eq($rc, 1, 'sperrend = 1');
        $z = $zeilen($out);
        T::ok($z !== [] && array_keys($z[0]) === ['art', 'pfad', 'technik', 'kunde'], 'JSON-Lines mit art, pfad, technik, kunde');
        T::eq($z[0]['kunde'] ?? '', 'Abschnitt 1 „Kopfbereich": „Titel" ist leer.', 'Kundentext mit echten Umlauten');
        [$rc, , $err] = $php('bauen.php', [H::ordner(H::seite(), '3.50.0')]);
        T::eq($rc, 2, 'falsche Marke = 2');
        T::contains($err, 'ENDOSKELETON', 'Meldung auf stderr');
        [$rc] = $php('bauen.php', []);
        T::eq($rc, 2, 'ohne Argument = 2');
        [$rc] = $php('bauen.php', ['/gibt/es/nicht']);
        T::eq($rc, 2, 'fehlender Ordner = 2');
    },
    'pruefen: --heute stellt die Uhr' => static function () use ($php, $zeilen): void {
        $o = H::ordner(H::seite([Endo\Lp\Katalog::laden()->baustein('termin')['beispiel']]));
        $php('bauen.php', [$o]);
        [$rc] = $php('pruefen.php', [$o, '--heute', '2026-09-27T12:00']);
        T::eq($rc, 0, 'künftiger Termin: 0');
        [$rc, $out] = $php('pruefen.php', [$o, '--heute', '2100-01-01T00:00']);
        T::eq($rc, 1, 'alle vergangen: 1');
        T::ok(in_array('alle Termine vergangen', array_column($zeilen($out), 'technik'), true), 'Befund ausgegeben');
        [$rc] = $php('pruefen.php', [$o, '--heute', 'morgen']);
        T::eq($rc, 2, 'ungültige Uhrzeit: 2');
    },
    'neu: legt an, verweigert Vorhandenes und ungültige Namen' => static function () use ($php): void {
        $basis = T::tmp();          // ein Ausbruch mit ../ landet so noch im Temp-Ordner des Tests
        $sammel = "$basis/sammel";
        mkdir($sammel);
        [$rc, $out] = $php('neu.php', [$sammel, 'zoom-test', 'zoom-vortrag']);
        T::eq($rc, 0, 'angelegt: ' . $out);
        T::ok(is_file("$sammel/zoom-test/seite.json") && is_dir("$sammel/zoom-test/.git"), 'Bauplan und Git');
        T::eq(trim((string) file_get_contents("$sammel/zoom-test/ENDOSKELETON")), trim((string) file_get_contents(H::wurzel() . '/VERSION')), 'Marke');
        [$rc] = $php('neu.php', [$sammel, 'zoom-test', 'zoom-vortrag']);
        T::eq($rc, 2, 'vorhanden: 2');
        foreach (['../raus', 'Gross', 'a', 'profil', 'x y'] as $slug) {
            [$rc] = $php('neu.php', [$sammel, $slug, 'zoom-vortrag']);
            T::eq($rc, 2, "ungültiger Name „{$slug}\": 2");
        }
        [$rc] = $php('neu.php', [$sammel, 'andere', '../../VERSION']);
        T::eq($rc, 2, 'Vorlage außerhalb: 2');
        T::ok(!file_exists("$basis/raus"), 'nichts außerhalb angelegt');
    },
    'neu + Profil übernehmen: Vorlage baut erst nach dem Füllen' => static function () use ($php, $zeilen): void {
        $sammel = T::tmp();
        $php('neu.php', [$sammel, 'vortrag', 'zoom-vortrag']);
        [$rc] = $php('profil-uebernehmen.php', ["$sammel/vortrag", H::testprofil()]);
        T::eq($rc, 0, 'Profil übernommen');
        T::ok(is_file("$sammel/vortrag/profil/VERSION"), 'profil/VERSION');
        [$rc, $out] = $php('bauen.php', ["$sammel/vortrag"]);
        T::eq($rc, 1, 'Lücken der Vorlage sperren');
        T::ok(in_array('bausteine.1.termine.0.beginn', array_column($zeilen($out), 'pfad'), true), 'Termin-Lücke gemeldet');
    },
    'sammelordner: .htaccess mit 404-Sperren' => static function () use ($php): void {
        $d = T::tmp();
        [$rc] = $php('sammelordner.php', [$d]);
        T::eq($rc, 0, 'geschrieben');
        $h = (string) file_get_contents("$d/.htaccess");
        foreach (['ErrorDocument 404 default', 'Header always set Cache-Control "no-store"', 'Header always set X-Robots-Tag "noindex"',
                  'RedirectMatch 404 "(?i)\.(json|md|php)$"', 'RedirectMatch 404 "(?i)/_profil(/|$)"', 'RedirectMatch 404 "(?i)/profil(/|$)"',
                  'RedirectMatch 404 "(?i)/ENDOSKELETON$"'] as $zeile) {
            T::contains($h, $zeile, 'Zeile');
        }
        T::notContains($h, "\nRequire", 'nie Require');
        T::ok(preg_match('~(?i)/profil(/|$)~', '/zoom/profil/profil.json') === 1 && preg_match('~(?i)\.(json|md|php)$~', '/zoom/seite.json') === 1, 'Muster treffen die Pfade');
        T::ok(preg_match('~(?i)/profil(/|$)~', '/zoom/index.html') === 0, 'Seite bleibt erreichbar');
    },
];
