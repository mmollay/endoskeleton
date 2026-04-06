<?php
declare(strict_types=1);

final class RateLimiter
{
    private string $dir;

    public function __construct()
    {
        $this->dir = __DIR__ . '/../data/rate-limits';
    }

    public function check(int $maxRequests, int $windowSeconds = 3600): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $hash = md5($ip);
        $file = $this->dir . '/' . $hash . '.json';

        $now = time();
        $timestamps = [];

        if (file_exists($file)) {
            $timestamps = json_decode(file_get_contents($file), true) ?? [];
            $timestamps = array_values(array_filter($timestamps, fn($t) => $t > $now - $windowSeconds));
        }

        if (count($timestamps) >= $maxRequests) {
            return false;
        }

        $timestamps[] = $now;
        file_put_contents($file, json_encode($timestamps), LOCK_EX);

        return true;
    }

    public static function cleanup(): void
    {
        $dir = __DIR__ . '/../data/rate-limits';
        foreach (glob($dir . '/*.json') as $file) {
            if (filemtime($file) < time() - 7200) {
                unlink($file);
            }
        }
    }
}
