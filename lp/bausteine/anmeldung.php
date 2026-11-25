<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Anmeldung: Felder mit Label, autocomplete und Fehlertext (als Beschreibung erst nach dem Absenden, lp.js); Einwilligung aus dem Profil, nie vorangehakt; Honigtopf;
 * Liste und Kurzprüfsumme des Einwilligungstexts als versteckte Felder. Ohne Formularziel sichtbar gesperrt
 * (Livegang gesperrt, bis die Anbindung steht). Rückmeldungen der Weiterleitung (?anmeldung=ok|fehler|voll, bei
 * abgelaufener Anmeldefrist ?anmeldung=fehler&grund=geschlossen) liegen versteckt bereit; lp.js zeigt und fokussiert die passende. Plätze (3.52.0): Mit `plaetze` und Formularziel steht
 * „Begrenzt auf N Plätze.“ im Formular; lp.js fragt den Stand am Host des Formularziels ab (/lp/plaetze). Eine eigene
 * Meldung „bereits angemeldet“ gibt es nicht (Datenschutz: die Seite verrät nicht, ob eine Adresse schon auf der Liste
 * steht) — der feste Zusatzsatz nach dem Dankestext deckt diesen Fall mit ab. Ohne JavaScript bleibt es beim Formular,
 * ein <noscript>-Hinweis erklärt den Ablauf per E-Mail. @var array $b @var Endo\Lp\Kontext $k @var int $nr
 *
 * Zwei Häkchen (3.55.0, Martin 20.10.2026; Kopplungsverbot Art. 7 Abs. 4 DSGVO): Das Pflicht-Häkchen `einwilligung` deckt nur Zugangslink und
 * Erinnerung (Text `einwilligung` des Profils). Das freiwillige Häkchen `newsletter` (nie Pflicht, nie vorangehakt, als „freiwillig“ beschriftet) steht
 * nur, wenn der Bauplan `"newsletter": true` trägt — das setzt das Panel, wenn für die Seite eine allgemeine Newsletter-Gruppe gewählt ist (kein Häkchen
 * ohne Ziel). Sein Text kommt aus `einwilligung_newsletter` des Profils, sonst aus Profil::NEWSLETTER_STANDARD mit dem Namen der Marke; die Kurzprüfsumme
 * dieses Wortlauts geht als verstecktes Feld `newsletter_kurz` mit (eigener Nachweis mit eigenem Wortlaut im Panel). Ohne `newsletter` ist das Formular
 * Byte für Byte wie bisher — ältere Weiterleitungsstellen verstehen es weiter.
 *
 * Hinweis statt Pflicht-Häkchen (3.77.0, Martin 25.11.2026): Mit `"einwilligung_hinweis": true` (nur für Single-Opt-In-Listen, deren Angaben nur dem Vortrag
 * dienen) steht statt des Pflicht-Häkchens ein Satz mit Link zur Datenschutzerklärung (Profil::hinweisText); das Formular schickt `einwilligung=hinweis`
 * und als einwilligung_kurz die Kurzsumme dieses Satzes. Das Newsletter-Häkchen bleibt unverändert freiwillig. Seit 3.77.0 trägt jedes Formular ein leeres
 * verstecktes Feld `fbclid` (lp.js füllt es aus der Adresse; das Panel meldet damit eine Anmeldung an Meta, ohne Adresse und Namen).
 *
 * Freies Feld (3.72.0): Mit `"freifeld": true` steht vor der Einwilligung ein freiwilliges Textfeld `anliegen` (höchstens 1000 Zeichen) mit der Frage
 * `freifeld_frage` (Vorgabe „Worum geht es dir?“), beschriftet als „(freiwillig)“, mit einem Hinweis auf die Grenze über aria-describedby. Ohne
 * `freifeld` ist das Formular Byte für Byte wie bisher. Im Text `danach` wird jede Mailadresse ein mailto:-Link (Kontext::absaetze, $mails).
 */
