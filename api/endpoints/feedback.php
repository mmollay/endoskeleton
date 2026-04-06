<?php
declare(strict_types=1);

$feedbackFile = __DIR__ . "/../data/feedback.json";

if ($method === "GET") {
    $entries = json_decode(file_get_contents($feedbackFile), true) ?? [];
    Response::json(["count" => count($entries), "entries" => $entries]);
}

if ($method !== "POST") {
    Response::error("Nur GET und POST erlaubt", 405);
}

require_once __DIR__ . "/../lib/RateLimiter.php";

$limiter = new RateLimiter();
if (!$limiter->check(20)) {
    Response::error("Rate limit erreicht. Max 20 Feedbacks pro Stunde.", 429);
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    Response::error("JSON Body erforderlich", 400);
}

$validTypes = ["missing_parameter", "missing_preset", "color_mismatch", "suggestion", "bug"];
$type = $input["type"] ?? "";
if (!in_array($type, $validTypes, true)) {
    Response::error("Ungueltiger Typ. Erlaubt: " . implode(", ", $validTypes), 400);
}

$description = trim($input["description"] ?? "");
if ($description === "") {
    Response::error("description ist erforderlich", 400);
}

$entry = [
    "id"          => "fb-" . date("Y-m-d") . "-" . substr(bin2hex(random_bytes(4)), 0, 6),
    "type"        => $type,
    "description" => $description,
    "context"     => $input["context"] ?? null,
    "created_at"  => date("c"),
    "ip"          => md5($_SERVER["REMOTE_ADDR"] ?? ""),
];

$entries = json_decode(file_get_contents($feedbackFile), true) ?? [];
$entries[] = $entry;
file_put_contents($feedbackFile, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

Response::json(["logged" => true, "id" => $entry["id"]]);
