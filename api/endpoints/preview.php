<?php
declare(strict_types=1);

if ($method !== "GET") {
    Response::error("Nur GET erlaubt", 405);
}

$params = $_GET;
unset($params["route"]);

if (empty($params)) {
    Response::error("Mindestens ein Parameter erforderlich (z.B. preset=corporate)", 400);
}

$hashParts = [];
$allowed = ["preset", "layout", "hero", "color", "buttons", "charakter", "spacing",
             "animation", "width", "font", "navBehavior", "navLayout", "navStyle",
             "footerStyle", "theme", "navCase"];
foreach ($params as $k => $v) {
    if (in_array($k, $allowed, true)) {
        $hashParts[] = urlencode($k) . "=" . urlencode($v);
    }
}

if (empty($hashParts)) {
    Response::error("Keine gueltigen Parameter gefunden", 400);
}

$url = "https://skeleton.ssi.at/demo.html#" . implode("&", $hashParts);
header("Location: " . $url, true, 302);
exit;
