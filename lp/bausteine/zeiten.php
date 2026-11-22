<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Öffnungszeiten (3.71.0): Tage und Zeiten als Beschreibungsliste (<dl>), als Tabelle oder Karte, dazu ein Hinweis. Tage und Zeiten sind
 * freier Text („Montag bis Freitag“, „9:00 bis 18:00 Uhr“, „geschlossen“). @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<dl class="lp-zeiten-liste">
<?php foreach ($b['zeilen'] as $i => $z): ?>
<div>
<dt class="lp-zeiten-tage"<?= $k->feld($nr, "zeilen.$i.tage") ?>><?= $k->e($z['tage']) ?></dt>
<dd class="lp-zeiten-zeit"<?= $k->feld($nr, "zeilen.$i.zeit") ?>><?= $k->e($z['zeit']) ?></dd>
</div>
<?php endforeach ?>
</dl>
<?php if (trim((string) ($b['hinweis'] ?? '')) !== ''): ?>
<div class="lp-zeiten-hinweis">
<?= $k->absaetze($b['hinweis'], feld: $k->feld($nr, 'hinweis')) ?>

</div>
<?php endif ?>
</div>
</section>
