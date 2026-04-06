<?php
declare(strict_types=1);

if ($method !== "POST") {
    Response::error("Nur POST erlaubt", 405);
}

require_once __DIR__ . "/../lib/RateLimiter.php";
require_once __DIR__ . "/../lib/CatalogReader.php";
require_once __DIR__ . "/../lib/RecommendEngine.php";

$limiter = new RateLimiter();
if (!$limiter->check(60)) {
    Response::error("Rate limit erreicht. Max 60 Requests pro Stunde.", 429);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    Response::error("JSON Body erforderlich", 400);
}

$fields = ["branch", "mood", "colors", "target_audience", "style_keywords"];
$hasInput = false;
foreach ($fields as $f) {
    if (!empty($input[$f])) {
        $hasInput = true;
        break;
    }
}
if (!$hasInput) {
    Response::error("Mindestens ein Feld erforderlich: " . implode(", ", $fields), 400);
}

$catalog = new CatalogReader();
$presets = $catalog->getPresets();

$engine = new RecommendEngine($presets);
$results = $engine->recommend($input);

$response = [
    "recommended"  => $results[0] ?? null,
    "alternatives" => array_slice($results, 1),
    "input"        => $input,
];

Response::json($response);
