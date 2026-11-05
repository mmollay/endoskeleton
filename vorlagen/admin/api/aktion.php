<?php
/**
 * Beispiel-Endpunkt des Admin-Wegs (Vorlage aus dem SSI Endoskeleton): speichert einen Text — nur per POST, nur mit
 * angemeldeter Sitzung, eigener Herkunft und CSRF-Token. Antwort immer JSON.
 *
 * So läuft jede privilegierte Aktion: Der Browser schickt Cookie und CSRF-Token, der Server prüft und handelt selbst.
 * Braucht die Aktion einen Schlüssel (z. B. für eine Schnittstelle), steht er in der Konfiguration außerhalb des
 * DocumentRoot und wird nur hier gelesen — $admin->einstellung('dienst_schluessel') — er verlässt den Server nie.
 */

declare(strict_types=1);

require __DIR__ . '/_admin.php';

$admin = AdminWeg::start(json: true);
$admin->verlangeSchreiben();

$text = AdminWeg::feld('text');
if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 10000) {
    AdminWeg::json(['ok' => false, 'fehler' => 'Der Text ist ungültig oder länger als 10 000 Zeichen.'], 422);
}

$datei = $admin->datei('text.txt');
if (file_put_contents("$datei.neu", $text, LOCK_EX) === false || !rename("$datei.neu", $datei)) {
    throw new RuntimeException("Text nicht gespeichert: $datei");
}

AdminWeg::json(['ok' => true, 'meldung' => 'Gespeichert.', 'zeichen' => mb_strlen($text)]);
