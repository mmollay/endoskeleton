<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Vorher und Nachher (3.71.0): Bildpaare nebeneinander (auf dem Handy untereinander), jedes Bild sichtbar mit „Vorher“ bzw. „Nachher“
 * beschriftet (nicht nur über die Lage), dazu eine Beschriftung des Paars. Ohne Skript; ein Schieberegler kann später als eigene Variante kommen.
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?php if (trim((string) ($b['text'] ?? '')) !== ''): ?>
<div class="lp-vergleich-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<ul class="lp-vergleich-liste" role="list">
<?php foreach ($b['paare'] as $paar): ?>
<li>
<figure class="lp-paar">
<div class="lp-paar-bilder">
<div>
<p class="lp-paar-marke">Vorher</p>
<?= $k->bild($paar['vorher'], 'lp-paar-bild', false, false, 'halb') ?>

</div>
<div>
<p class="lp-paar-marke">Nachher</p>
<?= $k->bild($paar['nachher'], 'lp-paar-bild', false, false, 'halb') ?>

</div>
</div>
<?php if (trim((string) ($paar['beschriftung'] ?? '')) !== ''): ?>
<figcaption class="lp-paar-beschriftung"><?= $k->e($paar['beschriftung']) ?></figcaption>
<?php endif ?>
</figure>
</li>
<?php endforeach ?>
</ul>
</div>
</section>
