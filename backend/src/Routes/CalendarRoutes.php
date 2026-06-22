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
 * Calendar routes — mirrors api/routes/calendar.js
 *   GET    /api/calendar/user/{userId}   (auth)
 *   POST   /api/calendar                 (auth)
 *   PUT    /api/calendar/{id}            (auth)
 *   DELETE /api/calendar/{id}            (auth)
 */
final class CalendarRoutes
{
    use JsonResponder;

    private const REMINDER_WINDOW_DAYS = 14;

    public function __invoke(App $app): void
    {
        $app->group('/api/calendar', function (RouteCollectorProxy $g): void {

            // ─── GET /api/calendar/user/{userId} ────────────────────────────
            $g->get('/user/{userId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$args['userId'];

                if ($userId !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                }

                try {
                    $pdo = Database::pdo();

                    // Personalized calendar: only events the user has confirmed and paid for.
                    // Simplified query — avoid correlated subquery in LEFT JOIN which
                    // triggers a Slim 500 on some MySQL / TiDB versions.
                    $stmt = $pdo->prepare(
                        'SELECT ce.calendar_id, ce.user_id, ce.event_id, ce.title,
                                ce.calendar_date, ce.start_time, ce.end_time, ce.venue,
                                e.description, e.category, e.image_url, e.organizer_id,
                                u.name AS organizer_name
                         FROM calendar_events ce
                         JOIN events e ON ce.event_id = e.event_id
                         LEFT JOIN users u ON e.organizer_id = u.user_id
                         WHERE ce.user_id = ?
                           AND EXISTS (
                               SELECT 1
                               FROM bookings bb
                               JOIN payments pp ON pp.booking_id = bb.booking_id
                               WHERE bb.user_id = ce.user_id
                                 AND bb.event_id = ce.event_id
                                 AND bb.booking_status = "confirmed"
                                 AND pp.payment_status = "successful"
                           )
                         ORDER BY ce.calendar_date ASC, ce.start_time ASC'
                    );
                    $stmt->execute([$userId]);
                    $rows = $stmt->fetchAll();

                    $events = array_map(function ($e) {
                        return [
                            'calendarId' => (int)$e['calendar_id'],
                            'userId'     => (int)$e['user_id'],
                            'eventId'    => (int)$e['event_id'],
                            'title'      => $e['title'],
                            'date'       => self::dateOnly($e['calendar_date']),
                            'startTime'  => $e['start_time'],
                            'endTime'    => $e['end_time'],
                            'venue'      => $e['venue'],
                            'description'=> $e['description'] ?? '',
                            'category'   => $e['category'] ?? '',
                            'organizer'  => $e['organizer_name'] ?? '',
                            'organizerId'=> isset($e['organizer_id']) ? (int)$e['organizer_id'] : null,
                            'bookingStatus' => 'confirmed',
                            'paymentStatus' => 'successful',
                            'image'         => $e['image_url'] ?? '',
                        ];
                    }, $rows);

                    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
                    $upcomingEvents = array_values(array_filter($events, fn($e) => $e['date'] >= $today));

                    $bookedEventIds = array_values(array_unique(array_map(fn($e) => (int)$e['eventId'], $events)));
                    $highlightedDates = array_values(array_unique(array_map(fn($e) => (string)$e['date'], $events)));

                    $allStmt = $pdo->prepare(
                        'SELECT event_id, title, event_date, start_time, end_time, venue, image_url
                         FROM events
                         WHERE event_date >= CURDATE()
                         ORDER BY event_date ASC, start_time ASC'
                    );
                    $allStmt->execute();
                    $allRows = $allStmt->fetchAll();

                    $monthlyAllUpcomingEvents = array_map(function ($e) use ($bookedEventIds) {
                        return [
                            'eventId'    => (int)$e['event_id'],
                            'title'      => $e['title'],
                            'date'       => self::dateOnly($e['event_date']),
                            'startTime'  => $e['start_time'],
                            'endTime'    => $e['end_time'],
                            'venue'      => $e['venue'],
                            'image'      => $e['image_url'] ?? '',
                            'booked'     => in_array((int)$e['event_id'], $bookedEventIds, true),
                        ];
                    }, $allRows);

                    return self::json($response, 200, [
                        'success' => true,
                        'events' => $events, // backward-compatible key
                        'bookedEvents' => $events,
                        'upcomingEvents' => $upcomingEvents,
                        'monthlyCalendarData' => [
                            'highlightedDates' => $highlightedDates,
                            'allUpcomingEvents' => $monthlyAllUpcomingEvents,
                        ],
                        'listViewData' => $events,
                    ]);
                } catch (\Throwable $e) {
                    error_log('[Calendar] GET /user/{userId} error: ' . $e->getMessage());
                    return self::json($response, 500, [
                        'success' => false,
                        'message' => 'Failed to load calendar events.',
                    ]);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── POST /api/calendar/sync ───────────────────────────────────
            $g->post('/sync', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$user['user_id'];
                $pdo = Database::pdo();

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'SELECT b.booking_id, b.user_id, b.event_id, b.booking_status,
                                e.title, e.event_date, e.start_time, e.end_time, e.venue
                         FROM bookings b
                         JOIN events e ON b.event_id = e.event_id
                         WHERE b.user_id = ?
                           AND b.booking_status = "confirmed"
                           AND EXISTS (
                               SELECT 1
                               FROM payments p
                               WHERE p.booking_id = b.booking_id
                                 AND p.payment_status = "successful"
                           )
                         ORDER BY e.event_date ASC, e.start_time ASC'
                    );
                    $stmt->execute([$userId]);
                    $bookings = $stmt->fetchAll();

                    $synchronizedCount = 0;
                    $remindersCreated = 0;

                    foreach ($bookings as $b) {
                        $eventId = (int)$b['event_id'];

                        $existingStmt = $pdo->prepare('SELECT * FROM calendar_events WHERE user_id = ? AND event_id = ? LIMIT 1');
                        $existingStmt->execute([$userId, $eventId]);
                        $existing = $existingStmt->fetch();

                        if (!$existing) {
                            $insertStmt = $pdo->prepare(
                                'INSERT INTO calendar_events (user_id, event_id, title, calendar_date, start_time, end_time, venue)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)'
                            );
                            $insertStmt->execute([
                                $userId,
                                $eventId,
                                $b['title'],
                                $b['event_date'],
                                $b['start_time'] ?? '',
                                $b['end_time'] ?? '',
                                $b['venue'] ?? '',
                            ]);
                            $synchronizedCount++;
                        } else {
                            $needsUpdate =
                                (string)$existing['title'] !== (string)$b['title'] ||
                                self::dateOnly((string)$existing['calendar_date']) !== self::dateOnly((string)$b['event_date']) ||
                                (string)$existing['start_time'] !== (string)($b['start_time'] ?? '') ||
                                (string)$existing['end_time'] !== (string)($b['end_time'] ?? '') ||
                                (string)$existing['venue'] !== (string)($b['venue'] ?? '');

                            if ($needsUpdate) {
                                $updateStmt = $pdo->prepare(
                                    'UPDATE calendar_events
                                     SET title = ?, calendar_date = ?, start_time = ?, end_time = ?, venue = ?
                                     WHERE calendar_id = ?'
                                );
                                $updateStmt->execute([
                                    $b['title'],
                                    $b['event_date'],
                                    $b['start_time'] ?? '',
                                    $b['end_time'] ?? '',
                                    $b['venue'] ?? '',
                                    (int)$existing['calendar_id'],
                                ]);
                                $synchronizedCount++;
                            }
                        }

                        $daysUntil = self::daysUntil((string)$b['event_date']);
                        if ($daysUntil >= 0 && $daysUntil <= self::REMINDER_WINDOW_DAYS) {
                            $title = 'Upcoming Event Reminder';
                            $message = "Your booked event '{$b['title']}' starts in {$daysUntil} day" . ($daysUntil === 1 ? '' : 's') . '.';

                            $reminderExistsStmt = $pdo->prepare(
                                'SELECT notification_id
                                 FROM notifications
                                 WHERE user_id = ?
                                   AND title = ?
                                   AND message = ?
                                   AND DATE(created_at) = CURDATE()
                                 LIMIT 1'
                            );
                            $reminderExistsStmt->execute([$userId, $title, $message]);
                            if (!$reminderExistsStmt->fetch()) {
                                $notifyStmt = $pdo->prepare(
                                    'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, "info")'
                                );
                                $notifyStmt->execute([$userId, $title, $message]);
                                $remindersCreated++;
                            }
                        }
                    }

                    $syncTitle = 'Calendar Synced';
                    $syncMessage = "{$synchronizedCount} booked events were successfully synchronized to your calendar.";
                    $syncNotifyStmt = $pdo->prepare(
                        'INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, ?, ?, "success")'
                    );
                    $syncNotifyStmt->execute([$userId, $syncTitle, $syncMessage]);

                    $pdo->commit();

                    return self::json($response, 200, [
                        'success' => true,
                        'message' => $syncMessage,
                        'synchronizedEvents' => $synchronizedCount,
                        'remindersCreated' => $remindersCreated,
                    ]);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[Calendar] sync error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to synchronize calendar.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── POST /api/calendar ─────────────────────────────────────────
            $g->post('', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $b = (array)$request->getParsedBody();
                $eventId   = (int)($b['eventId']   ?? 0);
                $title     = (string)($b['title']     ?? '');
                $date      = (string)($b['date']      ?? '');
                $startTime = (string)($b['startTime'] ?? '');
                $endTime   = (string)($b['endTime']   ?? '');
                $venue     = (string)($b['venue']     ?? '');

                if ($eventId <= 0 || $title === '' || $date === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Event ID, title, and date are required.']);
                }

                try {
                    $pdo = Database::pdo();

                    // Only allow calendar insertions for confirmed + paid bookings.
                    $paidStmt = $pdo->prepare(
                        'SELECT bb.booking_id
                         FROM bookings bb
                         WHERE bb.user_id = ?
                             AND bb.event_id = ?
                             AND bb.booking_status = "confirmed"
                             AND EXISTS (
                                 SELECT 1
                                 FROM payments pp
                                 WHERE pp.booking_id = bb.booking_id
                                     AND pp.payment_status = "successful"
                             )
                         LIMIT 1'
                    );
                    $paidStmt->execute([(int)$user['user_id'], $eventId]);
                    if (!$paidStmt->fetch()) {
                        return self::json($response, 403, [
                            'success' => false,
                            'message' => 'Calendar entries are only allowed for confirmed and paid bookings.',
                        ]);
                    }

                    // Check existing
                    $stmt = $pdo->prepare('SELECT calendar_id FROM calendar_events WHERE user_id = ? AND event_id = ?');
                    $stmt->execute([(int)$user['user_id'], $eventId]);
                    if ($stmt->fetch()) {
                        return self::json($response, 409, ['success' => false, 'message' => 'Event already in calendar.']);
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO calendar_events (user_id, event_id, title, calendar_date, start_time, end_time, venue) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        (int)$user['user_id'], $eventId, $title, $date,
                        $startTime, $endTime, $venue,
                    ]);
                    $calendarId = (int)$pdo->lastInsertId();

                    return self::json($response, 201, [
                        'success'    => true,
                        'message'    => 'Event added to calendar!',
                        'calendarId' => $calendarId,
                    ]);
                } catch (\Throwable $e) {
                    error_log('[Calendar] POST error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to add calendar event.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── PUT /api/calendar/{id} ─────────────────────────────────────
            $g->put('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];
                $b = (array)$request->getParsedBody();

                try {
                    $pdo = Database::pdo();
                    $stmt = $pdo->prepare('SELECT user_id FROM calendar_events WHERE calendar_id = ?');
                    $stmt->execute([$id]);
                    $calendar = $stmt->fetch();
                    if (!$calendar) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Calendar event not found.']);
                    }
                    if ((int)$calendar['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                    }

                    $updates = [];
                    $values  = [];

                    if (isset($b['title']))     { $updates[] = 'title = ?';          $values[] = $b['title']; }
                    if (isset($b['date']))      { $updates[] = 'calendar_date = ?';  $values[] = $b['date']; }
                    if (isset($b['startTime'])) { $updates[] = 'start_time = ?';     $values[] = $b['startTime']; }
                    if (isset($b['endTime']))   { $updates[] = 'end_time = ?';       $values[] = $b['endTime']; }
                    if (isset($b['venue']))     { $updates[] = 'venue = ?';          $values[] = $b['venue']; }

                    if (count($updates) === 0) {
                        return self::json($response, 400, ['success' => false, 'message' => 'No fields to update.']);
                    }

                    $values[] = $id;
                    $sql = 'UPDATE calendar_events SET ' . implode(', ', $updates) . ' WHERE calendar_id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);

                    return self::json($response, 200, ['success' => true, 'message' => 'Calendar event updated!']);
                } catch (\Throwable $e) {
                    error_log('[Calendar] PUT error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update calendar event.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());

            // ─── DELETE /api/calendar/{id} ──────────────────────────────────
            $g->delete('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];

                try {
                    $pdo = Database::pdo();

                    $stmt = $pdo->prepare('SELECT user_id FROM calendar_events WHERE calendar_id = ?');
                    $stmt->execute([$id]);
                    $calendar = $stmt->fetch();
                    if (!$calendar) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Calendar event not found.']);
                    }
                    if ((int)$calendar['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        return self::json($response, 403, ['success' => false, 'message' => 'Access denied.']);
                    }

                    $stmt = $pdo->prepare('DELETE FROM calendar_events WHERE calendar_id = ?');
                    $stmt->execute([$id]);
                    return self::json($response, 200, ['success' => true, 'message' => 'Calendar event removed.']);
                } catch (\Throwable $e) {
                    error_log('[Calendar] DELETE error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to remove calendar event.']);
                }
            })->add(new RoleMiddleware('student'))->add(new AuthMiddleware());
        });
    }

    private static function daysUntil(string $date): int
    {
        $target = new \DateTimeImmutable(self::dateOnly($date));
        $today = new \DateTimeImmutable('today');
        return (int)$today->diff($target)->format('%r%a');
    }
}
