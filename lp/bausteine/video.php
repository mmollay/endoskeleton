<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Video mit zwei Klicks: bis zum Klick nur ein lokales Vorschaubild, keine Anfrage an den Anbieter (Kontext::videoStart).
 * lp.js setzt das iframe erst nach dem Klick ein; ohne JavaScript bleibt der Link. @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
$titel = (string) $b['titel'];
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $titel) ?>

<?php if ($b['variante'] === 'mit-text'): ?>
<div class="lp-video-text">
<?= $k->absaetze($b['text'] ?? '') ?>

</div>
<?php endif ?>
<?= $k->videoStart($b['kennung'], $titel, $b['vorschau']) ?>

</div>
</section>