$p = $k->profil->daten;
$ziel = (string) $p['formularziel'];
$hinweis = ($b['einwilligung_hinweis'] ?? false) === true ? Endo\Lp\Profil::hinweisText($p) : '';
$newsletterText = ($b['newsletter'] ?? false) === true ? Endo\Lp\Profil::newsletterText($p) : '';
$freifeld = ($b['freifeld'] ?? false) === true;
$frage = trim((string) ($b['freifeld_frage'] ?? '')) !== '' ? trim((string) $b['freifeld_frage']) : $k->du('Worum geht es dir?', 'Worum geht es Ihnen?');
$felder = [
    'vorname'  => ['Vorname', 'text', 'given-name', true, $k->du('Bitte gib deinen Vornamen ein.', 'Bitte geben Sie Ihren Vornamen ein.')],
    'nachname' => ['Nachname', 'text', 'family-name', true, $k->du('Bitte gib deinen Nachnamen ein.', 'Bitte geben Sie Ihren Nachnamen ein.')],
    'email'    => ['E-Mail-Adresse', 'email', 'email', true, $k->du('Bitte gib deine E-Mail-Adresse ein.', 'Bitte geben Sie Ihre E-Mail-Adresse ein.')],
    'telefon'  => ['Telefon (freiwillig)', 'tel', 'tel', false, ''],
];
?>
<?php
/** Maskierter Text, in dem „E-Mail“ nicht am Bindestrich umbricht (3.54.4: „E-“ am Zeilenende und „Mail“ in der nächsten sah aus wie ein Fehler). */
$mail = static fn (string $t): string => str_replace('E-Mail', '<span class="lp-ohne-umbruch">E-Mail</span>', $k->e($t));
?>
<?= $k->oeffnen($b, $nr) ?>

<div class="lp-block-innen container">
<?= $k->titel($nr, $b['titel']) ?>

<?= $k->absaetze($b['text'] ?? '', feld: $k->feld($nr, 'text')) ?>

