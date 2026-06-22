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
 * Payment routes — mirrors api/routes/payments.js
 *   POST /api/payments                 (auth, student)  Simulate payment for a booking
 *   GET  /api/payments/user/{userId}   (auth)           Payment history for a user
 *
 * Payment simulation: 90% success / 10% failure (same as Node version).
 */
final class PaymentRoutes
{
    use JsonResponder;

    public function __invoke(App $app): void
    {
        $app->group('/api/payments', function (RouteCollectorProxy $g): void {

            // ─── POST /api/payments ─────────────────────────────────────────
            $g->post('', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$user['user_id'];
                $b = (array)$request->getParsedBody();

                $bookingId = (int)($b['bookingId'] ?? 0);
                $paymentMethod = (string)($b['paymentMethod'] ?? 'card');

                if ($bookingId <= 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Booking ID is required.']);
                }

                $pdo = Database::pdo();

                try {
                    $pdo->beginTransaction();

                    // Verify booking belongs to user
                    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?');
                    $stmt->execute([$bookingId, $userId]);
                    $booking = $stmt->fetch();
                    if (!$booking) {
                        $pdo->rollBack();
                        return self::json($response, 404, ['success' => false, 'message' => 'Booking not found.']);
                    }
                    if ($booking['booking_status'] !== 'pending_payment' && $booking['booking_status'] !== 'payment_failed') {
                        $pdo->rollBack();
                        return self::json($response, 400, ['success' => false, 'message' => 'Booking is not in a payable state.']);
                    }

                    $isRetry = ($booking['booking_status'] === 'payment_failed');

                    if ($isRetry) {
                        // Re-verify event has enough available seats
                        $stmt = $pdo->prepare('SELECT available_seats FROM events WHERE event_id = ? FOR UPDATE');
                        $stmt->execute([(int)$booking['event_id']]);
                        $event = $stmt->fetch();
                        if (!$event) {
                            $pdo->rollBack();
                            return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                        }
                        if ((int)$event['available_seats'] < (int)$booking['ticket_quantity']) {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Not enough available seats to retry payment.']);
                        }

                        // Re-decrement seats for the retry attempt
                        $stmt = $pdo->prepare('UPDATE events SET available_seats = available_seats - ? WHERE event_id = ?');
                        $stmt->execute([(int)$booking['ticket_quantity'], (int)$booking['event_id']]);
                    }

                    // Simulate payment outcome (90% success)
                    $paymentSuccess = (mt_rand() / mt_getrandmax()) > 0.1;
                    $paymentStatus = $paymentSuccess ? 'successful' : 'failed';

                    // Create payment record
                    $stmt = $pdo->prepare(
                        'INSERT INTO payments (booking_id, user_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $bookingId, $userId,
                        (float)$booking['amount'],
                        $paymentMethod,
                        $paymentStatus,
                    ]);
                    $paymentId = (int)$pdo->lastInsertId();

                    if ($paymentSuccess) {
                        // Mark booking confirmed
                        $stmt = $pdo->prepare('UPDATE bookings SET booking_status = ? WHERE booking_id = ?');
                        $stmt->execute(['confirmed', $bookingId]);

                        // Get event
                        $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                        $stmt->execute([(int)$booking['event_id']]);
                        $event = $stmt->fetch();

                        if ($event) {
                            // Booking confirmed notification
                            $stmt = $pdo->prepare(
                                'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)'
                            );
                            $stmt->execute([
                                $userId,
                                'Booking Confirmed',
                                "Your booking for {$event['title']} has been confirmed.",
                                'success',
                            ]);

                            // Notification
                            $stmt = $pdo->prepare(
                                'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)'
                            );
                            $stmt->execute([
                                $userId,
                                'Payment Successful',
                                "Your payment for {$event['title']} was successful.",
                                'success',
                            ]);

                            // Add to calendar
                            $stmt = $pdo->prepare(
                                'INSERT INTO calendar_events (user_id, event_id, title, calendar_date, start_time, end_time, venue)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE
                                    title = VALUES(title),
                                    calendar_date = VALUES(calendar_date),
                                    start_time = VALUES(start_time),
                                    end_time = VALUES(end_time),
                                    venue = VALUES(venue)'
                            );
                            $stmt->execute([
                                $userId,
                                (int)$booking['event_id'],
                                $event['title'],
                                $event['event_date'],
                                $event['start_time'],
                                $event['end_time'] ?? '',
                                $event['venue'],
                            ]);
                        }
                    } else {
                        // Mark payment_failed
                        $stmt = $pdo->prepare('UPDATE bookings SET booking_status = ? WHERE booking_id = ?');
                        $stmt->execute(['payment_failed', $bookingId]);

                        $stmt = $pdo->prepare('SELECT title FROM events WHERE event_id = ?');
                        $stmt->execute([(int)$booking['event_id']]);
                        $event = $stmt->fetch();
                        $eventTitle = $event ? (string)$event['title'] : 'the selected event';

                        // Restore available seats since payment failed!
                        $stmt = $pdo->prepare('UPDATE events SET available_seats = available_seats + ? WHERE event_id = ?');
                        $stmt->execute([(int)$booking['ticket_quantity'], (int)$booking['event_id']]);

                        // Notification
                        $stmt = $pdo->prepare(
                            'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $userId,
                            'Payment Failed',
                            "Your payment for {$eventTitle} could not be processed.",
                            'warning',
                        ]);
                    }

                    $pdo->commit();

                    return self::json($response, 201, [
                        'success'      => true,
                        'paymentId'    => $paymentId,
                        'paymentStatus'=> $paymentStatus,
                        'message'      => $paymentSuccess
                            ? 'Payment successful! Booking confirmed.'
                            : 'Payment failed. Please try again.',
                    ]);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[Payment] error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Payment processing failed.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── GET /api/payments/user/{userId} ────────────────────────────
            $g->get('/user/{userId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$args['userId'];

                if ($userId !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare(
                    'SELECT p.*, e.title as event_title
                     FROM payments p
                     JOIN bookings b ON p.booking_id = b.booking_id
                     JOIN events e ON b.event_id = e.event_id
                     WHERE p.user_id = ?
                     ORDER BY p.payment_date DESC'
                );
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll();

                $payments = array_map(function ($p) {
                    return [
                        'paymentId'     => (int)$p['payment_id'],
                        'bookingId'     => (int)$p['booking_id'],
                        'userId'        => (int)$p['user_id'],
                        'amount'        => (float)$p['amount'],
                        'paymentMethod' => $p['payment_method'],
                        'paymentStatus' => $p['payment_status'],
                        'paymentDate'   => $p['payment_date'],
                        'eventTitle'    => $p['event_title'],
                    ];
                }, $rows);

                return self::json($response, 200, ['success' => true, 'payments' => $payments]);
                } catch (\Throwable $e) {
                    error_log('[Payment] GET /user/{userId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch payment history.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());
        });
    }
}
