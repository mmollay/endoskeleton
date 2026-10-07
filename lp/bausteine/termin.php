<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Termin: alle Termine werden gebaut (deterministisch); lp.js blendet vergangene zur Laufzeit aus und zeigt dann
 * „wenn_leer“. Je Termin ein JSON-LD Event. Der Preis (3.52.0) steht unter dem Ort: ohne Angabe gar nichts (keine Zeile, kein Preis im JSON-LD), bei ausdrücklich 0 „Teilnahme kostenlos“ (isAccessibleForFree), sonst der Betrag in Euro.
 * @var array $b @var Endo\Lp\Kontext $k @var int $nr
 */
$online = ($b['art'] ?? 'online') === 'online';
$firma = (string) $k->profil->daten['firma']['name'];
$preis = isset($b['preis']) ? (int) $b['preis'] : null; // null = keine Angabe, 0 = ausdrücklich kostenlos
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<ul class="lp-termin-liste" role="list">
<?php foreach ($b['termine'] as $t): $dauer = (int) ($t['dauer_min'] ?? 0); ?>
<li class="lp-termin-eintrag" data-lp-ende="<?= $k->iso($t['beginn'], $dauer) ?>"><time class="lp-termin-zeit" datetime="<?= $k->iso($t['beginn']) ?>"><?= $k->e($k->datum($t['beginn'])) ?></time><?= $dauer > 0 ? ' · etwa ' . $dauer . ' Minuten' : '' ?></li>
<?php endforeach ?>
</ul>
<p class="lp-termin-ort"><?= $k->e($b['ort']) ?></p>
<?= $preis === null ? '' : '<p class="lp-termin-preis">' . ($preis === 0 ? 'Teilnahme kostenlos' : 'Teilnahme: ' . $preis . ' €') . "</p>\n" ?>
<p class="lp-termin-leer" hidden><?= $k->e($b['wenn_leer']) ?></p>
<?= isset($b['knopf']) ? $k->knopf($b['knopf']) . "\n" : '' ?>
</div>
<?php foreach ($b['termine'] as $t): $dauer = (int) ($t['dauer_min'] ?? 0); ?>
<?= $k->jsonLd(array_filter([
    '@type' => 'Event',
    'name' => $k->meta['titel'],
    'description' => $k->meta['beschreibung'],
    'startDate' => $k->iso($t['beginn']),
    'endDate' => $dauer > 0 ? $k->iso($t['beginn'], $dauer) : null,
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => $online ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
    'isAccessibleForFree' => $preis === null ? null : $preis === 0,
    'offers' => $preis ? ['@type' => 'Offer', 'price' => $preis, 'priceCurrency' => 'EUR'] : null,
    'location' => $online ? ['@type' => 'VirtualLocation', 'name' => $b['ort']] : ['@type' => 'Place', 'name' => $b['ort'], 'address' => $b['ort']],
    'organizer' => ['@type' => 'Organization', 'name' => $firma],
], static fn ($v) => $v !== null)) ?>

<?php endforeach ?>
</section>
