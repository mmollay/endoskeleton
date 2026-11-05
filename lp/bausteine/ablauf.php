<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Ablauf (3.71.0): nummerierte Schritte (<ol>) oder eine Zeitleiste — je Schritt Titel, Zeitangabe („am Vortag“, „Woche 1“) und Text.
 * Die Nummer schreibt das CSS (counter), der Text bleibt so, wie er im Bauplan steht.
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?php if (trim((string) ($b['text'] ?? '')) !== ''): ?>
<div class="lp-ablauf-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<ol class="lp-ablauf-liste">
<?php foreach ($b['schritte'] as $s): ?>
<li class="lp-schritt">
<?php if (trim((string) ($s['zeit'] ?? '')) !== ''): ?>
<p class="lp-schritt-zeit"><?= $k->e($s['zeit']) ?></p>
<?php endif ?>
<h3 class="lp-schritt-titel"><?= $k->e($s['titel']) ?></h3>
<?php if (trim((string) ($s['text'] ?? '')) !== ''): ?>
<div class="lp-schritt-text">
<?= $k->absaetze($s['text']) ?>

</div>
<?php endif ?>
</li>
<?php endforeach ?>
</ol>
</div>
</section>
