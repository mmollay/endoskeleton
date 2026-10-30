<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Bewegung (3.64.0): eine aufgezeichnete Bewegung als Strichfigur oder Punkte — ohne Kamerabild, ohne Anfrage an Dritte (Kontext::bewegungsDatei,
 * lp/bewegung.js). Variante „wiedergabe“ spielt die Datei nur ab; „live“ bietet zusätzlich die eigene Kamera an: erst nach dem Klick, das Bild
 * bleibt auf dem Gerät (lp/bewegung-live.js wird erst dann geladen). Ohne JavaScript bleibt die Beschriftung; Steuerung und Kamera-Block sind
 * bis dahin verborgen (hidden). @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
$live = $b['variante'] === 'live';
$d = $k->bewegungsDatei($b['bewegung'], $live);
$beschriftung = $k->id($nr, 'beschriftung');
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, (string) $b['titel']) ?>

<?php if (!empty($b['text'])): ?>
<div class="lp-bewegung-text">
<?= $k->absaetze($b['text']) ?>

</div>
<?php endif ?>
<figure class="lp-bewegung-figur" data-lp-bewegung="<?= $k->e($b['bewegung']) ?>" data-lp-stil="<?= $k->e($b['stil'] ?? 'strich') ?>" data-lp-modus="<?= $k->e($b['variante']) ?>">
<canvas width="<?= $d['breite'] ?>" height="<?= $d['hoehe'] ?>" role="img" tabindex="0" aria-labelledby="<?= $beschriftung ?>"></canvas>
<figcaption class="lp-bewegung-beschriftung" id="<?= $beschriftung ?>"><?= $k->e($b['alt']) ?></figcaption>
</figure>
<div class="lp-bewegung-leiste" data-lp-b="steuerung" role="group" aria-label="Steuerung der Bewegung" hidden>
<button type="button" class="btn btn-secondary" data-lp-b="pause" data-an="Pause" data-aus="Abspielen">Pause</button>
<button type="button" class="btn btn-outline" data-lp-b="spiegeln" aria-pressed="false">Spiegeln</button>
<label class="lp-bewegung-wahl">Tempo
<select data-lp-b="tempo">
<option value="0.5">halb</option>
<option value="1" selected>normal</option>
<option value="2">doppelt</option>
</select>
</label>
</div>
<?php if ($live): ?>
<div class="lp-bewegung-kamera" hidden>
<p class="lp-bewegung-hinweis"><?= $k->du('Das Kamerabild bleibt auf deinem Gerät und wird nicht gespeichert oder übertragen. Die Kamera schaltet sich erst nach deinem Klick ein; die Körperpunkte einer Aufnahme bleiben im Arbeitsspeicher, bis du sie selbst als Datei sicherst.',
    'Das Kamerabild bleibt auf Ihrem Gerät und wird nicht gespeichert oder übertragen. Die Kamera schaltet sich erst nach Ihrem Klick ein; die Körperpunkte einer Aufnahme bleiben im Arbeitsspeicher, bis Sie sie selbst als Datei sichern.') ?></p>
<div class="lp-bewegung-leiste" role="group" aria-label="Kamera und Aufnahme">
<button type="button" class="btn btn-primary" data-lp-b="kamera" data-an="Kamera ausschalten" data-aus="<?= $k->e($b['knopf_text'] ?? 'Kamera starten') ?>"><?= $k->e($b['knopf_text'] ?? 'Kamera starten') ?></button>
<label class="lp-bewegung-wahl" hidden>Darstellung
<select data-lp-b="bild">
<option value="kamera">Skelett über dem Kamerabild</option>
<option value="dunkel">Skelett auf dunklem Grund</option>
</select>
</label>
<button type="button" class="btn btn-secondary" data-lp-b="aufnehmen" data-an="Stopp" data-aus="Aufnehmen" hidden>Aufnehmen</button>
<button type="button" class="btn btn-outline" data-lp-b="speichern" hidden>Als Datei speichern</button>
</div>
<p class="lp-bewegung-meldung" data-lp-t="laedt" role="status" hidden>Die Bewegungserkennung wird geladen – das kann einen Moment dauern.</p>
<p class="lp-bewegung-meldung" data-lp-t="aufnahme" role="status" hidden>Die Aufnahme läuft. Zum Beenden auf „Stopp“ drücken (nach spätestens zehn Sekunden endet sie von selbst).</p>
<p class="lp-bewegung-meldung" data-lp-t="fertig" role="status" hidden><?= $k->du('Die Aufnahme ist fertig. Du siehst sie oben als Vorschau und kannst sie als Datei speichern.', 'Die Aufnahme ist fertig. Sie sehen sie oben als Vorschau und können sie als Datei speichern.') ?></p>
<p class="lp-bewegung-meldung" data-lp-t="keinkamera" role="status" hidden><?= $k->du('Die Kamera oder die Bewegungserkennung ist hier nicht verfügbar oder nicht freigegeben. Du siehst stattdessen die Beispielbewegung.', 'Die Kamera oder die Bewegungserkennung ist hier nicht verfügbar oder nicht freigegeben. Sie sehen stattdessen die Beispielbewegung.') ?></p>
<p class="lp-bewegung-meldung" data-lp-t="leer" role="status" hidden><?= $k->du('Es wurde keine Person erkannt. Stell dich ganz ins Bild und versuche es noch einmal.', 'Es wurde keine Person erkannt. Bitte stellen Sie sich ganz ins Bild und versuchen Sie es noch einmal.') ?></p>
</div>
<?php endif ?>
<p class="lp-bewegung-meldung" data-lp-t="fehler" role="status" hidden>Die Bewegung konnte nicht geladen werden.</p>
</div>
</section>
