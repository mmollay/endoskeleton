<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Häufige Fragen: natives <details>/<summary> (per Tastatur bedienbar, ohne JavaScript), JSON-LD FAQPage. @var array $b @var Endo\Lp\Kontext $k @var int $nr */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<div class="lp-faq-liste">
<?php foreach ($b['fragen'] as $f): ?>
<details class="lp-faq-frage">
<summary><?= $k->e($f['frage']) ?></summary>
<div class="lp-faq-antwort">
<?= $k->absaetze($f['antwort']) ?>

</div>
</details>
<?php endforeach ?>
</div>
</div>
<?= $k->jsonLd(['@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $f): array => [
    '@type' => 'Question', 'name' => $f['frage'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['antwort']],
], $b['fragen'])]) ?>

</section>
