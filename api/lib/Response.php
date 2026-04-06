<?php
declare(strict_types=1);

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['error' => $message, 'status' => $status], $status);
    }

    public static function cached(mixed $data, string $etag, int $maxAge = 300): never
    {
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === $etag) {
            http_response_code(304);
            exit;
        }
        header("ETag: $etag");
        header("Cache-Control: public, max-age=$maxAge");
        self::json($data);
    }
}
