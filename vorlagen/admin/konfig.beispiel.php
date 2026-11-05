<?php
/**
 * Beispiel der Admin-Konfiguration (Vorlage aus dem SSI Endoskeleton, vorlagen/admin/).
 *
 * NIE in den DocumentRoot und NIE ins Repo: kopieren nach z. B. /home/KONTO/admin-konfig.php (über dem DocumentRoot),
 * Rechte 600, und den Pfad nennen — im vhost oder in der .htaccess der Seite:
 *     SetEnv ADMIN_KONFIG /home/KONTO/admin-konfig.php
 * Solange die Datei fehlt oder ungültig ist (etwa mit dem Platzhalter unten), antworten alle Admin-Dateien mit 404.
 *
 * Passwort-Hash erzeugen (bash) — das Passwort wird verdeckt abgefragt, Leerzeichen am Rand bleiben erhalten, eine leere Eingabe bricht ab,
 * und es landet weder im Shell-Verlauf noch in der Prozessliste:
 *     IFS= read -rsp 'Passwort: ' PW && echo && [ -n "$PW" ] && printf %s "$PW" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_ARGON2ID), PHP_EOL;'; unset PW
 * Keine Kurzform mit dem Passwort im Befehl: in php -r "…" verfälscht PHP $ und \ im Passwort, und es stünde im Shell-Verlauf.
 * Kennt das PHP des Servers kein Argon2id, PASSWORD_DEFAULT (bcrypt) nehmen. Den Hash in einfache Anführungszeichen setzen ($ im Hash).
 */

declare(strict_types=1);

return [
    // Ergebnis von password_hash() — hier steht absichtlich nur ein Platzhalter, damit ist der Admin aus.
    'passwort_hash' => 'HIER-DEN-HASH-EINTRAGEN',

    // Erlaubte Herkünfte schreibender Anfragen: Schema und Host (Port nur, wenn nicht Standard), ohne Pfad.
    'herkuenfte' => ['https://example.com', 'https://www.example.com'],

    // Datenordner außerhalb des DocumentRoot (muss bestehen: mkdir -m 700 …) — Bremse gegen Durchprobieren und Daten der Beispiel-Aktion.
    'ordner' => __DIR__ . '/admin-daten',

    // Abmelden nach so vielen Minuten Untätigkeit.
    'sitzung_minuten' => 30,

    // Bremse: höchstens so viele Anmeldeversuche je Adresse im Fenster (Minuten), danach 429.
    'bremse_versuche' => 5,
    'bremse_minuten' => 15,

    // Eigene Werte, z. B. der Schlüssel eines Fremddienstes für api/aktion.php — wird nur serverseitig gelesen ($admin->einstellung()).
    // 'dienst_schluessel' => '',
];
