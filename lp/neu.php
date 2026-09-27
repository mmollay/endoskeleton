<?php
/**
 * Legt eine neue Landingpage im Sammelordner eines Kunden an: <slug>/seite.json aus einer Vorlage, ENDOSKELETON mit
 * diesem Stand, eigenes Git. Danach: Profil übernehmen, seite.json füllen, bauen, prüfen.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/autoload.php';

use Endo\Lp\Befund;
use Endo\Lp\Cli;
use Endo\Lp\Dateien;

Cli::lauf($argv, 'php lp/neu.php <sammelordner> <slug> <vorlage>   (Vorlagen: ' . implode(', ', array_map(static fn ($f) => basename($f, '.json'), glob(__DIR__ . '/vorlagen/*.json') ?: [])) . ')', 3,
    static function (array $a): array {
        [$sammel, $slug, $vorlage] = $a;
        $sammel = Cli::ordner($sammel);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $slug) || in_array($slug, ['profil', 'es', 'img'], true)) {
            throw new InvalidArgumentException("ungültiger Name „{$slug}\" — nur a-z, 0-9 und -, 2 bis 41 Zeichen");
        }
        $quelle = __DIR__ . "/vorlagen/$vorlage.json";
        if (!preg_match('/^[a-z0-9-]+$/', $vorlage) || !is_file($quelle)) {
            throw new InvalidArgumentException("Vorlage „{$vorlage}\" gibt es nicht");
        }
        $ziel = "$sammel/$slug";
        if (file_exists($ziel)) {
            throw new InvalidArgumentException("$ziel gibt es schon");
        }
        Dateien::kopieren($quelle, "$ziel/seite.json");
        Dateien::schreiben("$ziel/ENDOSKELETON", trim((string) file_get_contents(dirname(__DIR__) . '/VERSION')) . "\n");
        exec('git -C ' . escapeshellarg($ziel) . ' init -q 2>&1', $aus, $rc);
        if ($rc !== 0) {
            throw new RuntimeException('git init fehlgeschlagen: ' . implode(' ', $aus));
        }
        return [Befund::hinweis($slug, "angelegt: $ziel (Vorlage $vorlage) — nächster Schritt: lp/profil-uebernehmen.php",
            'Die Seite wurde angelegt. Die Lücken im Bauplan ({{…}}) müssen noch gefüllt werden.')];
    });
