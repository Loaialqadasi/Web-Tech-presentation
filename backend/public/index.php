<?php
/**
 * UniEvent PHP Slim 4 Backend - Front Controller
 * Team 9 (FreshDev) - SECJ3483 Web Technology Group Project
 * Backend Lead: Muhammad Amir Zafri Bin Mohd Adhar (A23CS0120)
 *
 * All requests are routed through this file. Apache .htaccess (or PHP built-in
 * server) rewrites everything to index.php. Slim then dispatches to the route
 * handlers under App\Routes\*.
 *
 * Run locally:   php -S localhost:8080 -t public
 * Run on XAMPP:  http://localhost/unievent-php-backend/public/
 */

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Autoload (Composer) and env
require __DIR__ . '/../vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class)) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        \Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
    }
}

// Build Slim app
$app = AppFactory::create();

// Add Body Parsing Middleware so $request->getParsedBody() works for JSON
$app->addBodyParsingMiddleware();

// Routing middleware (must be added before errors in Slim 4)
$app->addRoutingMiddleware();

// Error middleware (display full errors in development)
$displayErrors = (getenv('APP_DEBUG') === 'true') || (getenv('APP_ENV') !== 'production');
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);

// ─── CORS & OPTIONS preflight handler (mirror Express cors()) ────────────────
$app->options('/api/{routes:.+}', function (Request $request, ResponseInterface $response): ResponseInterface {
    return $response;
});

$app->add(function (Request $request, RequestHandler $handler): ResponseInterface {
    $response = $handler->handle($request);

    // CORS — use * in dev, or restrict via CORS_ORIGIN env in production.
    $origin = getenv('CORS_ORIGIN') ?: '*';
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Max-Age', '86400');

    return $response;
});

// JSON content-type for API responses (default)
$app->add(function (Request $request, RequestHandler $handler): ResponseInterface {
    $response = $handler->handle($request);
    if (!$response->hasHeader('Content-Type')) {
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
    return $response;
});

// ─── Request logging (mirror Express logger) ────────────────────────────────
$app->add(function (Request $request, RequestHandler $handler): ResponseInterface {
    $ts = (new DateTime())->format('c');
    error_log("[$ts] {$request->getMethod()} {$request->getUri()->getPath()}");
    return $handler->handle($request);
});

// ─── Health check ───────────────────────────────────────────────────────────
$app->get('/api/health', function (Request $request, ResponseInterface $response): ResponseInterface {
    $payload = [
        'status' => 'ok',
        'timestamp' => (new DateTime())->format('c'),
        'env' => getenv('APP_ENV') ?: 'production',
        'backend' => 'PHP Slim 4',
    ];
    $response->getBody()->write(json_encode($payload));
    return $response;
});

// ─── Register Route Modules ──────────────────────────────────────────────────
// Each module mounts its own group under /api/<module>
use App\Routes\AuthRoutes;
use App\Routes\EventRoutes;
use App\Routes\BookingRoutes;
use App\Routes\PaymentRoutes;
use App\Routes\ForumRoutes;
use App\Routes\FeedbackRoutes;
use App\Routes\NotificationRoutes;
use App\Routes\CalendarRoutes;
use App\Routes\DashboardRoutes;
use App\Routes\AgendaRoutes;

(new AuthRoutes())($app);
(new EventRoutes())($app);
(new BookingRoutes())($app);
(new PaymentRoutes())($app);
(new ForumRoutes())($app);
(new FeedbackRoutes())($app);
(new NotificationRoutes())($app);
(new CalendarRoutes())($app);
(new DashboardRoutes())($app);
(new AgendaRoutes())($app);

// ─── API 404 Handler ────────────────────────────────────────────────────────
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/api/{routes:.+}', function (Request $request, ResponseInterface $response): ResponseInterface {
    $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Route ' . $request->getMethod() . ' ' . $request->getUri()->getPath() . ' not found.',
    ]));
    return $response->withStatus(404);
});

$app->run();
