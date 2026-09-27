<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Anmeldung: Felder mit Label, autocomplete und Fehlertext; Einwilligung aus dem Profil, nie vorangehakt; Honigtopf;
 * Liste und Kurzprüfsumme des Einwilligungstexts als versteckte Felder. Ohne Formularziel sichtbar gesperrt
 * (Livegang gesperrt, bis die Anbindung steht). @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
$p = $k->profil->daten;
$ziel = (string) $p['formularziel'];
$felder = [
    'vorname'  => ['Vorname', 'text', 'given-name', true, $k->du('Bitte gib deinen Vornamen ein.', 'Bitte geben Sie Ihren Vornamen ein.')],
    'nachname' => ['Nachname', 'text', 'family-name', true, $k->du('Bitte gib deinen Nachnamen ein.', 'Bitte geben Sie Ihren Nachnamen ein.')],
    'email'    => ['E-Mail-Adresse', 'email', 'email', true, $k->du('Bitte gib deine E-Mail-Adresse ein.', 'Bitte geben Sie Ihre E-Mail-Adresse ein.')],
    'telefon'  => ['Telefon (freiwillig)', 'tel', 'tel', false, ''],
];
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?= $k->absaetze($b['text'] ?? '') ?>

<form class="lp-formular" method="post"<?= $ziel !== '' ? ' action="' . $k->e($ziel) . '"' : '' ?> novalidate>
<?php if ($ziel === ''): ?>
<p class="lp-anmeldung-hinweis" role="note">Die Anmeldung wird gerade eingerichtet und ist in Kürze möglich.</p>
<?php endif ?>
<fieldset aria-labelledby="<?= $k->id($nr, 'titel') ?>"<?= $ziel === '' ? ' disabled' : '' ?>>
<?php foreach ($felder as $name => [$label, $typ, $auto, $pflicht, $fehler]): if (!in_array($name, $b['felder'], true)) { continue; } $id = "lp-f-$name"; ?>
<div class="lp-feld">
<label for="<?= $id ?>"><?= $k->e($label) ?></label>
<input id="<?= $id ?>" name="<?= $name ?>" type="<?= $typ ?>" autocomplete="<?= $auto ?>"<?= $pflicht ? ' required aria-describedby="' . $id . '-fehler"' : '' ?>>
<?php if ($pflicht): ?>
<p class="lp-feld-fehler" id="<?= $id ?>-fehler" hidden><?= $k->e($fehler) ?></p>
<?php endif ?>
</div>
<?php endforeach ?>
<div class="lp-feld lp-einwilligung">
<input id="lp-f-einwilligung" name="einwilligung" type="checkbox" value="ja" required aria-describedby="lp-f-einwilligung-fehler">
<label for="lp-f-einwilligung"><?= $k->e($p['einwilligung']) ?> Mehr dazu in der <a href="datenschutz.html">Datenschutzerklärung</a>.</label>
<p class="lp-feld-fehler" id="lp-f-einwilligung-fehler" hidden><?= $k->e($k->du('Bitte bestätige die Einwilligung.', 'Bitte bestätigen Sie die Einwilligung.')) ?></p>
</div>
<div class="form-hp" aria-hidden="true">
<label for="lp-f-website">Website</label>
<input id="lp-f-website" name="website" type="text" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="liste" value="<?= $k->e($b['liste']) ?>">
<input type="hidden" name="einwilligung_kurz" value="<?= substr(hash('sha256', (string) $p['einwilligung']), 0, 12) ?>">
<button type="submit" class="btn btn-primary lp-knopf"><?= $k->e($b['knopf_text']) ?></button>
</fieldset>
<p class="lp-anmeldung-danke" role="status" hidden><?= $k->e($b['danke']) ?></p>
</form>
</div>
</section>
