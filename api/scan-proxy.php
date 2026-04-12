<?php
/**
 * Scan Proxy — forwards requests to scanner.ssi.at to avoid CSP restrictions.
 * Usage: /api/scan-proxy.php?path=scans/domain.at
 *        /api/scan-proxy.php?path=scan (POST)
 */

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing path parameter']);
    exit;
}

// Whitelist allowed paths
if (!preg_match('/^(scans\/[a-z0-9.\-]+|scan|refine)$/i', $path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid path']);
    exit;
}

$url = 'https://scanner.ssi.at/api/v1/' . $path;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'Proxy error: ' . $error]);
    exit;
}

http_response_code($httpCode);
echo $response;
