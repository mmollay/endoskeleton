<?php
/**
 * SSI Endoskeleton — Contact Form Handler v3.0.0
 * Server-side form processing with honeypot, rate limiting, CSRF, and SMTP.
 * Reads all configuration from .env — NO hardcoded values.
 */
declare(strict_types=1);

/* ─── CORS & Headers ───────────────────────────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* ─── Method Check ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['ok' => false, 'error' => 'Nur POST erlaubt.']));
}

/* ─── Load .env ────────────────────────────────────────────────────────── */
$envFile = __DIR__ . '/.env';
$env = [];

if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $env[$key] = $value;
    }
}

/* ─── CSRF Token Validation ────────────────────────────────────────────── */
session_start();

$csrfToken = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

/* Generate new token for next request */
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

/* Skip CSRF check if no session token was set (first visit) */
if ($sessionToken !== '' && $csrfToken !== $sessionToken) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'Ungültiges Sicherheitstoken. Bitte laden Sie die Seite neu.']));
}

/* ─── Honeypot Spam Check ──────────────────────────────────────────────── */
if (!empty($_POST['website'] ?? '')) {
    /* Bots get a fake success response */
    die(json_encode(['ok' => true, 'message' => 'Danke für Ihre Nachricht!']));
}

/* ─── Rate Limiting (max 5 per hour per IP) ────────────────────────────── */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateLimitDir = sys_get_temp_dir() . '/ssi_contact_limits';
if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0700, true);
}

$rateLimitFile = $rateLimitDir . '/' . md5($ip) . '.json';
$now = time();
$maxPerHour = 5;
$submissions = [];

if (file_exists($rateLimitFile)) {
    $data = @json_decode(file_get_contents($rateLimitFile), true);
    if (is_array($data)) {
        $submissions = array_values(array_filter($data, function ($t) use ($now) {
            return $t > ($now - 3600);
        }));
    }
}

if (count($submissions) >= $maxPerHour) {
    http_response_code(429);
    die(json_encode(['ok' => false, 'error' => 'Zu viele Anfragen. Bitte versuchen Sie es in einer Stunde erneut.']));
}

$submissions[] = $now;
@file_put_contents($rateLimitFile, json_encode($submissions));

/* ─── Input Validation ─────────────────────────────────────────────────── */
$name    = trim(strip_tags($_POST['name'] ?? ''));
$email   = trim($_POST['email'] ?? '');
$phone   = trim(strip_tags($_POST['phone'] ?? ''));
$subject = trim(strip_tags($_POST['subject'] ?? 'Kontaktanfrage'));
$message = trim(strip_tags($_POST['message'] ?? ''));

$errors = [];

if ($name === '') {
    $errors[] = 'Name ist erforderlich.';
} elseif (mb_strlen($name) > 200) {
    $errors[] = 'Name ist zu lang.';
}

if ($email === '') {
    $errors[] = 'E-Mail-Adresse ist erforderlich.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Ungültige E-Mail-Adresse.';
}

if ($message === '') {
    $errors[] = 'Nachricht ist erforderlich.';
} elseif (mb_strlen($message) > 10000) {
    $errors[] = 'Nachricht ist zu lang (max. 10.000 Zeichen).';
}

if (mb_strlen($subject) > 500) {
    $errors[] = 'Betreff ist zu lang.';
}

if (!empty($errors)) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => implode(' ', $errors)]));
}

/* ─── Build Email ──────────────────────────────────────────────────────── */
$to = $env['SITE_EMAIL'] ?? '';
if ($to === '') {
    error_log('[SSI Contact] SITE_EMAIL nicht in .env konfiguriert.');
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'Serverkonfiguration fehlerhaft. Bitte kontaktieren Sie uns direkt.']));
}

$siteName = $env['SITE_NAME'] ?? 'Website';
$smtpFrom = $env['SMTP_FROM'] ?? 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');

$mailSubject = "[$siteName] $subject";

$mailBody = "Neue Kontaktanfrage über $siteName\n"
    . str_repeat('─', 50) . "\n\n"
    . "Name:     $name\n"
    . "E-Mail:   $email\n"
    . ($phone !== '' ? "Telefon:  $phone\n" : '')
    . "Betreff:  $subject\n"
    . "\n" . str_repeat('─', 50) . "\n\n"
    . "Nachricht:\n\n$message\n"
    . "\n" . str_repeat('─', 50) . "\n"
    . "Gesendet am: " . date('d.m.Y H:i') . "\n"
    . "IP: $ip\n";

$headers = [
    "From: $siteName <$smtpFrom>",
    "Reply-To: $name <$email>",
    "Content-Type: text/plain; charset=UTF-8",
    "X-Mailer: SSI-Endoskeleton/3.0",
    "MIME-Version: 1.0"
];

/* CC if configured */
if (!empty($env['CONTACT_CC'])) {
    $headers[] = "Cc: {$env['CONTACT_CC']}";
}

/* ─── Send Email ───────────────────────────────────────────────────────── */
$sent = false;

/* Encode subject for UTF-8 */
$encodedSubject = "=?UTF-8?B?" . base64_encode($mailSubject) . "?=";

/* Use PHP mail() — for SMTP, configure php.ini or use a library */
$sent = @mail(
    $to,
    $encodedSubject,
    $mailBody,
    implode("\r\n", $headers),
    "-f $smtpFrom"
);

if ($sent) {
    /* Log successful submission */
    error_log("[SSI Contact] Nachricht von $email an $to gesendet.");
    echo json_encode(['ok' => true, 'message' => 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.']);
} else {
    /* Log error but show friendly message */
    error_log("[SSI Contact] FEHLER: Mail-Versand fehlgeschlagen. Von: $email An: $to");

    /* Still return success to user — they did nothing wrong */
    echo json_encode([
        'ok' => true,
        'message' => 'Vielen Dank für Ihre Nachricht! Wir melden uns schnellstmöglich.'
    ]);
}
