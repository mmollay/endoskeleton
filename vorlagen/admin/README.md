# Vorlage: sicherer Admin-Weg

Für Kundenseiten, die einen kleinen Verwaltungsbereich brauchen (Texte pflegen, Anmeldungen ansehen, eine Schnittstelle
auslösen …). Anlass: Bei einer Kundenseite stand ein geheimer Schlüssel im öffentlichen JavaScript, mit dem der Admin-Bereich
angesprochen wurde — jeder Besucher konnte ihn im Quelltext lesen. Diese Vorlage macht es richtig:

- **Anmeldung** mit Passwort (nur als `password_hash` gespeichert) auf einer Anmeldeseite; danach gilt nur die
  **Server-Sitzung** (`$_SESSION['admin']`, Cookie `__Host-admin`: `HttpOnly`, `Secure`, `SameSite=Strict`, eigener Name,
  neue Sitzungs-ID bei An- und Abmeldung, Ablauf nach Untätigkeit).
- **Herkunftsprüfung** bei jeder schreibenden Anfrage: `Origin` (ersatzweise `Referer`) muss eine der erlaubten Herkünfte
  sein, `Sec-Fetch-Site` (wenn vorhanden) `same-origin` — sonst 403. Änderungen nur per POST (sonst 405).
- **CSRF-Token** je Sitzung (`random_bytes`, Vergleich mit `hash_equals`): im Formular als Feld `csrf`, bei fetch im Kopf
  `X-CSRF-Token` (aus `<meta name="csrf-token">`).
- **Bremse** gegen Durchprobieren: Anmeldeversuche je Adresse in einem gleitenden Fenster, danach 429 mit `Retry-After`.
  Die IP wird nie im Klartext gespeichert (HMAC), der Zähler liegt außerhalb des DocumentRoot.
- **Sichere Kopfzeilen** in jeder Antwort: `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin`, `Content-Security-Policy` ohne Inline-Skript und ohne Inline-Stil, `X-Robots-Tag: noindex`.
- **Kein Schlüssel im Browser.** Braucht eine Aktion einen Schlüssel (z. B. für eine Schnittstelle), steht er in der
  Konfiguration und wird nur im Endpunkt auf dem Server verwendet (`$admin->einstellung('…')`).
- **Aus statt offen:** Fehlt die Konfiguration oder ist sie ungültig, antwortet jede Admin-Datei mit 404.

## Dateien

| Datei | Wohin in der Kundenseite | Aufgabe |
| --- | --- | --- |
| `api/_admin.php` | `api/_admin.php` | Bibliothek (`AdminWeg`): Konfiguration, Sitzung, Herkunft, CSRF, Bremse, Kopfzeilen. Direkt aufgerufen 404. |
| `api/aktion.php` | `api/aktion.php` | Beispiel-Endpunkt „Text speichern“: verlangt Sitzung + Herkunft + CSRF, antwortet JSON. |
| `admin/index.php` | `admin/index.php` | Anmeldeseite; angemeldet das Beispiel-Formular und „Abmelden“. |
| `admin/admin.js` | `admin/admin.js` | fetch mit `credentials: 'same-origin'` und `X-CSRF-Token` — ohne jeden Schlüssel. |
| `.htaccess` | Zeilen **ergänzen** in die `.htaccess` der Wurzel | `RedirectMatch 404 "/api/_"` sperrt die Hilfsdateien. |
| `konfig.beispiel.php` | **außerhalb** des DocumentRoot, z. B. `/home/KONTO/admin-konfig.php` | Muster der Konfiguration. |

Auf skeleton.ssi.at (das Repo ist dort DocumentRoot) läuft die Vorlage nie: `vorlagen/.htaccess` antwortet für alles mit 404,
und ohne Konfiguration antwortet ohnehin jede PHP-Datei mit 404.

## Einbauen

1. `api/` und `admin/` in die Kundenseite kopieren (Hilfsdateien in `api/` beginnen immer mit `_`).
2. Die zwei `RedirectMatch`-Zeilen aus `.htaccess` in die `.htaccess` der Kundenseite übernehmen — die Datei nicht ersetzen.
3. `konfig.beispiel.php` **außerhalb** des DocumentRoot ablegen (z. B. `/home/KONTO/admin-konfig.php`, Rechte 600),
   Datenordner anlegen (`mkdir -m 700 /home/KONTO/admin-daten`), Herkünfte auf die echte Domain setzen.
