<?php
declare(strict_types=1);

namespace App\Routes;

use App\Database;
use App\Middleware\AuthMiddleware;
use App\Routes\AuthRoutes;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Dashboard route — mirrors api/routes/dashboard.js
 *   GET /api/dashboard/{userId}   (auth)
 *
 * Aggregates: user info, booking stats, upcoming events, recent notifications,
 * unread count, calendar count, feedback count.
 */
final class DashboardRoutes
{
    use JsonResponder;

    public function __invoke(App $app): void
    {
        $app->group('/api/dashboard', function (RouteCollectorProxy $g): void {

            $g->get('/{userId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$args['userId'];

                if ($userId !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                try {
                    $pdo = Database::pdo();

                    // User info
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
                    $stmt->execute([$userId]);
                    $u = $stmt->fetch();
                    if (!$u) {
                        return self::json($response, 404, ['success' => false, 'message' => 'User not found.']);
                    }

                    // Booking stats
                    $stmt = $pdo->prepare(
                        'SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN booking_status = "confirmed" THEN 1 ELSE 0 END) as confirmed,
                            SUM(CASE WHEN booking_status = "pending_payment" THEN 1 ELSE 0 END) as pending
                         FROM bookings WHERE user_id = ?'
                    );
                    $stmt->execute([$userId]);
                    $bs = $stmt->fetch();

                    // Upcoming events
                    $stmt = $pdo->prepare(
                        'SELECT e.event_id, e.title, e.event_date, e.start_time, e.venue, e.category, e.image_url
                         FROM bookings b
                         JOIN events e ON b.event_id = e.event_id
                         WHERE b.user_id = ? AND b.booking_status IN ("confirmed", "active") AND e.event_date >= CURDATE()
                         ORDER BY e.event_date ASC
                         LIMIT 5'
                    );
                    $stmt->execute([$userId]);
                    $upcoming = $stmt->fetchAll();

                    // Unread notif count
                    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = FALSE');
                    $stmt->execute([$userId]);
                    $unread = (int)$stmt->fetch()['cnt'];

                    // Recent notifications
                    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
                    $stmt->execute([$userId]);
                    $recentNotifs = $stmt->fetchAll();

                    // Calendar count
                    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM calendar_events WHERE user_id = ?');
                    $stmt->execute([$userId]);
                    $calCount = (int)$stmt->fetch()['cnt'];

                    // Feedback count
                    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM feedback WHERE user_id = ?');
                    $stmt->execute([$userId]);
                    $fbCount = (int)$stmt->fetch()['cnt'];

                    $upcomingEvents = array_map(function ($e) {
                        return [
                            'id'       => (int)$e['event_id'],
                            'title'    => $e['title'],
                            'date'     => self::dateOnly($e['event_date']),
                            'time'     => $e['start_time'],
                            'venue'    => $e['venue'],
                            'category' => $e['category'],
                            'image'    => $e['image_url'],
                        ];
                    }, $upcoming);

                    $recentNotifications = array_map(function ($n) {
                        return [
                            'id'        => (int)$n['notification_id'],
                            'title'     => $n['title'],
                            'message'   => $n['message'],
                            'type'      => $n['notification_type'],
                            'read'      => (bool)$n['is_read'],
                            'createdAt' => $n['created_at'],
                        ];
                    }, $recentNotifs);

                    return self::json($response, 200, [
                        'success' => true,
                        'dashboard' => [
                            'user'   => AuthRoutes::publicUser($u),
                            'stats'  => [
                                'totalBookings'        => (int)($bs['total'] ?? 0),
                                'confirmedBookings'    => (int)($bs['confirmed'] ?? 0),
                                'pendingBookings'      => (int)($bs['pending'] ?? 0),
                                'unreadNotifications'  => $unread,
                                'calendarEvents'       => $calCount,
                                'feedbackGiven'        => $fbCount,
                            ],
                            'upcomingEvents'       => $upcomingEvents,
                            'recentNotifications'  => $recentNotifications,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    error_log('[Dashboard] GET error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to load dashboard.']);
                }
            })->add(new AuthMiddleware());
        });
    }
}
