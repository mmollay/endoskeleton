<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Themen: Liste mit Häkchen, einspaltig oder zweispaltig. @var array $b @var Endo\Lp\Kontext $k @var int $nr */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?= $k->absaetze($b['text'] ?? '') ?>

<ul class="lp-themen-liste" role="list">
<?php foreach ($b['punkte'] as $punkt): ?>
<li class="lp-themen-punkt"><?= $k->inline($punkt) ?></li>
<?php endforeach ?>
</ul>
</div>
</section>
