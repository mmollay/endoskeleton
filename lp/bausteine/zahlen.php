<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Zahlenband (3.71.0): zwei bis sechs Zahlen mit Beschriftung, als Band auf der Akzentfläche oder schlicht. Die Zahl ist Text, so wie sie
 * im Bauplan steht („1.500“, „4,8“, „98 %“) — kein Skript zählt hoch (ein Zähler kann später als eigenes Feld dazukommen).
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<ul class="lp-zahlen-liste" role="list">
<?php foreach ($b['eintraege'] as $z): ?>
<li class="lp-zahl">
<p class="lp-zahl-wert"><?= $k->e($z['wert']) ?></p>
<p class="lp-zahl-beschriftung"><?= $k->e($z['beschriftung']) ?></p>
</li>
<?php endforeach ?>
</ul>
</div>
</section>
