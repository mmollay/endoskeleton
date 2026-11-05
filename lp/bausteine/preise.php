<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Preise und Leistungen (3.71.0): Angebote als Karten oder Liste — Name, Betrag (freier Text: „ab 49 €“, „auf Anfrage“), Hinweis zum Betrag,
 * Beschreibung, Leistungen und Knopf; „hervorgehoben“ hebt ein Angebot hervor (Rahmen, nicht nur Farbe).
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?php if (trim((string) ($b['text'] ?? '')) !== ''): ?>
<div class="lp-preise-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<ul class="lp-preise-liste" role="list">
<?php foreach ($b['angebote'] as $a): $klasse = ($a['hervorgehoben'] ?? false) === true ? 'lp-angebot lp-angebot--hervorgehoben' : 'lp-angebot'; ?>
<li class="<?= $klasse ?>">
<h3 class="lp-angebot-name"><?= $k->e($a['name']) ?></h3>
<p class="lp-angebot-betrag"><?= $k->e($a['betrag']) ?></p>
<?php if (trim((string) ($a['hinweis'] ?? '')) !== ''): ?>
<p class="lp-angebot-hinweis"><?= $k->e($a['hinweis']) ?></p>
<?php endif ?>
<?php if (trim((string) ($a['text'] ?? '')) !== ''): ?>
<div class="lp-angebot-text">
<?= $k->absaetze($a['text']) ?>

</div>
<?php endif ?>
<?php if (($a['punkte'] ?? []) !== []): ?>
<ul class="lp-angebot-punkte">
<?php foreach ($a['punkte'] as $punkt): ?>
<li><?= $k->inline($punkt) ?></li>
<?php endforeach ?>
</ul>
<?php endif ?>
<?= isset($a['knopf']) ? $k->knopf($a['knopf']) . "\n" : '' ?>
</li>
<?php endforeach ?>
</ul>
</div>
</section>
