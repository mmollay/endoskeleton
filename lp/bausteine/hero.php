<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Kopfbereich: einzige H1 der Seite, optional Bild (rechts oder dahinter) und Knopf. Bei bild-rechts mit video_kennung (3.61.0) wird das Bild
 * zum Startbild eines YouTube-Videos mit zwei Klicks (Kontext::videoStart). @var array $b @var Endo\Lp\Kontext $k @var int $nr */
$titelId = $k->id($nr, 'titel');
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<div class="lp-hero-text">
<?php if (($b['dachzeile'] ?? '') !== ''): ?>
<p class="lp-hero-dachzeile"><?= $k->e($b['dachzeile']) ?></p>
<?php endif ?>
<h1 class="lp-hero-titel" id="<?= $titelId ?>"<?= $k->groesse($b['titel']) ?>><?= $k->e($b['titel']) ?></h1>
<?= $k->absaetze($b['text'] ?? '') ?>

<?= isset($b['knopf']) ? $k->knopf($b['knopf']) : '' ?>

</div>
<?php if ($b['variante'] === 'bild-rechts' && isset($b['bild'], $b['video_kennung'])): ?>
<div class="lp-hero-medien">
<?= $k->videoStart($b['video_kennung'], $b['titel'], $b['bild'], true) ?>

</div>
<?php elseif ($b['variante'] !== 'schlicht' && isset($b['bild'])): ?>
<?= $k->bild($b['bild'], 'lp-hero-bild', true) ?>

<?php endif ?>
</div>
</section>
