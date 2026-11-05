<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Referenzen (3.71.0): Arbeiten mit Bild (optional), Titel, Text und Auftraggeber — als Raster oder als waagrechte Leiste mit CSS-Scroll-Snap,
 * ohne Skript und ohne Bibliothek. Die Leiste ist ein beschrifteter Bereich mit tabindex="0": mit der Tastatur erreichbar und mit den
 * Pfeiltasten scrollbar. @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
$leiste = $b['variante'] === 'leiste';
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?php if (trim((string) ($b['text'] ?? '')) !== ''): ?>
<div class="lp-referenzen-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<div class="lp-referenzen-rahmen"<?= $leiste ? ' tabindex="0" role="region" aria-label="' . $k->e($b['titel'] . ' (waagrecht scrollbar)') . '"' : '' ?>>
<ul class="lp-referenzen-liste" role="list">
<?php foreach ($b['eintraege'] as $r): ?>
<li class="lp-referenz">
<?php if (isset($r['bild'])): ?>
<?= $k->bild($r['bild'], 'lp-referenz-bild', false, false, 'karte') ?>

<?php endif ?>
<h3 class="lp-referenz-titel"><?= $k->e($r['titel']) ?></h3>
<?php if (trim((string) ($r['text'] ?? '')) !== ''): ?>
<div class="lp-referenz-text">
<?= $k->absaetze($r['text']) ?>

</div>
<?php endif ?>
<?php if (trim((string) ($r['name'] ?? '')) !== ''): ?>
<p class="lp-referenz-name"><?= $k->e($r['name']) ?></p>
<?php endif ?>
</li>
<?php endforeach ?>
</ul>
</div>
</div>
</section>
