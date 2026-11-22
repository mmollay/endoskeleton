<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Hinweis-Kasten (3.72.0): ein ruhig abgesetzter Kasten mit kurzer Kennzeichnung („Wichtiger Hinweis“) über der Überschrift und einem Text —
 * etwa „Keine Heilversprechen“. Kein Alarmrot: Rahmen in der Hauptfarbe, Fläche der Karte. Ohne Text wird nichts gebaut (wie die Person) — so
 * kann der Kasten in einer Vorlage stehen. Mit `vor_anmeldung` setzt der Bau (Bauer::folge) ihn direkt vor den Abschnitt Anmeldung.
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
if (trim((string) ($b['text'] ?? '')) === '') {
    return;
}
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<div class="lp-hinweis-kasten">
<?php if (trim((string) ($b['kennzeichnung'] ?? '')) !== ''): ?>
<p class="lp-hinweis-kennzeichnung"><?= $k->e($b['kennzeichnung']) ?></p>
<?php endif ?>
<?= $k->titel($nr, $b['titel']) ?>

<div class="lp-hinweis-text">
<?= $k->absaetze($b['text']) ?>

</div>
</div>
</div>
</section>
