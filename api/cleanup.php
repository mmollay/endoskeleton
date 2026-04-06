<?php
declare(strict_types=1);

require_once __DIR__ . "/lib/RateLimiter.php";
require_once __DIR__ . "/lib/SiteGenerator.php";

RateLimiter::cleanup();
SiteGenerator::cleanup();

echo date("c") . " — Cleanup done\n";
