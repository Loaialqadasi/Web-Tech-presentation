<?php
declare(strict_types=1);

namespace App\Routes;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Tiny JSON helper trait used by all route modules.
 */
trait JsonResponder
{
    private static function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Convert a MySQL DATE/DATETIME string (e.g. "2026-07-15" or "2026-07-15 09:00:00")
     * into the YYYY-MM-DD shape the Node version returns via `.toISOString().split('T')[0]`.
     */
    private static function dateOnly(?string $v): ?string
    {
        if (!$v) return null;
        $t = strtotime($v);
        return $t !== false ? date('Y-m-d', $t) : $v;
    }
}
