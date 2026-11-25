<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Kopfbereich: einzige H1 der Seite, optional Bild (rechts oder dahinter) und Knopf. Bei bild-rechts wird das Bild zum Startbild eines Videos:
 * mit video_kennung (3.61.0) ein YouTube-Video mit zwei Klicks (Kontext::videoStart), mit video_datei (3.62.0) ein eigenes Video (Kontext::videoDatei);
 * mit video_ende_knopf (3.75.0) liegt nach dem Ende des eigenen Videos der Knopf des Kopfbereichs über dem verschwommenen letzten Bild.
 * Mit schnellwahl (3.76.0; 3.77.0 nach Alberts Zahlen: 95 % Handy, 89 % unter 30 s) stehen direkt unter der Überschrift bis zu drei Knöpfe
 * (nav.lp-schnellwahl), jeder nur, wenn sein Ziel da ist: 1 zur Anmeldung (ersetzt den Knopf), 2 zu den Erfolgsberichten — bericht_ziel (https, im selben
 * Fenster, damit die Zurück-Taste im Facebook-Browser zurückführt; je utm_campaign aus bericht_kampagnen, lp.js tauscht die Adresse), sonst der Sprung zu
 * Erfahrungen oder Video — und 3 zur Zoom-Aufzeichnung: aufzeichnung_kennung + aufzeichnung_bild ergeben unter dem Text ein YouTube-Video mit zwei Klicks
 * (Kontext::videoStart: lokales Startbild und Hinweis, keine Verbindung zu Google vor dem Tipp) und darunter einen zweiten Anmeldeknopf.
 * Der Knopf „Zoom ohne E-Mail“ (3.76.0) ist in 3.77.0 entfallen. data-lp-knopf meldet den Klick an die Statistik (anmelden|bericht|aufzeichnung).
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr */
$titelId = $k->id($nr, 'titel');
$wahlA = $wahlB = $wahlC = null;
$zieleB = [];
if (($b['schnellwahl'] ?? false) === true) {
    $wahlA = $k->sprungziel(['anmeldung']);
    $wahlB = $b['bericht_ziel'] ?? $k->sprungziel(['erfahrungen', 'video']);
    foreach ($b['bericht_kampagnen'] ?? [] as $z) {
        $zieleB[$z['kampagne']] = $z['ziel'];
    }
    $wahlC = isset($b['aufzeichnung_kennung'], $b['aufzeichnung_bild']) ? '#' . $k->id($nr, 'aufzeichnung') : null;
}
$textA = 'Jetzt zum nächsten Live-Zoom anmelden';
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
<?php if ($wahlA !== null || $wahlB !== null || $wahlC !== null): ?>
<nav class="lp-schnellwahl" aria-label="Schnellwahl">
<?php if ($wahlA !== null): ?>
<a class="btn btn-primary lp-knopf lp-schnellwahl-knopf" href="<?= $k->e($wahlA) ?>" data-lp-knopf="anmelden"><?= $textA ?></a>
<?php endif ?>
<?php if ($wahlB !== null): ?>
<a class="btn btn-outline lp-knopf lp-schnellwahl-knopf" href="<?= $k->e($wahlB) ?>" data-lp-knopf="bericht"<?= $zieleB !== [] ? ' data-lp-ziele="' . $k->e((string) json_encode($zieleB, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>Echte Erfolgsberichte ansehen</a>
<?php endif ?>
<?php if ($wahlC !== null): ?>
<a class="btn btn-outline lp-knopf lp-schnellwahl-knopf" href="<?= $k->e($wahlC) ?>" data-lp-knopf="aufzeichnung">Zoom-Aufzeichnung sofort ansehen</a>
<?php endif ?>
</nav>
<?php endif ?>
<?= $k->absaetze($b['text'] ?? '', feld: $k->feld($nr, 'text')) ?>

<?= isset($b['knopf']) && $wahlA === null ? $k->knopf($b['knopf'], $k->feld($nr, 'knopf.text')) : '' ?>
<?php if ($wahlC !== null): ?>
<div class="lp-aufzeichnung" id="<?= $k->id($nr, 'aufzeichnung') ?>">
<p class="lp-aufzeichnung-titel">Zoom-Aufzeichnung</p>
<?= $k->videoStart($b['aufzeichnung_kennung'], 'Zoom-Aufzeichnung: ' . $b['titel'], $b['aufzeichnung_bild'], false, 'halb') ?>
<?php if ($wahlA !== null): ?>
<a class="btn btn-primary lp-knopf" href="<?= $k->e($wahlA) ?>"><?= $textA ?></a>
<?php endif ?>
</div>
<?php endif ?>

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
