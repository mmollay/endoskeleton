<?php
declare(strict_types=1);

if ($method !== "POST") {
    Response::error("Nur POST erlaubt", 405);
}

require_once __DIR__ . "/../lib/RateLimiter.php";
require_once __DIR__ . "/../lib/CatalogReader.php";
require_once __DIR__ . "/../lib/SiteGenerator.php";

$limiter = new RateLimiter();
if (!$limiter->check(10)) {
    Response::error("Rate limit erreicht. Max 10 Generierungen pro Stunde.", 429);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    Response::error("JSON Body erforderlich", 400);
}

$catalog = new CatalogReader();
$presets = $catalog->getPresets();

$resolved = [];
$presetName = $input["preset"] ?? null;
if ($presetName !== null && isset($presets[$presetName])) {
    $resolved = $presets[$presetName];
}

$overrides = $input["overrides"] ?? [];
foreach ($overrides as $k => $v) {
    $resolved[$k] = $v;
}

if (empty($resolved)) {
    Response::error("preset oder overrides erforderlich", 400);
}

$content = $input["content"] ?? [];
if (empty($content)) {
    Response::error("content ist erforderlich (company, pages)", 400);
}

$generator = new SiteGenerator();
$result = $generator->generate($resolved, $content);

$format = $input["format"] ?? "urls";

if ($format === "zip") {
    $zipPath = __DIR__ . "/../data/generated/" . $result["hash"] . "/site.zip";
    if (file_exists($zipPath)) {
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"endoskeleton-site.zip\"");
        header("Content-Length: " . filesize($zipPath));
        readfile($zipPath);
        exit;
    }
    Response::error("ZIP konnte nicht erstellt werden", 500);
}

Response::json($result);