<?= $k->absaetze($b['danach'] ?? '', 'lp-anmeldung-danach', true, feld: $k->feld($nr, 'danach')) ?>
<form class="lp-formular" method="post"<?= $ziel !== '' ? ' action="' . $k->e($ziel) . '"' : '' ?>>
<?php if ($ziel === ''): ?>
<p class="lp-anmeldung-hinweis" role="note">Die Anmeldung wird gerade eingerichtet und ist in Kürze möglich.</p>
<?php endif ?>
<p class="lp-anmeldung-fehler" role="status" tabindex="-1" hidden><?= $k->e($k->du('Das hat leider nicht geklappt. Bitte prüfe deine Angaben und versuche es noch einmal. Klappt es wieder nicht, melde dich bitte direkt bei uns.', 'Das hat leider nicht geklappt. Bitte prüfen Sie Ihre Angaben und versuchen Sie es noch einmal. Klappt es wieder nicht, melden Sie sich bitte direkt bei uns.')) ?></p>
<?php if (isset($b['plaetze']) && $ziel !== ''): $plaetze = (int) $b['plaetze']; ?>
<p class="lp-anmeldung-plaetze" data-lp-plaetze-liste="<?= $k->e($b['liste']) ?>" data-lp-plaetze-quelle="<?= $k->e('https://' . parse_url($ziel, PHP_URL_HOST) . '/lp/plaetze') ?>" aria-live="polite">Begrenzt auf <?= $plaetze === 1 ? '1 Platz' : $plaetze . ' Plätze' ?>.</p>
<?php endif ?>
<p class="lp-anmeldung-voll" role="status" tabindex="-1" hidden>Leider ausgebucht — alle Plätze sind vergeben.</p>
<p class="lp-anmeldung-geschlossen" role="status" tabindex="-1" hidden><?= $k->e($k->du('Die Anmeldung ist im Moment geschlossen. Das liegt nicht an deinen Angaben. Bitte melde dich direkt bei uns.', 'Die Anmeldung ist im Moment geschlossen. Das liegt nicht an Ihren Angaben. Bitte melden Sie sich direkt bei uns.')) ?></p>
<fieldset aria-labelledby="<?= $k->id($nr, 'titel') ?>"<?= $ziel === '' ? ' disabled' : '' ?>>
<?php foreach ($felder as $name => [$label, $typ, $auto, $pflicht, $fehler]): if (!in_array($name, $b['felder'], true)) { continue; } $id = "lp-f-$name"; ?>
<div class="lp-feld">
<label for="<?= $id ?>"><?= $k->e($label) ?></label>
<input id="<?= $id ?>" name="<?= $name ?>" type="<?= $typ ?>" autocomplete="<?= $auto ?>"<?= $pflicht ? ' required' : '' ?>>
<?php if ($pflicht): ?>
<p class="lp-feld-fehler" id="<?= $id ?>-fehler" hidden><?= $k->e($fehler) ?></p>
<?php endif ?>
</div>
<?php endforeach ?>
<?php if ($freifeld): ?>
<div class="lp-feld lp-freifeld">
<label for="lp-f-anliegen"<?= trim((string) ($b['freifeld_frage'] ?? '')) !== '' ? $k->feld($nr, 'freifeld_frage') : '' ?>><?= $k->e($frage) ?> <span class="lp-freiwillig">(freiwillig)</span></label>
<textarea id="lp-f-anliegen" name="anliegen" rows="4" maxlength="1000" aria-describedby="lp-f-anliegen-hinweis"></textarea>
<p class="lp-freifeld-hinweis" id="lp-f-anliegen-hinweis">Höchstens 1000 Zeichen.</p>
</div>
<?php endif ?>
<?php if ($hinweis !== ''): ?>
<p class="lp-einwilligung-hinweis"><?= $k->e($hinweis) ?> Mehr dazu in der <a href="datenschutz.html">Datenschutzerklärung</a>.</p>
<input type="hidden" name="einwilligung" value="hinweis">
<?php else: ?>
<div class="lp-feld lp-einwilligung">
<input id="lp-f-einwilligung" name="einwilligung" type="checkbox" value="ja" required>
<label for="lp-f-einwilligung"><?= $k->e($p['einwilligung']) ?> Mehr dazu in der <a href="datenschutz.html">Datenschutzerklärung</a>.</label>
<p class="lp-feld-fehler" id="lp-f-einwilligung-fehler" hidden><?= $k->e($k->du('Bitte bestätige die Einwilligung.', 'Bitte bestätigen Sie die Einwilligung.')) ?></p>
</div>
<?php endif ?>
<?php if ($newsletterText !== ''): ?>
<div class="lp-feld lp-einwilligung lp-einwilligung--freiwillig">
<input id="lp-f-newsletter" name="newsletter" type="checkbox" value="ja">
<label for="lp-f-newsletter"><?= $mail($newsletterText) ?> <span class="lp-freiwillig">(freiwillig)</span></label>
</div>
<?php endif ?>
<div class="form-hp" aria-hidden="true">
<label for="lp-f-website">Website</label>
<input id="lp-f-website" name="website" type="text" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="liste" value="<?= $k->e($b['liste']) ?>">
<input type="hidden" name="einwilligung_kurz" value="<?= substr(hash('sha256', $hinweis !== '' ? $hinweis : (string) $p['einwilligung']), 0, 12) ?>">
<input type="hidden" name="fbclid" value="">
<?php if ($newsletterText !== ''): ?>
<input type="hidden" name="newsletter_kurz" value="<?= substr(hash('sha256', $newsletterText), 0, 12) ?>">
<?php endif ?>
<button type="submit" class="btn btn-primary lp-knopf"<?= $k->feld($nr, 'knopf_text') ?>><?= $k->e($b['knopf_text']) ?></button>
</fieldset>
<?php if ($ziel !== ''): ?>
<noscript><p class="lp-anmeldung-hinweis"><?= $k->e($k->du('Nach dem Absenden bekommst du eine E-Mail von uns. Kommt keine an, schau bitte im Spam-Ordner nach und versuche es sonst noch einmal.', 'Nach dem Absenden bekommen Sie eine E-Mail von uns. Kommt keine an, sehen Sie bitte im Spam-Ordner nach und versuchen Sie es sonst noch einmal.')) ?></p></noscript>
<?php endif ?>
<p class="lp-anmeldung-danke" role="status" tabindex="-1" hidden><span class="lp-anmeldung-danke-text"<?= $k->feld($nr, 'danke') ?>><?= $mail($b['danke']) ?></span> <span class="lp-anmeldung-danke-nachsatz"><?= $mail($k->du('Falls du schon angemeldet warst, bekommst du keine neue E-Mail.', 'Falls Sie schon angemeldet waren, bekommen Sie keine neue E-Mail.')) ?></span></p>
</form>
</div>
</section>
