<?php
declare(strict_types=1);

namespace App\Middleware;

use App\JwtHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * PSR-15 middleware: verify JWT token from Authorization header.
 * Mirror of `authenticateToken` in api/middleware/auth.js.
 *
 * On success: attaches decoded user to request as attribute 'user'.
 * On failure: returns 401/403 JSON response.
 */
final class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = JwtHelper::extractBearerToken($authHeader);

        if (!$token) {
            return self::deny(401, 'Access denied. No token provided.');
        }

        $decoded = JwtHelper::decode($token);

        if ($decoded === null) {
            return self::deny(403, 'Invalid token.');
        }
        if (isset($decoded['__expired']) && $decoded['__expired'] === true) {
            return self::deny(401, 'Token expired. Please log in again.');
        }

        // Attach user info to request for downstream handlers
        $request = $request->withAttribute('user', [
            'user_id' => $decoded['user_id'],
            'email'   => $decoded['email'],
            'role'    => $decoded['role'],
            'name'    => $decoded['name'],
        ]);

        return $handler->handle($request);
    }

    private static function deny(int $status, string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
