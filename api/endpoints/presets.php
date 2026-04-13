<?php
declare(strict_types=1);

/**
 * GET /api/v1/presets        — Liste aller Presets
 * GET /api/v1/presets/{name} — Detail eines Presets
 */

if ($method !== 'GET') {
    Response::error('Nur GET erlaubt', 405);
}

require_once __DIR__ . '/../lib/CatalogReader.php';

$catalog = new CatalogReader();

if ($param !== null) {
    // Detail: /api/v1/presets/corporate
    $name = preg_replace('/[^a-z0-9_-]/', '', strtolower($param));
    $detail = $catalog->getPresetDetail($name);
    if ($detail === null) {
        Response::error("Preset nicht gefunden: $name", 404);
    }
    $etag = '"preset-' . $name . '-' . $catalog->getVersion() . '"';
    Response::cached($detail, $etag, 300);
}

// Liste: /api/v1/presets
$presets = $catalog->getPresets();
$list = [];
foreach ($presets as $name => $config) {
    $list[$name] = [
        'parameters'  => $config,
        'preview_url' => 'https://skeleton.ssi.at/konfigurator.html?fullscreen=1#preset=' . $name,
    ];
}

$etag = '"presets-' . $catalog->getVersion() . '"';
Response::cached($list, $etag, 300);
