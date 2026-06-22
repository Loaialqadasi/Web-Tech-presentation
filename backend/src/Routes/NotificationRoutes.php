<?php
declare(strict_types=1);

namespace App\Routes;

use App\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Notification routes — mirrors api/routes/notifications.js
 *   GET    /api/notifications/user/{userId}        (auth)
 *   POST   /api/notifications                      (auth)
 *   PUT    /api/notifications/{id}/read            (auth)
 *   PUT    /api/notifications/read-all/{userId}    (auth)
 *   DELETE /api/notifications/{id}                 (auth)
 */
final class NotificationRoutes
{
    use JsonResponder;

    private const ALLOWED_TYPES = ['success', 'warning', 'info'];

    public function __invoke(App $app): void
    {
        $app->group('/api/notifications', function (RouteCollectorProxy $g): void {

            // ─── GET /api/notifications/user/{userId} ───────────────────────
            $g->get('/user/{userId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$args['userId'];

                if ($userId !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll();

                $notifications = array_map(function ($n) {
                    return [
                        'id'        => (int)$n['notification_id'],
                        'title'     => $n['title'],
                        'message'   => $n['message'],
                        'type'      => $n['notification_type'],
                        'read'      => (bool)$n['is_read'],
                        'createdAt' => $n['created_at'],
                    ];
                }, $rows);

                $unreadCount = count(array_filter($rows, fn($n) => !(bool)$n['is_read']));

                $bookingNotifications = array_values(array_filter(
                    $notifications,
                    fn($n) => stripos((string)$n['title'], 'booking') !== false
                ));
                $paymentNotifications = array_values(array_filter(
                    $notifications,
                    fn($n) => stripos((string)$n['title'], 'payment') !== false
                ));
                $reminderNotifications = array_values(array_filter(
                    $notifications,
                    fn($n) => strtolower((string)$n['title']) === 'upcoming event reminder'
                ));
                $calendarSyncNotifications = array_values(array_filter(
                    $notifications,
                    fn($n) => strtolower((string)$n['title']) === 'calendar synced'
                ));

                return self::json($response, 200, [
                    'success'      => true,
                    'notifications'=> $notifications,
                    'unreadCount'  => $unreadCount,
                    'reminderNotifications' => $reminderNotifications,
                    'bookingNotifications' => $bookingNotifications,
                    'paymentNotifications' => $paymentNotifications,
                    'calendarSyncNotifications' => $calendarSyncNotifications,
                ]);
                } catch (\Throwable $e) {
                    error_log('[Notification] GET /user/{userId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch notifications.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── POST /api/notifications ────────────────────────────────────
            $g->post('', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                if (($user['role'] ?? '') !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Only internal/admin flows can create notifications.']);
                }

                $b = (array)$request->getParsedBody();
                $userId  = (int)($b['userId']  ?? 0);
                $title   = (string)($b['title']   ?? '');
                $message = (string)($b['message'] ?? '');
                $type    = strtolower((string)($b['type'] ?? 'info'));

                if ($userId <= 0 || $title === '' || $message === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'User ID, title, and message are required.']);
                }
                if (!in_array($type, self::ALLOWED_TYPES, true)) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Notification type must be success, warning, or info.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare(
                    'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $title, $message, $type]);
                $notificationId = (int)$pdo->lastInsertId();

                return self::json($response, 201, [
                    'success'       => true,
                    'message'       => 'Notification created!',
                    'notificationId'=> $notificationId,
                ]);
                } catch (\Throwable $e) {
                    error_log('[Notification] POST / error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to create notification.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── PUT /api/notifications/read-all/{userId} ───────────────────
            // (Declared BEFORE /{id}/read so this wins on /read-all/...)
            $g->put('/read-all/{userId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$args['userId'];

                if ($userId !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE user_id = ?');
                $stmt->execute([$userId]);
                return self::json($response, 200, ['success' => true, 'message' => 'All notifications marked as read.']);
                } catch (\Throwable $e) {
                    error_log('[Notification] PUT /read-all/{userId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to mark all notifications as read.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── PUT /api/notifications/{id}/read ───────────────────────────
            $g->put('/{id}/read', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];
                try {
                $pdo = Database::pdo();

                $stmt = $pdo->prepare('SELECT user_id FROM notifications WHERE notification_id = ?');
                $stmt->execute([$id]);
                $notification = $stmt->fetch();
                if (!$notification) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Notification not found.']);
                }
                if ((int)$notification['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE notification_id = ?');
                $stmt->execute([$id]);
                return self::json($response, 200, ['success' => true, 'message' => 'Notification marked as read.']);
                } catch (\Throwable $e) {
                    error_log('[Notification] PUT /{id}/read error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to mark notification as read.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── DELETE /api/notifications/{id} ─────────────────────────────
            $g->delete('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];
                try {
                $pdo = Database::pdo();

                $stmt = $pdo->prepare('SELECT user_id FROM notifications WHERE notification_id = ?');
                $stmt->execute([$id]);
                $notification = $stmt->fetch();
                if (!$notification) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Notification not found.']);
                }
                if ((int)$notification['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                $stmt = $pdo->prepare('DELETE FROM notifications WHERE notification_id = ?');
                $stmt->execute([$id]);
                return self::json($response, 200, ['success' => true, 'message' => 'Notification deleted.']);
                } catch (\Throwable $e) {
                    error_log('[Notification] DELETE /{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete notification.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());
        });
    }
}
