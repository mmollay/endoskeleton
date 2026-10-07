<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Vortragende Person (3.52.0): Name, Rolle in einer Zeile, zwei bis drei Sätze, auf Wunsch ein Bild aus dem freigegebenen
 * Bestand (Variante bild-links). Ohne Namen wird nichts gebaut — so steht der Block in der Vorlage, ohne dass eine Seite ohne
 * Angaben ihn zeigt. @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
if (trim((string) ($b['name'] ?? '')) === '') {
    return;
}
$mitBild = $b['variante'] === 'bild-links' && isset($b['bild']);
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<div class="lp-person-inhalt">
<?php if ($mitBild): ?>
<?= $k->bild($b['bild'], 'lp-person-bild') ?>

<?php endif ?>
<div class="lp-person-text">
<p class="lp-person-name"><?= $k->e((string) ($b['name'] ?? '')) ?></p>
<?php if (($b['rolle'] ?? '') !== ''): ?>
<p class="lp-person-rolle"><?= $k->e($b['rolle']) ?></p>
<?php endif ?>
<?= $k->absaetze($b['text'] ?? '') ?>

</div>
</div>
</div>
</section>
