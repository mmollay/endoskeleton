<?php
/**
 * Läuft in einem eigenen PHP-Prozess (aufgerufen von tests/faltung.test.php, FA08): Lässt jeden einzelnen Regex-Schritt der Faltung scheitern
 * (Schadensattrappe tests/hilfe/preg-stoerung.php) und meldet als JSON, ob der Text dann „nicht prüfbar“ und damit gesperrt war — erst für die Suche in der
 * Sperrliste (Endo\Lp\Sperrliste), dann für die ganze Prüfung einer Seite (Endo\Lp\Pruefer, Seitenordner als Argument).
 *
 *   php8.2 tests/hilfe/preg-stoerung-lauf.php <seitenordner> [<seitenordner mit Preis>]
 *
 * Der zweite Ordner ist eine Seite mit einem Termin mit Preis: Dort ruft der Prüfer auch den Regex für „kostenlos“ auf (Pruefer::KOSTENLOS); scheitert er, darf das nie „kein Treffer“ heißen.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/preg-stoerung.php';           // vor dem ersten Aufruf von Code des Namensraums Endo\Lp
require dirname(__DIR__, 2) . '/lp/autoload.php';

use Endo\Lp\Pruefer;
use Endo\Lp\Sperrliste;
use Endo\LpTest\PregStoerung;

$ordner = (string) ($argv[1] ?? '');
$ordnerPreis = (string) ($argv[2] ?? '');
// eine Buchstabenreihe, eine Schriftform, ein Umlaut, U+0308 und ein Füller (zweite Lesart) — und kein Sperrwort
$probe = 'Das ist g a r a n t und ä, ' . mb_chr(0x1D5EE) . ', ⓖ, İ, a' . "\u{0308}" . ' und ' . "\u{3164}";
$ergebnis = ['ohne' => [], 'gesehen' => [], 'versuche' => 0, 'nichtGesperrt' => [], 'pruefer' => ['kontrolle' => [], 'versuche' => 0, 'nichtGemeldet' => []], 'preis' => ['kontrolle' => [], 'aufgerufen' => false, 'nichtGemeldet' => []]];

// ── die Suche in der Sperrliste: zwei Listen, ein Einzelwort (Strang je Lesart) und eine Wendung (Satzstrang) ───────────────────────
$gesehen = [];
foreach ([['garantiert'], ['Durchfall verschwunden']] as $liste) {
    PregStoerung::$aufzeichnen = true;
    PregStoerung::$gesehen = [];
    PregStoerung::$anzahl = [];
    $ergebnis['ohne'][] = Sperrliste::wort($probe, $liste);
    PregStoerung::$aufzeichnen = false;
    $hier = PregStoerung::$gesehen;
    $anzahl = PregStoerung::$anzahl;
    $gesehen = array_values(array_unique(array_merge($gesehen, $hier)));
    foreach ($hier as $muster) {
        // jeder Aufruf des Musters einzeln (der 1., der 2. …): z. B. der Strang erst beim Text, nachdem er beim Eintrag noch ging
        for ($k = 0; $k < min($anzahl[$muster], 6); $k++) {
            PregStoerung::$scheitern = $muster;
            PregStoerung::$verschonen = $k;
            $r = Sperrliste::wort($probe, $liste);
            PregStoerung::$scheitern = null;
            PregStoerung::$verschonen = 0;
            $ergebnis['versuche']++;
            if ($r !== Sperrliste::NICHT_PRUEFBAR) {
                $ergebnis['nichtGesperrt'][] = $liste[0] . ': ' . $muster . ' (Aufruf ' . ($k + 1) . ')';
            }
        }
    }
}
$ergebnis['gesehen'] = count($gesehen);

// ── die ganze Prüfung einer Seite: Der Seitenordner trägt den Text $probe und eine Sperrliste ohne Treffer ──────────────────────────
if ($ordner !== '' && is_dir($ordner)) {
    $heute = '2026-09-27T12:00';
    $meldet = static fn (array $befunde): array => array_values(array_filter($befunde,
        static fn ($b) => $b->art === 'sperrend' && preg_match('/^(Sperrliste|Grenzwort): (Text|Eintrag) nicht prüfbar/u', $b->technik) === 1));
    $meldungen = static fn (array $befunde): array => array_map(static fn ($b) => $b->pfad . ' | ' . $b->technik, $meldet($befunde));
    PregStoerung::$aufzeichnen = true;
    PregStoerung::$gesehen = [];
    PregStoerung::$anzahl = [];
    $kontrolle = Pruefer::pruefen($ordner, $heute);
    PregStoerung::$aufzeichnen = false;
    $ergebnis['pruefer']['kontrolle'] = $meldungen($kontrolle);
    $anzahl = PregStoerung::$anzahl;
    foreach (array_values(array_intersect($gesehen, PregStoerung::$gesehen)) as $muster) {   // nur die Muster der Faltung, nicht die des Prüfers selbst
        for ($k = 0; $k < min($anzahl[$muster], 3); $k++) {
            PregStoerung::$scheitern = $muster;
            PregStoerung::$verschonen = $k;
            $befunde = Pruefer::pruefen($ordner, $heute);
            PregStoerung::$scheitern = null;
            PregStoerung::$verschonen = 0;
            $ergebnis['pruefer']['versuche']++;
            if ($meldet($befunde) === []) {
                $ergebnis['pruefer']['nichtGemeldet'][] = $muster . ' (Aufruf ' . ($k + 1) . ')';
            }
        }
    }
}

// ── der Regex „kostenlos trotz Preis“ (nur bei einem Termin mit Preis) ──────────────────────────────────────────────────────────────
if ($ordnerPreis !== '' && is_dir($ordnerPreis)) {
    $heute = '2026-09-27T12:00';
    $muster = (string) (new ReflectionClassConstant(Pruefer::class, 'KOSTENLOS'))->getValue();
    $nicht = static fn (array $befunde): array => array_values(array_filter($befunde,
        static fn ($b) => $b->art === 'sperrend' && preg_match('/^(Sperrliste|Grenzwort): (Text|Eintrag) nicht prüfbar/u', $b->technik) === 1));
    PregStoerung::$aufzeichnen = true;
    PregStoerung::$gesehen = [];
    $kontrolle = Pruefer::pruefen($ordnerPreis, $heute);
    PregStoerung::$aufzeichnen = false;
    $ergebnis['preis']['aufgerufen'] = in_array($muster, PregStoerung::$gesehen, true);
    $ergebnis['preis']['kontrolle'] = array_map(static fn ($b) => $b->pfad, $nicht($kontrolle));
    PregStoerung::$scheitern = $muster;
    $befunde = Pruefer::pruefen($ordnerPreis, $heute);
    PregStoerung::$scheitern = null;
    if ($nicht($befunde) === []) {
        $ergebnis['preis']['nichtGemeldet'][] = 'KOSTENLOS';
    }
}

echo json_encode($ergebnis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
