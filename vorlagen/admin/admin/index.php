<?php
/**
 * Anmeldeseite und Beispiel des Admin-Wegs (Vorlage aus dem SSI Endoskeleton).
 * Ohne Anmeldung: Passwortfeld (POST an diese Seite). Angemeldet: Beispiel „Text speichern“ (POST an ../api/aktion.php,
 * mit admin.js per fetch) und Abmelden. Kein Inline-Skript, kein Inline-Stil — die Content-Security-Policy verbietet beides.
 */

declare(strict_types=1);

require __DIR__ . '/../api/_admin.php';

$admin = AdminWeg::start();
$meldung = '';

if ($admin->methode() === 'POST') {
    $was = AdminWeg::feld('aktion');
    if ($was === 'anmelden') {
        if ($admin->anmelden(AdminWeg::feld('passwort'))) { AdminWeg::weiter('index.php'); }
        http_response_code(403);
        $meldung = 'Anmeldung fehlgeschlagen.';
    } elseif ($was === 'abmelden') {
        $admin->abmelden();
        AdminWeg::weiter('index.php');
    } else {
        throw new AdminAbbruch(400, 'Unbekannte Aktion.');
    }
}

$angemeldet = $admin->angemeldet();
$csrf = $admin->csrfToken();
$text = $angemeldet && is_file($admin->datei('text.txt')) ? (string) file_get_contents($admin->datei('text.txt')) : '';
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= $h($csrf) ?>">
<title>Verwaltung</title>
<script src="admin.js" defer></script>
</head>
<body>
<main>
<h1>Verwaltung</h1>
<?php if ($meldung !== ''): ?>
<p role="alert"><?= $h($meldung) ?></p>
<?php endif; ?>
<?php if (!$angemeldet): ?>
<form method="post" action="index.php">
  <input type="hidden" name="aktion" value="anmelden">
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <label for="passwort">Passwort</label>
  <input id="passwort" name="passwort" type="password" autocomplete="current-password" required>
  <button type="submit">Anmelden</button>
</form>
<?php else: ?>
<form method="post" action="../api/aktion.php" data-admin-fetch>
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <label for="text">Text</label>
  <textarea id="text" name="text" rows="8" maxlength="10000"><?= $h($text) ?></textarea>
  <button type="submit">Text speichern</button>
  <p data-admin-meldung role="status" aria-live="polite"></p>
</form>
<form method="post" action="index.php">
  <input type="hidden" name="aktion" value="abmelden">
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <button type="submit">Abmelden</button>
</form>
<?php endif; ?>
</main>
</body>
</html>
