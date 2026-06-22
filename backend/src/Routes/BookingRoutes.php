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
 * Booking routes — mirrors api/routes/bookings.js
 *   GET    /api/bookings/user/{userId}   (auth)
 *   POST   /api/bookings                 (auth, student)
 *   PUT    /api/bookings/{id}            (auth)
 *   DELETE /api/bookings/{id}            (auth)
 *
 * NOTE: Booking creation is wrapped in a PDO transaction so that seats,
 * bookings, notifications, and calendar_events are committed atomically.
 */
final class BookingRoutes
{
    use JsonResponder;

    public function __invoke(App $app): void
    {
        $app->group('/api/bookings', function (RouteCollectorProxy $g): void {

            // ─── GET /api/bookings/user/{userId} ────────────────────────────
            $g->get('/user/{userId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$args['userId'];

                if ($userId !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare(
                    'SELECT b.*, e.title, e.event_date, e.start_time, e.end_time, e.venue, e.image_url, e.price, e.category
                     FROM bookings b
                     JOIN events e ON b.event_id = e.event_id
                     WHERE b.user_id = ?
                     ORDER BY b.booking_date DESC'
                );
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll();

                $bookings = array_map(function ($b) {
                    return [
                        'bookingId'      => (int)$b['booking_id'],
                        'userId'         => (int)$b['user_id'],
                        'eventId'        => (int)$b['event_id'],
                        'ticketQuantity' => (int)$b['ticket_quantity'],
                        'bookingStatus'  => $b['booking_status'],
                        'bookingDate'    => $b['booking_date'],
                        'amount'         => (float)$b['amount'],
                        'event' => [
                            'id'       => (int)$b['event_id'],
                            'title'    => $b['title'],
                            'date'     => self::dateOnly($b['event_date']),
                            'time'     => $b['start_time'] . (!empty($b['end_time']) ? ' - ' . $b['end_time'] : ''),
                            'startTime'=> $b['start_time'],
                            'venue'    => $b['venue'],
                            'image'    => $b['image_url'],
                            'price'    => $b['price'],
                            'category' => $b['category'],
                        ],
                    ];
                }, $rows);

                return self::json($response, 200, ['success' => true, 'bookings' => $bookings]);
                } catch (\Throwable $e) {
                    error_log('[Booking] GET /user/{userId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch bookings.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── POST /api/bookings ─────────────────────────────────────────
            $g->post('', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$user['user_id'];
                $b = (array)$request->getParsedBody();

                $eventId = (int)($b['eventId'] ?? 0);
                $quantity = (int)($b['ticketQuantity'] ?? 1);
                if ($quantity < 1) $quantity = 1;

                if ($eventId <= 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Event ID is required.']);
                }
                if ($quantity < 1 || $quantity > 10) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Ticket quantity must be between 1 and 10.']);
                }

                $pdo = Database::pdo();

                try {
                    $pdo->beginTransaction();

                    // Check event
                    $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    $event = $stmt->fetch();
                    if (!$event) {
                        $pdo->rollBack();
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }
                    if ((int)$event['available_seats'] < $quantity) {
                        $pdo->rollBack();
                        return self::json($response, 400, ['success' => false, 'message' => 'Not enough available seats.']);
                    }

                    // Check existing active booking
                    $stmt = $pdo->prepare(
                        'SELECT booking_id FROM bookings WHERE user_id = ? AND event_id = ? AND booking_status NOT IN ("cancelled", "payment_failed")'
                    );
                    $stmt->execute([$userId, $eventId]);
                    if ($stmt->fetch()) {
                        $pdo->rollBack();
                        return self::json($response, 409, ['success' => false, 'message' => 'You already have an active booking for this event.']);
                    }

                    // Calculate amount (RM xx -> qty * x)
                    $priceStr = (string)$event['price'];
                    $amount = 0.0;
                    if ($priceStr !== '' && $priceStr !== 'Free' && $priceStr !== 'Free Entry') {
                        if (preg_match('/RM\s*(\d+)/i', $priceStr, $m)) {
                            $amount = (float)$m[1] * $quantity;
                        }
                    }

                    // Decrease available seats (with guard)
                    $stmt = $pdo->prepare('UPDATE events SET available_seats = available_seats - ? WHERE event_id = ? AND available_seats >= ?');
                    $stmt->execute([$quantity, $eventId, $quantity]);

                    // Create booking
                    $status = $amount > 0 ? 'pending_payment' : 'confirmed';
                    $stmt = $pdo->prepare(
                        'INSERT INTO bookings (user_id, event_id, ticket_quantity, booking_status, amount) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $eventId, $quantity, $status, $amount]);
                    $bookingId = (int)$pdo->lastInsertId();

                    // For free events: booking is immediately confirmed.
                    if ($amount == 0) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $userId,
                            'Booking Confirmed',
                            "Your booking for {$event['title']} has been confirmed.",
                            'success',
                        ]);
                    }

                    $pdo->commit();

                    return self::json($response, 201, [
                        'success'       => true,
                        'message'       => $amount > 0 ? 'Booking created. Payment pending.' : 'Booking confirmed!',
                        'bookingId'     => $bookingId,
                        'amount'        => $amount,
                        'bookingStatus' => $status,
                    ]);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[Booking] create error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to create booking.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── PUT /api/bookings/{id} ─────────────────────────────────────
            $g->put('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $bookingId = (int)$args['id'];
                $b = (array)$request->getParsedBody();

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ?');
                $stmt->execute([$bookingId]);
                $booking = $stmt->fetch();

                if (!$booking) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Booking not found.']);
                }
                if ((int)$booking['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                $updates = [];
                $values  = [];

                if (isset($b['ticketQuantity'])) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Booking ticket quantity cannot be modified after creation.']);
                }
                if (isset($b['bookingStatus'])) {
                    $updates[] = 'booking_status = ?'; $values[] = $b['bookingStatus'];
                }

                if (count($updates) === 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'No fields to update.']);
                }

                $values[] = $bookingId;
                $sql = 'UPDATE bookings SET ' . implode(', ', $updates) . ' WHERE booking_id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return self::json($response, 200, ['success' => true, 'message' => 'Booking updated successfully!']);
                } catch (\Throwable $e) {
                    error_log('[Booking] PUT /{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update booking.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── DELETE /api/bookings/{id} ──────────────────────────────────
            $g->delete('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $bookingId = (int)$args['id'];

                $pdo = Database::pdo();

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ?');
                    $stmt->execute([$bookingId]);
                    $booking = $stmt->fetch();

                    if (!$booking) {
                        $pdo->rollBack();
                        return self::json($response, 404, ['success' => false, 'message' => 'Booking not found.']);
                    }
                    if ((int)$booking['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        $pdo->rollBack();
                        return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                    }

                    // Restore available seats only if they were currently held (i.e. confirmed or pending_payment)
                    if ($booking['booking_status'] === 'confirmed' || $booking['booking_status'] === 'pending_payment') {
                        $stmt = $pdo->prepare('UPDATE events SET available_seats = available_seats + ? WHERE event_id = ?');
                        $stmt->execute([(int)$booking['ticket_quantity'], (int)$booking['event_id']]);
                    }

                    // Mark cancelled
                    $stmt = $pdo->prepare('UPDATE bookings SET booking_status = ? WHERE booking_id = ?');
                    $stmt->execute(['cancelled', $bookingId]);

                    // Delete related calendar event
                    $stmt = $pdo->prepare('DELETE FROM calendar_events WHERE user_id = ? AND event_id = ?');
                    $stmt->execute([(int)$booking['user_id'], (int)$booking['event_id']]);

                    // Notification
                    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, ?)');
                    $stmt->execute([
                        (int)$booking['user_id'],
                        'Booking Cancelled',
                        'Your booking has been cancelled.',
                        'warning',
                    ]);

                    $pdo->commit();
                    return self::json($response, 200, ['success' => true, 'message' => 'Booking cancelled successfully!']);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[Booking] delete error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to cancel booking.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());
        });
    }
}
