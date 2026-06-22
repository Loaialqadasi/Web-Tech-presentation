<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * PSR-15 middleware factory: require the authenticated user to have one of the
 * given roles. Mirror of `requireRole(...roles)` in api/middleware/auth.js.
 *
 * MUST be added AFTER AuthMiddleware so $request->getAttribute('user') exists.
 *
 * Usage (inside a route group):
 *   $app->group('/api/events', function (...) { ... })
 *       ->add(new RoleMiddleware('organizer', 'admin'))
 *       ->add(new AuthMiddleware());
 */
final class RoleMiddleware
{
    /** @var string[] */
    private array $allowedRoles;

    public function __construct(string ...$roles)
    {
        $this->allowedRoles = $roles;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return self::deny(401, 'Authentication required.');
        }

        if (!in_array($user['role'], $this->allowedRoles, true)) {
            return self::deny(403, 'Insufficient permissions.');
        }

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
