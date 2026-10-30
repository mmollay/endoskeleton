<?php
/**
 * Erzeugt die synthetische Beispielbewegung „Winken“ (lp/musterbuch/bew/beispiel-winken.bewegung) rein aus Mathematik — kein Mensch, keine Kamera.
 *
 *   php8.2 ci/bewegung-beispiel-bauen.php             schreibt die Datei ins Musterbuch
 *   php8.2 ci/bewegung-beispiel-bauen.php --ausgeben  schreibt auf die Standardausgabe (Test BW09 vergleicht beides)
 *
 * Aufbau: 3 s bei 30 Bildern je Sekunde, hochkant 9:16 (Bild- und Shortformat), die Figur steht vor dem Betrachter. Links/rechts wie im Modell
 * (die „linke“ Seite der Person liegt im Bild rechts). Der rechte Arm hebt sich, winkt dreimal und senkt sich wieder — Bild 0 und das letzte
 * Bild liegen nahe beieinander, die Schleife springt kaum. Augen und Mund bleiben null (kein Gesicht, Format-Regel).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../lp/autoload.php';

use Endo\Lp\Bewegung;

const FPS = 30;
const BILDER = 90;
const VERH = 0.5625;

/** Weicher Übergang 0…1 (Hermite). */
function weich(float $t): float
{
    $t = max(0.0, min(1.0, $t));
    return $t * $t * (3 - 2 * $t);
}

/** @return list<float|int> ein Bild als flache Liste x, y, Sichtbarkeit je Punkt */
function bild(int $n): array
{
    $t = $n / FPS;
    $hebung = weich($t / 0.6) * (1 - weich(($t - 2.4) / 0.6));              // 0 = Arm unten, 1 = Arm oben
    $welle = $hebung * 0.5 * sin(2 * M_PI * 1.67 * ($t - 0.6));              // Schwenk der Hand, ±0,5 rad
    $wiegen = 0.004 * sin(2 * M_PI * $t / 3.0);                              // leichtes Wiegen des ganzen Körpers
    // Höheneinheiten (Bildhöhe = 1), Ursprung auf der Mittellinie; später in x umgerechnet
    $p = [];
    $p[0] = [0, 0.20];                                                        // Nase
    $p[7] = [0.035, 0.205];  $p[8] = [-0.035, 0.205];                         // Ohren links/rechts (links = +u)
    $p[11] = [0.10, 0.31];   $p[12] = [-0.10, 0.31];                          // Schultern
    $p[13] = [0.12, 0.45];   $p[15] = [0.12, 0.58];                           // linker Arm hängt
    $schulter = [-0.10, 0.31];
    $unten = [-0.12, 0.45];
    $oben = [-0.20, 0.27];
    $ell = [$unten[0] + ($oben[0] - $unten[0]) * $hebung, $unten[1] + ($oben[1] - $unten[1]) * $hebung];
    $winkel = M_PI + $hebung * (($welle) - M_PI);                              // 180° (nach unten) → Hand nach oben mit Schwenk
    $richtung = [sin($winkel), -cos($winkel)];
    $hand = [$ell[0] + 0.13 * $richtung[0], $ell[1] + 0.13 * $richtung[1]];
    $quer = [-$richtung[1], $richtung[0]];
    $p[14] = $ell;           $p[16] = $hand;
    $hand2 = static fn (array $w, array $r, array $q, float $vor, float $seit): array => [$w[0] + $vor * $r[0] + $seit * $q[0], $w[1] + $vor * $r[1] + $seit * $q[1]];
    $p[18] = $hand2($hand, $richtung, $quer, 0.025, -0.012);                  // kleiner Finger rechts
    $p[20] = $hand2($hand, $richtung, $quer, 0.035, 0.0);                     // Zeigefinger rechts
    $p[22] = $hand2($hand, $richtung, $quer, 0.015, 0.014);                   // Daumen rechts
    $p[17] = [0.12, 0.58 + 0.025];  $p[19] = [0.12, 0.58 + 0.035];  $p[21] = [0.12 - 0.014, 0.58 + 0.015]; // linke Hand (hängend)
    $p[23] = [0.06, 0.55];   $p[24] = [-0.06, 0.55];                          // Hüften
    $p[25] = [0.065, 0.72];  $p[26] = [-0.065, 0.72];                         // Knie
    $p[27] = [0.07, 0.88];   $p[28] = [-0.07, 0.88];                          // Knöchel
    $p[29] = [0.07, 0.905];  $p[30] = [-0.07, 0.905];                         // Fersen
    $p[31] = [0.09, 0.925];  $p[32] = [-0.09, 0.925];                         // Fußspitzen
    $aus = [];
    for ($i = 0; $i < count(Bewegung::PUNKTE); $i++) {
        if (in_array($i, Bewegung::GESICHT, true)) {
            array_push($aus, 0, 0, 0);
            continue;
        }
        [$u, $v] = $p[$i];
        $x = max(0.0, min(1.0, 0.5 + ($u + $wiegen) / VERH));
        $y = max(0.0, min(1.0, $v));
        array_push($aus, round($x, 3), round($y, 3), 1);
    }
    return $aus;
}

$zahlen = static fn (array $z): string => '[' . implode(',', array_map(static fn ($w) => is_float($w) ? rtrim(rtrim(number_format($w, 3, '.', ''), '0'), '.') ?: '0' : (string) $w, $z)) . ']';
$kopf = json_encode(['v' => 1, 'name' => 'Winken (synthetisch)', 'quelle' => 'synthetisch', 'verhaeltnis' => VERH, 'fps' => FPS, 'dim' => 2, 'dauer_ms' => (int) round(BILDER * 1000 / FPS)],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$zeilen = [];
for ($n = 0; $n < BILDER; $n++) {
    $zeilen[] = $zahlen(bild($n));
}
$text = substr($kopf, 0, -1) . ",\n\"punkte\":" . json_encode(Bewegung::PUNKTE, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ",\n\"rahmen\":[\n" . implode(",\n", $zeilen) . "\n]}";
$text = rtrim($text, "\n");
if (Bewegung::pruefen(json_decode($text, false, 8, JSON_THROW_ON_ERROR), strlen($text)) !== null) {
    fwrite(STDERR, "Die erzeugte Datei besteht den Prüfer nicht: " . Bewegung::pruefen(json_decode($text, false, 8, JSON_THROW_ON_ERROR), strlen($text)) . "\n");
    exit(1);
}
if (in_array('--ausgeben', $argv, true)) {
    echo $text;   // ohne Zeilenumbruch am Ende: der Aufrufer hängt keinen an
    exit(0);
}
$ziel = dirname(__DIR__) . '/lp/musterbuch/bew/beispiel-winken.bewegung';
@mkdir(dirname($ziel), 0755, true);
file_put_contents($ziel, $text . "\n");
echo "geschrieben: $ziel (" . (strlen($text) + 1) . " Byte, " . BILDER . " Bilder)\n";
