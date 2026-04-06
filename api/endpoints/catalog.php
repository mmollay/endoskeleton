<?php
declare(strict_types=1);

/**
 * GET /api/v1/catalog — Komplettes Design-System als JSON.
 */

if ($method !== 'GET') {
    Response::error('Nur GET erlaubt', 405);
}

require_once __DIR__ . '/../lib/CatalogReader.php';

$catalog = new CatalogReader();
$data = $catalog->getCatalog();
$etag = '"endo-' . $data['version'] . '"';

Response::cached($data, $etag, 300);
