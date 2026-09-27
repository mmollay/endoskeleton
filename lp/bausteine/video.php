<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Video mit zwei Klicks: bis zum Klick nur ein lokales Vorschaubild, keine Anfrage an den Anbieter. lp.js setzt das
 * iframe (youtube-nocookie) erst nach dem Klick ein; ohne JavaScript bleibt der Link. @var array $b @var Endo\Lp\Kontext $k @var int $nr
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
<div class="lp-video-rahmen">
<button type="button" class="lp-video-start" data-lp-kennung="<?= $k->e($b['kennung']) ?>" data-lp-titel="<?= $k->e($titel) ?>" aria-label="<?= $k->e('Video abspielen: ' . $titel . ' (lädt Inhalte von YouTube)') ?>">
<?= $k->bild($b['vorschau'], 'lp-video-bild', false, true) ?>

</button>
</div>
<p class="lp-video-link"><?= $k->du('Mit dem Klick auf das Bild lädst du das Video von YouTube. Du kannst es auch', 'Mit dem Klick auf das Bild laden Sie das Video von YouTube. Sie können es auch') ?> <a href="https://www.youtube.com/watch?v=<?= $k->e($b['kennung']) ?>">auf YouTube ansehen</a>.</p>
</div>
</section>
