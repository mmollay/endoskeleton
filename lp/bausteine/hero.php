<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Kopfbereich: einzige H1 der Seite, optional Bild (rechts oder dahinter) und Knopf. @var array $b @var Endo\Lp\Kontext $k @var int $nr */
$titelId = $k->id($nr, 'titel');
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<div class="lp-hero-text">
<?php if (($b['dachzeile'] ?? '') !== ''): ?>
<p class="lp-hero-dachzeile"><?= $k->e($b['dachzeile']) ?></p>
<?php endif ?>
<h1 class="lp-hero-titel" id="<?= $titelId ?>"><?= $k->e($b['titel']) ?></h1>
<?= $k->absaetze($b['text'] ?? '') ?>

<?= isset($b['knopf']) ? $k->knopf($b['knopf']) : '' ?>

</div>
<?php if ($b['variante'] !== 'schlicht' && isset($b['bild'])): ?>
<?= $k->bild($b['bild'], 'lp-hero-bild', true) ?>

<?php endif ?>
</div>
</section>
