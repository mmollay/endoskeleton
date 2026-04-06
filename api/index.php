<?php
declare(strict_types=1);

/**
 * SSI Endoskeleton API v1 — Router
 * Routes /api/v1/* requests to endpoint handlers.
 */

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/lib/Response.php';

// Parse route
$route = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];
$segments = $route === '' ? [] : explode('/', $route);
$endpoint = $segments[0] ?? '';
$param = $segments[1] ?? null;

// Dispatch
match ($endpoint) {
    'catalog'   => require __DIR__ . '/endpoints/catalog.php',
    'presets'   => require __DIR__ . '/endpoints/presets.php',
    'recommend' => require __DIR__ . '/endpoints/recommend.php',
    'generate'  => require __DIR__ . '/endpoints/generate.php',
    'preview'   => require __DIR__ . '/endpoints/preview.php',
    'feedback'  => require __DIR__ . '/endpoints/feedback.php',
    'schema'    => require __DIR__ . '/endpoints/schema.php',
    ''          => Response::json([
        'name'    => 'SSI Endoskeleton API',
        'version' => 'v1',
        'docs'    => 'https://skeleton.ssi.at/api/v1/schema',
        'endpoints' => [
            'GET  /api/v1/catalog'          => 'Komplettes Design-System',
            'GET  /api/v1/presets'           => 'Alle Presets',
            'GET  /api/v1/presets/{name}'    => 'Preset-Detail',
            'POST /api/v1/recommend'         => 'Design-Empfehlung basierend auf Anforderungen',
            'POST /api/v1/generate'          => 'Seite generieren',
            'GET  /api/v1/preview?preset=X'  => 'Live-Vorschau',
            'POST /api/v1/feedback'          => 'Feature-Request loggen',
            'GET  /api/v1/schema'            => 'OpenAPI Schema',
        ]
    ]),
    default     => Response::error('Endpoint nicht gefunden: ' . $endpoint, 404),
};
