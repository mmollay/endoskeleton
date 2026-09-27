<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Erfahrungen: Zitate mit Name, Ort und Quelle (Pflicht). @var array $b @var Endo\Lp\Kontext $k @var int $nr */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<ul class="lp-erfahrungen-liste" role="list">
<?php foreach ($b['eintraege'] as $e): $wer = $e['name'] . (($e['ort'] ?? '') !== '' ? ', ' . $e['ort'] : ''); ?>
<li>
<figure class="lp-erfahrung">
<blockquote class="lp-erfahrung-text">
<?= $k->absaetze($e['text']) ?>

</blockquote>
<figcaption class="lp-erfahrung-name"><?= $k->e($wer) ?> · <a class="lp-erfahrung-quelle" href="<?= $k->e($e['quelle']) ?>" aria-label="<?= $k->e('Originalbericht von ' . $e['name']) ?>">Originalbericht</a></figcaption>
</figure>
</li>
<?php endforeach ?>
</ul>
</div>
</section>
