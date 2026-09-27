<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Abschluss: letzter Aufruf mit Knopf, auf Akzentfläche oder schlicht. @var array $b @var Endo\Lp\Kontext $k @var int $nr */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?php if (($b['text'] ?? '') !== ''): ?>
<div class="lp-abschluss-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<?= $k->knopf($b['knopf']) ?>

</div>
</section>
