<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Kopfbereich: einzige H1 der Seite, optional Bild (rechts oder dahinter) und Knopf. Bei bild-rechts wird das Bild zum Startbild eines Videos:
 * mit video_kennung (3.61.0) ein YouTube-Video mit zwei Klicks (Kontext::videoStart), mit video_datei (3.62.0) ein eigenes Video (Kontext::videoDatei);
 * mit video_ende_knopf (3.75.0) liegt nach dem Ende des eigenen Videos der Knopf des Kopfbereichs über dem verschwommenen letzten Bild.
 * Mit schnellwahl (3.76.0) stehen unter der Überschrift bis zu drei Knöpfe (nav.lp-schnellwahl), jeder nur, wenn sein Ziel auf der Seite ist:
 * A zur Anmeldung (ersetzt den Knopf), B Zoom ohne E-Mail (nur Online-Termin + Anmeldung; ein verborgener Platz — lp.js holt den Link 15 Minuten vor Beginn
 * vom Panel, im HTML steht er nie), C zum Erfolgsbericht (Erfahrungen, sonst Video). data-lp-knopf meldet den Klick an die Statistik.
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr */
$titelId = $k->id($nr, 'titel');
$wahlA = $wahlC = null;
$wahlB = false;
if (($b['schnellwahl'] ?? false) === true) {
    $wahlA = $k->sprungziel(['anmeldung']);
    $wahlB = $wahlA !== null && $k->hat(['termin'], static fn (array $t): bool => ($t['art'] ?? 'online') === 'online') !== null;
    $wahlC = $k->sprungziel(['erfahrungen', 'video']);
}
$video = null;
if ($b['variante'] === 'bild-rechts' && isset($b['bild'])) {
    $video = isset($b['video_kennung']) ? $k->videoStart($b['video_kennung'], $b['titel'], $b['bild'], true, 'halb')
        : (isset($b['video_datei']) ? $k->videoDatei($b['video_datei'], $b['titel'], $b['bild'], ($b['video_ende_knopf'] ?? false) === true ? $b['knopf'] : null) : null);
}
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<div class="lp-hero-text">
<?php if (($b['dachzeile'] ?? '') !== ''): ?>
<p class="lp-hero-dachzeile"<?= $k->feld($nr, 'dachzeile') ?>><?= $k->e($b['dachzeile']) ?></p>
<?php endif ?>
<h1 class="lp-hero-titel" id="<?= $titelId ?>"<?= $k->groesse($b['titel']) ?><?= $k->feld($nr, 'titel') ?>><?= $k->e($b['titel']) ?></h1>
<?php if ($wahlA !== null || $wahlC !== null): ?>
<nav class="lp-schnellwahl" aria-label="Schnellwahl">
<?php if ($wahlA !== null): ?>
<a class="btn btn-primary lp-knopf lp-schnellwahl-knopf" href="<?= $k->e($wahlA) ?>" data-lp-knopf="anmelden">Für den nächsten Zoom-Vortrag anmelden</a>
<?php endif ?>
<?php if ($wahlB): ?>
<div class="lp-schnellwahl-zoom" data-lp-zoom data-lp-zoom-text="Jetzt ohne E-Mail beim Zoom-Vortrag dabei sein" hidden><p class="lp-schnellwahl-wartet" aria-live="polite"></p></div>
<?php endif ?>
<?php if ($wahlC !== null): ?>
<a class="btn btn-outline lp-knopf lp-schnellwahl-knopf" href="<?= $k->e($wahlC) ?>" data-lp-knopf="bericht">Echten Erfolgsbericht ansehen (ohne E-Mail)</a>
<?php endif ?>
</nav>
<?php endif ?>
<?= $k->absaetze($b['text'] ?? '', feld: $k->feld($nr, 'text')) ?>

<?= isset($b['knopf']) && $wahlA === null ? $k->knopf($b['knopf'], $k->feld($nr, 'knopf.text')) : '' ?>

</div>
<?php if ($video !== null): ?>
<div class="lp-hero-medien">
<?= $video ?>

</div>
<?php elseif ($b['variante'] !== 'schlicht' && isset($b['bild'])): ?>
<?= $k->bild($b['bild'], 'lp-hero-bild', true, false, $b['variante'] === 'bild-hinten' ? 'voll' : 'halb') ?>

<?php endif ?>
</div>
</section>
