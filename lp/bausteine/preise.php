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
<?= $k->absaetze($b['text'], feld: $k->feld($nr, 'text')) ?>

</div>
<?php endif ?>
<ul class="lp-preise-liste" role="list">
<?php foreach ($b['angebote'] as $i => $a): $klasse = ($a['hervorgehoben'] ?? false) === true ? 'lp-angebot lp-angebot--hervorgehoben' : 'lp-angebot'; ?>
<li class="<?= $klasse ?>">
<h3 class="lp-angebot-name"<?= $k->feld($nr, "angebote.$i.name") ?>><?= $k->e($a['name']) ?></h3>
<p class="lp-angebot-betrag"<?= $k->feld($nr, "angebote.$i.betrag") ?>><?= $k->e($a['betrag']) ?></p>
<?php if (trim((string) ($a['hinweis'] ?? '')) !== ''): ?>
<p class="lp-angebot-hinweis"<?= $k->feld($nr, "angebote.$i.hinweis") ?>><?= $k->e($a['hinweis']) ?></p>
<?php endif ?>
<?php if (trim((string) ($a['text'] ?? '')) !== ''): ?>
<div class="lp-angebot-text">
<?= $k->absaetze($a['text'], feld: $k->feld($nr, "angebote.$i.text")) ?>

</div>
<?php endif ?>
<?php if (($a['punkte'] ?? []) !== []): ?>
<ul class="lp-angebot-punkte">
<?php foreach ($a['punkte'] as $j => $punkt): ?>
<li<?= $k->feld($nr, "angebote.$i.punkte.$j") ?>><?= $k->inline($punkt) ?></li>
<?php endforeach ?>
</ul>
<?php endif ?>
<?= isset($a['knopf']) ? $k->knopf($a['knopf'], $k->feld($nr, "angebote.$i.knopf.text")) . "\n" : '' ?>
</li>
<?php endforeach ?>
</ul>
</div>
</section>
