<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Kopfbereich: einzige H1 der Seite, optional Bild (rechts oder dahinter) und Knopf. Bei bild-rechts wird das Bild zum Startbild eines Videos:
 * mit video_kennung (3.61.0) ein YouTube-Video mit zwei Klicks (Kontext::videoStart), mit video_datei (3.62.0) ein eigenes Video (Kontext::videoDatei).
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr */
$titelId = $k->id($nr, 'titel');
$video = null;
if ($b['variante'] === 'bild-rechts' && isset($b['bild'])) {
    $video = isset($b['video_kennung']) ? $k->videoStart($b['video_kennung'], $b['titel'], $b['bild'], true)
        : (isset($b['video_datei']) ? $k->videoDatei($b['video_datei'], $b['titel'], $b['bild']) : null);
}
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
<?php if ($video !== null): ?>
<div class="lp-hero-medien">
<?= $video ?>

</div>
<?php elseif ($b['variante'] !== 'schlicht' && isset($b['bild'])): ?>
<?= $k->bild($b['bild'], 'lp-hero-bild', true) ?>

<?php endif ?>
</div>
</section>
