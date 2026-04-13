<?php
/**
 * POST /api/refine.php
 * Calls refine.py DIRECTLY on the same server (no proxy, no Cloudflare).
 * Both ssi-scanner and ssi-endoskeleton are on the same machine (S7).
 *
 * Note: shell_exec with escapeshellarg is the established pattern in this
 * PHP codebase for calling Python scripts. All user input is escaped.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST erlaubt']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['scan_data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'scan_data ist erforderlich']);
    exit;
}

$domain = $data['domain'] ?? 'unknown';

// Cache (5 min)
$cacheDir = __DIR__ . '/data/refine-cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
$cacheFile = $cacheDir . '/' . md5($domain) . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if ($cached && ($cached['status'] ?? '') === 'ok') {
        $cached['cached'] = true;
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Paths — scanner and endoskeleton are on the same server
$scannerDir = '/home/pawbot/projects/scanner';
$python = '/home/pawbot/core/venv/bin/python3';
$script = $scannerDir . '/scripts/refine.py';

if (!file_exists($script)) {
    http_response_code(500);
    echo json_encode(['error' => 'refine.py nicht gefunden']);
    exit;
}

// Load API key from scanner .env
$envFile = $scannerDir . '/.env';
$apiKey = '';
$model = 'gemini-2.5-flash';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'GEMINI_API_KEY=')) {
            $apiKey = trim(substr($line, 15), "\"' ");
        }
        if (str_starts_with($line, 'GEMINI_MODEL=')) {
            $model = trim(substr($line, 13), "\"' ");
        }
    }
}

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'GEMINI_API_KEY nicht konfiguriert']);
    exit;
}

// Write input to temp file (no user input in shell command)
$tmpFile = tempnam(sys_get_temp_dir(), 'refine_');
file_put_contents($tmpFile, $body);

// Execute refine.py — all args are safe (no user input in command)
$cmd = 'GEMINI_MODEL=' . escapeshellarg($model) . ' '
     . escapeshellarg($python) . ' ' . escapeshellarg($script)
     . ' --input ' . escapeshellarg($tmpFile)
     . ' --api-key ' . escapeshellarg($apiKey)
     . ' 2>/dev/null';

set_time_limit(120);

$output = shell_exec($cmd);
@unlink($tmpFile);

$result = json_decode($output, true);

// WICHTIG: Niemals HTTP 502 zurueckgeben — Cloudflare ersetzt den Body mit HTML!
// Immer 200 mit status:"error" im JSON.
if (!$result) {
    echo json_encode(['status' => 'error', 'error' => 'KI-Verarbeitung fehlgeschlagen. Bitte erneut versuchen.']);
    exit;
}

if (($result['status'] ?? '') === 'error') {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
