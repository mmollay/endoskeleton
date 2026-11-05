<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Team (3.71.0): Personen als Karten oder Liste — Bild (optional), Name, Rolle, Text. Für eine einzelne vortragende Person gibt es weiter
 * den Baustein „person“. @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?php if (trim((string) ($b['text'] ?? '')) !== ''): ?>
<div class="lp-team-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<ul class="lp-team-liste" role="list">
<?php foreach ($b['personen'] as $p): ?>
<li class="lp-mitglied">
<?php if (isset($p['bild'])): ?>
<?= $k->bild($p['bild'], 'lp-mitglied-bild', false, false, 'klein') ?>

<?php endif ?>
<h3 class="lp-mitglied-name"><?= $k->e($p['name']) ?></h3>
<?php if (trim((string) ($p['rolle'] ?? '')) !== ''): ?>
<p class="lp-mitglied-rolle"><?= $k->e($p['rolle']) ?></p>
<?php endif ?>
<?php if (trim((string) ($p['text'] ?? '')) !== ''): ?>
<div class="lp-mitglied-text">
<?= $k->absaetze($p['text']) ?>

</div>
<?php endif ?>
</li>
<?php endforeach ?>
</ul>
</div>
</section>