4. Passwort-Hash erzeugen (Befehl steht in `konfig.beispiel.php`) und eintragen. Das Passwort selbst steht nirgends.
5. Pfad nennen: `SetEnv ADMIN_KONFIG /home/KONTO/admin-konfig.php` (vhost oder `.htaccess`) — oder vor dem Einbinden von
   `_admin.php` `define('ADMIN_KONFIG', …)`.
6. Eigene Aktionen als weitere Endpunkte in `api/` nach dem Muster von `aktion.php`: zuerst `AdminWeg::start(json: true)`,
   dann `$admin->verlangeSchreiben()`, erst danach handeln.
7. Prüfen: `https://<domain>/api/_admin.php` → 404; `admin/` ohne Anmeldung zeigt nur das Passwortfeld; POST an
   `api/aktion.php` von einer fremden Seite oder ohne Token → 403; GET → 405.

## Was man NIE tut

- Schlüssel, Token, Passwörter oder Hashes in JavaScript, HTML, `data-`-Attribute oder ins Repo schreiben — alles, was
  der Browser bekommt, ist öffentlich. Auch kein `localStorage`/`sessionStorage` für Berechtigungen.
- Admin-Aufrufe per GET (Links, `<img>`, Vorabruf und Verlauf lösen GET aus): Änderungen nur per POST mit CSRF-Token.
- Gesperrte Pfade mit `Require all denied` (oder `Deny from all`) sperren: Auf den Servern sperrt fail2ban bei „denied“ die
  aufrufende Adresse — im Zweifel die eigene. Immer `RedirectMatch 404 …`.
- Die Konfiguration in den DocumentRoot legen oder eine `.env` mit Zugangsdaten mitliefern.
- `X-Forwarded-For` als Adresse der Bremse nehmen, solange kein eigener Proxy davor steht — der Kopf ist frei fälschbar.
- Inline-Skripte oder `style=`-Attribute: Die Content-Security-Policy verbietet sie, und das soll so bleiben.

## Grenzen

- **Gleiche Herkunft wie die öffentliche Seite:** Herkunftsprüfung, `SameSite` und CSRF-Token schützen gegen *fremde* Seiten. Ein Skript, das auf derselben
  Herkunft läuft (eingebundenes Fremd-Skript, Werbe- oder Statistik-Code, eine Lücke in der öffentlichen Seite), kann alles, was der angemeldete Admin kann.
  Lädt die öffentliche Seite fremde Skripte, gehört der Admin auf eine **eigene (Sub-)Domain** (z. B. `admin.example.com`) mit eigener Konfiguration.
- **Mehrere Seiten auf einem Server:** Jede Seite bekommt ihren **eigenen Datenordner** (`ordner`) und ihre eigene Konfiguration — die Sitzungen liegen in
  `<ordner>/sitzungen`, das Anmelde-Kennzeichen ist an Datenordner und Passwort gebunden. Zwei Seiten mit demselben Ordner teilen sich Sitzungen und Daten.
- **Hinter einem Reverse-Proxy** zählt die Bremse alle Besucher gemeinsam (`REMOTE_ADDR`); IPv6 zählt je /64-Netz.

## Checkliste

- [ ] Konfiguration außerhalb des DocumentRoot, Rechte 600, Pfad per `ADMIN_KONFIG`
- [ ] Passwort-Hash mit `password_hash` (Argon2id oder bcrypt), Passwort lang und nur beim Kunden bzw. im Passwortspeicher
- [ ] `herkuenfte` = die echte Domain (mit und ohne `www.`, nur HTTPS)
- [ ] Datenordner angelegt, außerhalb des DocumentRoot, Rechte 700
- [ ] `RedirectMatch 404 "/api/_"` in der `.htaccess` der Seite; nirgends `Require all denied`
- [ ] `api/_admin.php` von außen → 404; ohne Konfiguration → alle Admin-Dateien 404
- [ ] jeder Endpunkt beginnt mit `AdminWeg::start(json: true)` und `$admin->verlangeSchreiben()`
- [ ] im ausgelieferten JavaScript und HTML kein Schlüssel, kein Token-Literal, kein Passwort
- [ ] HTTPS aktiv (das Sitzungs-Cookie ist `Secure`; ohne HTTPS gibt es keine Anmeldung)
- [ ] Tests: `php tests/run.php admin`
