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
 * Event routes — mirrors api/routes/events.js
 *   GET    /api/events
 *   GET    /api/events/categories/list
 *   GET    /api/events/{id}
 *   POST   /api/events                  (organizer|admin)
 *   PUT    /api/events/{id}             (organizer|admin, owner|admin)
 *   DELETE /api/events/{id}             (organizer|admin, owner|admin)
 */
final class EventRoutes
{
    use JsonResponder;

    private const EVENT_CATEGORIES = [
        'Technology',
        'Career',
        'Academic',
        'Workshop',
        'Seminar',
        'Sports',
        'Cultural',
        'Community Service',
        'Arts',
        'Entertainment',
    ];

    private const EVENT_STATUSES = ['open', 'closed', 'cancelled'];

    public function __invoke(App $app): void
    {
        $app->group('/api/events', function (RouteCollectorProxy $g): void {

            // ─── GET /api/events/categories/list (declared BEFORE /{id}) ─────
            $g->get('/categories/list', function (Request $request, Response $response): Response {
                return self::json($response, 200, [
                    'success'    => true,
                    'categories' => array_merge(['All'], self::EVENT_CATEGORIES),
                ]);
            });

            // ─── GET /api/events ────────────────────────────────────────────
            $g->get('', function (Request $request, Response $response): Response {
                $q = $request->getQueryParams();
                $category = $q['category'] ?? null;
                $search   = $q['search']   ?? null;
                $status   = $q['status']   ?? null;

                if ($status !== null && $status !== '' && !in_array($status, self::EVENT_STATUSES, true)) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Invalid status filter.']);
                }

                try {
                    $sql = 'SELECT * FROM events WHERE 1=1';
                    $params = [];

                    if ($category && $category !== 'All') {
                        if (!in_array($category, self::EVENT_CATEGORIES, true)) {
                            return self::json($response, 400, ['success' => false, 'message' => 'Invalid category filter.']);
                        }
                        $sql .= ' AND category = ?';
                        $params[] = $category;
                    }
                    if ($search) {
                        $sql .= ' AND (title LIKE ? OR description LIKE ? OR venue LIKE ?)';
                        $like = "%{$search}%";
                        $params[] = $like; $params[] = $like; $params[] = $like;
                    }
                    if ($status) {
                        $sql .= ' AND status = ?';
                        $params[] = $status;
                    }
                    $sql .= ' ORDER BY event_date ASC';

                    $pdo = Database::pdo();
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll();

                    // Fetch aggregate ratings
                    $ratings = [];
                    $rstmt = $pdo->query('SELECT event_id, AVG(rating) as avg_rating, COUNT(*) as review_count FROM feedback GROUP BY event_id');
                    foreach ($rstmt->fetchAll() as $r) {
                        $ratings[(int)$r['event_id']] = [
                            'avgRating'   => round((float)$r['avg_rating'], 1),
                            'reviewCount' => (int)$r['review_count'],
                        ];
                    }

                    $events = array_map(function ($e) use ($ratings) {
                        return self::shapeEvent($e, $ratings);
                    }, $rows);

                    return self::json($response, 200, ['success' => true, 'events' => $events]);
                } catch (\Throwable $e) {
                    error_log('[Events] GET / error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to load events.']);
                }
            });

            // ─── GET /api/events/{id} ───────────────────────────────────────
            $g->get('/{id}', function (Request $request, Response $response, array $args): Response {
                $id = (int)$args['id'];

                try {
                    $pdo = Database::pdo();
                    $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                    $stmt->execute([$id]);
                    $e = $stmt->fetch();

                    if (!$e) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }

                    $rstmt = $pdo->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM feedback WHERE event_id = ?');
                    $rstmt->execute([$id]);
                    $r = $rstmt->fetch();
                    $avg = $r && $r['avg_rating'] !== null ? round((float)$r['avg_rating'], 1) : 0;
                    $cnt = $r ? (int)$r['review_count'] : 0;

                    // Agenda items — wrapped separately so a missing table still returns the event
                    $agenda = [];
                    try {
                        $astmt = $pdo->prepare(
                            'SELECT agenda_id, title, description, start_time, end_time
                             FROM event_agenda_items
                             WHERE event_id = ?
                             ORDER BY start_time ASC, agenda_id ASC'
                        );
                        $astmt->execute([$id]);
                        $agendaRows = $astmt->fetchAll();

                        $agenda = array_map(fn($a) => [
                            'agendaId' => (int)$a['agenda_id'],
                            'title' => $a['title'],
                            'description' => $a['description'] ?? '',
                            'startTime' => $a['start_time'],
                            'endTime' => $a['end_time'],
                        ], $agendaRows);
                    } catch (\Throwable $agendaErr) {
                        error_log('[Events] agenda query skipped: ' . $agendaErr->getMessage());
                    }

                    $event = self::shapeEvent($e, [$id => ['avgRating' => $avg, 'reviewCount' => $cnt]]);
                    $event['agenda'] = $agenda;

                    return self::json($response, 200, ['success' => true, 'event' => $event]);
                } catch (\Throwable $e) {
                    error_log('[Events] GET /{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to load event details.']);
                }
            });

            // ─── GET /api/events/{id}/agenda ───────────────────────────────
            $g->get('/{id}/agenda', function (Request $request, Response $response, array $args): Response {
                $eventId = (int)$args['id'];
                try {
                    $pdo = Database::pdo();

                    $stmt = $pdo->prepare('SELECT event_id FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    if (!$stmt->fetch()) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }

                    $stmt = $pdo->prepare(
                        'SELECT agenda_id, event_id, title, description, start_time, end_time
                         FROM event_agenda_items
                         WHERE event_id = ?
                         ORDER BY start_time ASC, agenda_id ASC'
                    );
                    $stmt->execute([$eventId]);
                    $rows = $stmt->fetchAll();

                    $agendaItems = array_map(fn($a) => [
                        'agendaId' => (int)$a['agenda_id'],
                        'eventId' => (int)$a['event_id'],
                        'title' => $a['title'],
                        'description' => $a['description'] ?? '',
                        'startTime' => $a['start_time'],
                        'endTime' => $a['end_time'],
                    ], $rows);

                    return self::json($response, 200, ['success' => true, 'agendaItems' => $agendaItems]);
                } catch (\Throwable $e) {
                    error_log('[Events] GET /{id}/agenda error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to load agenda items.']);
                }
            })->add(new AuthMiddleware());

            // ─── POST /api/events/{id}/agenda ──────────────────────────────
            $g->post('/{id}/agenda', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['id'];
                $b = (array)$request->getParsedBody();

                $title = trim((string)($b['title'] ?? ''));
                $description = trim((string)($b['description'] ?? ''));
                $startTime = trim((string)($b['startTime'] ?? ''));
                $endTime = trim((string)($b['endTime'] ?? ''));

                if ($title === '' || $startTime === '' || $endTime === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Title, start time, and end time are required.']);
                }

                try {
                    $pdo = Database::pdo();
                    $stmt = $pdo->prepare('SELECT organizer_id FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    $event = $stmt->fetch();
                    if (!$event) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }
                    if ((int)$event['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        return self::json($response, 403, ['success' => false, 'message' => 'You can only manage agenda for your own events.']);
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO event_agenda_items (event_id, title, description, start_time, end_time)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$eventId, $title, $description, $startTime, $endTime]);

                    return self::json($response, 201, [
                        'success' => true,
                        'message' => 'Agenda item created successfully.',
                        'agendaId' => (int)$pdo->lastInsertId(),
                    ]);
                } catch (\Throwable $e) {
                    error_log('[Events] POST /{id}/agenda error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to create agenda item.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── PUT /api/events/{id}/agenda/{agendaId} ───────────────────
            $g->put('/{id}/agenda/{agendaId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['id'];
                $agendaId = (int)$args['agendaId'];
                $b = (array)$request->getParsedBody();

                try {
                    $pdo = Database::pdo();

                    $stmt = $pdo->prepare('SELECT organizer_id FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    $event = $stmt->fetch();
                    if (!$event) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }
                    if ((int)$event['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        return self::json($response, 403, ['success' => false, 'message' => 'You can only manage agenda for your own events.']);
                    }

                    $stmt = $pdo->prepare('SELECT agenda_id FROM event_agenda_items WHERE agenda_id = ? AND event_id = ?');
                    $stmt->execute([$agendaId, $eventId]);
                    if (!$stmt->fetch()) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Agenda item not found.']);
                    }

                    $updates = [];
                    $values = [];

                    if (isset($b['title'])) {
                        $title = trim((string)$b['title']);
                        if ($title === '') return self::json($response, 400, ['success' => false, 'message' => 'Title cannot be empty.']);
                        $updates[] = 'title = ?';
                        $values[] = $title;
                    }
                    if (isset($b['description'])) {
                        $updates[] = 'description = ?';
                        $values[] = trim((string)$b['description']);
                    }
                    if (isset($b['startTime'])) {
                        $startTime = trim((string)$b['startTime']);
                        if ($startTime === '') return self::json($response, 400, ['success' => false, 'message' => 'Start time cannot be empty.']);
                        $updates[] = 'start_time = ?';
                        $values[] = $startTime;
                    }
                    if (isset($b['endTime'])) {
                        $endTime = trim((string)$b['endTime']);
                        if ($endTime === '') return self::json($response, 400, ['success' => false, 'message' => 'End time cannot be empty.']);
                        $updates[] = 'end_time = ?';
                        $values[] = $endTime;
                    }

                    if (count($updates) === 0) {
                        return self::json($response, 400, ['success' => false, 'message' => 'No fields to update.']);
                    }

                    $values[] = $agendaId;
                    $values[] = $eventId;
                    $sql = 'UPDATE event_agenda_items SET ' . implode(', ', $updates) . ' WHERE agenda_id = ? AND event_id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);

                    return self::json($response, 200, ['success' => true, 'message' => 'Agenda item updated successfully.']);
                } catch (\Throwable $e) {
                    error_log('[Events] PUT /{id}/agenda/{agendaId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update agenda item.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── DELETE /api/events/{id}/agenda/{agendaId} ────────────────
            $g->delete('/{id}/agenda/{agendaId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['id'];
                $agendaId = (int)$args['agendaId'];

                try {
                    $pdo = Database::pdo();

                    $stmt = $pdo->prepare('SELECT organizer_id FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    $event = $stmt->fetch();
                    if (!$event) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }
                    if ((int)$event['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        return self::json($response, 403, ['success' => false, 'message' => 'You can only manage agenda for your own events.']);
                    }

                    $stmt = $pdo->prepare('DELETE FROM event_agenda_items WHERE agenda_id = ? AND event_id = ?');
                    $stmt->execute([$agendaId, $eventId]);
                    if ($stmt->rowCount() === 0) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Agenda item not found.']);
                    }

                    return self::json($response, 200, ['success' => true, 'message' => 'Agenda item deleted successfully.']);
                } catch (\Throwable $e) {
                    error_log('[Events] DELETE /{id}/agenda/{agendaId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete agenda item.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── POST /api/events ───────────────────────────────────────────
            $g->post('', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $b = (array)$request->getParsedBody();

                $title       = trim((string)($b['title']       ?? ''));
                $description = trim((string)($b['description'] ?? ''));
                $category    = (string)($b['category'] ?? '');
                $date        = (string)($b['date'] ?? '');
                $startTime   = (string)($b['startTime'] ?? '9:00 AM');
                $endTime     = (string)($b['endTime'] ?? '5:00 PM');
                $venue       = trim((string)($b['venue'] ?? ''));
                $capacity    = (int)($b['capacity'] ?? 0);
                $price       = (string)($b['price'] ?? 'Free');
                $imageUrl    = (string)($b['imageUrl'] ?? '');

                if ($title === '')       return self::json($response, 400, ['success' => false, 'message' => 'Title is required.']);
                if ($description === '') return self::json($response, 400, ['success' => false, 'message' => 'Description is required.']);
                if ($category === '')    return self::json($response, 400, ['success' => false, 'message' => 'Category is required.']);
                if ($date === '')        return self::json($response, 400, ['success' => false, 'message' => 'Date is required.']);
                if ($venue === '')       return self::json($response, 400, ['success' => false, 'message' => 'Venue is required.']);
                if ($capacity < 1)       return self::json($response, 400, ['success' => false, 'message' => 'Capacity must be at least 1.']);
                if (!in_array($category, self::EVENT_CATEGORIES, true)) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Invalid category.']);
                }
                if (strtotime($date) === false) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Invalid event date.']);
                }

                try {
                    $pdo = Database::pdo();
                    $stmt = $pdo->prepare(
                        'INSERT INTO events (organizer_id, title, description, category, event_date, start_time, end_time, venue, capacity, available_seats, price, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        (int)$user['user_id'], $title, $description, $category, $date,
                        $startTime ?: '9:00 AM', $endTime ?: '5:00 PM',
                        $venue, $capacity, $capacity,
                        $price ?: 'Free', $imageUrl,
                    ]);

                    $eventId = (int)$pdo->lastInsertId();

                    return self::json($response, 201, [
                        'success' => true,
                        'message' => 'Event created successfully!',
                        'eventId' => $eventId,
                    ]);
                } catch (\Throwable $e) {
                    error_log('[Events] POST / error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to create event.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── PUT /api/events/{id} ───────────────────────────────────────
            $g->put('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['id'];
                $b = (array)$request->getParsedBody();

                $pdo = Database::pdo();
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    $existing = $stmt->fetch();

                    if (!$existing) {
                        $pdo->rollBack();
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }
                    if ((int)$existing['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        $pdo->rollBack();
                        return self::json($response, 403, ['success' => false, 'message' => 'You can only edit your own events.']);
                    }

                    $updates = [];
                    $values  = [];
                    $newCapacity = null;

                    if (isset($b['title'])) {
                        $title = trim((string)$b['title']);
                        if ($title === '') {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Title cannot be empty.']);
                        }
                        $updates[] = 'title = ?';
                        $values[] = $title;
                    }

                    if (isset($b['description'])) {
                        $description = trim((string)$b['description']);
                        if ($description === '') {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Description cannot be empty.']);
                        }
                        $updates[] = 'description = ?';
                        $values[] = $description;
                    }

                    if (isset($b['category'])) {
                        $category = (string)$b['category'];
                        if (!in_array($category, self::EVENT_CATEGORIES, true)) {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Invalid category.']);
                        }
                        $updates[] = 'category = ?';
                        $values[] = $category;
                    }

                    if (isset($b['date'])) {
                        $date = (string)$b['date'];
                        if (strtotime($date) === false) {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Invalid event date.']);
                        }
                        $updates[] = 'event_date = ?';
                        $values[] = $date;
                    }

                    if (isset($b['startTime'])) {
                        $updates[] = 'start_time = ?';
                        $values[] = (string)$b['startTime'];
                    }

                    if (isset($b['endTime'])) {
                        $updates[] = 'end_time = ?';
                        $values[] = (string)$b['endTime'];
                    }

                    if (isset($b['venue'])) {
                        $venue = trim((string)$b['venue']);
                        if ($venue === '') {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Venue cannot be empty.']);
                        }
                        $updates[] = 'venue = ?';
                        $values[] = $venue;
                    }

                    if (isset($b['capacity'])) {
                        $newCapacity = (int)$b['capacity'];
                        $attendees = (int)$existing['capacity'] - (int)$existing['available_seats'];
                        if ($newCapacity < max(1, $attendees)) {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Capacity cannot be less than current attendees.']);
                        }
                        $updates[] = 'capacity = ?';
                        $values[] = $newCapacity;
                        $updates[] = 'available_seats = ?';
                        $values[] = $newCapacity - $attendees;
                    }

                    if (isset($b['price'])) {
                        $updates[] = 'price = ?';
                        $values[] = (string)$b['price'];
                    }

                    if (isset($b['imageUrl'])) {
                        $updates[] = 'image_url = ?';
                        $values[] = (string)$b['imageUrl'];
                    }

                    if (isset($b['status'])) {
                        $status = (string)$b['status'];
                        if (!in_array($status, self::EVENT_STATUSES, true)) {
                            $pdo->rollBack();
                            return self::json($response, 400, ['success' => false, 'message' => 'Invalid event status.']);
                        }
                        $updates[] = 'status = ?';
                        $values[] = $status;
                    }

                    if (count($updates) === 0) {
                        $pdo->rollBack();
                        return self::json($response, 400, ['success' => false, 'message' => 'No fields to update.']);
                    }

                    $values[] = $eventId;
                    $sql = 'UPDATE events SET ' . implode(', ', $updates) . ' WHERE event_id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);

                    // Sync related calendar_events
                    $calUpdates = [];
                    $calValues  = [];
                    if (isset($b['title']))     { $calUpdates[] = 'title = ?';          $calValues[] = trim((string)$b['title']); }
                    if (isset($b['date']))      { $calUpdates[] = 'calendar_date = ?';  $calValues[] = $b['date']; }
                    if (isset($b['startTime'])) { $calUpdates[] = 'start_time = ?';     $calValues[] = $b['startTime']; }
                    if (isset($b['endTime']))   { $calUpdates[] = 'end_time = ?';       $calValues[] = $b['endTime']; }
                    if (isset($b['venue']))     { $calUpdates[] = 'venue = ?';          $calValues[] = trim((string)$b['venue']); }
                    if (count($calUpdates) > 0) {
                        $calValues[] = $eventId;
                        $csql = 'UPDATE calendar_events SET ' . implode(', ', $calUpdates) . ' WHERE event_id = ?';
                        $cstmt = $pdo->prepare($csql);
                        $cstmt->execute($calValues);
                    }

                    $pdo->commit();
                    return self::json($response, 200, ['success' => true, 'message' => 'Event updated successfully!']);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[Event] update error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update event.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── DELETE /api/events/{id} ────────────────────────────────────
            $g->delete('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['id'];

                $pdo = Database::pdo();
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    $existing = $stmt->fetch();

                    if (!$existing) {
                        $pdo->rollBack();
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }
                    if ((int)$existing['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                        $pdo->rollBack();
                        return self::json($response, 403, ['success' => false, 'message' => 'You can only delete your own events.']);
                    }

                    // Explicit cleanup (in addition to FK cascade) keeps flow deterministic.
                    $stmt = $pdo->prepare('DELETE FROM calendar_events WHERE event_id = ?');
                    $stmt->execute([$eventId]);

                    $stmt = $pdo->prepare('DELETE FROM event_agenda_items WHERE event_id = ?');
                    $stmt->execute([$eventId]);

                    $stmt = $pdo->prepare('DELETE FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);

                    $pdo->commit();
                    return self::json($response, 200, ['success' => true, 'message' => 'Event deleted successfully!']);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[Event] delete error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete event.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());
        });
    }

    /**
     * Map a DB row to the JSON event object the client expects.
     */
    private static function shapeEvent(array $e, array $ratings): array
    {
        $id = (int)$e['event_id'];
        $avg = $ratings[$id]['avgRating']   ?? 0;
        $cnt = $ratings[$id]['reviewCount'] ?? 0;
        $capacity = (int)$e['capacity'];
        $available = (int)$e['available_seats'];

        return [
            'id'             => $id,
            'organizerId'    => (int)$e['organizer_id'],
            'title'          => $e['title'],
            'description'    => $e['description'],
            'category'       => $e['category'],
            'date'           => self::dateOnly($e['event_date']),
            'startTime'      => $e['start_time'],
            'endTime'        => $e['end_time'],
            'time'           => $e['start_time'] . (!empty($e['end_time']) ? ' - ' . $e['end_time'] : ''),
            'venue'          => $e['venue'],
            'capacity'       => $capacity,
            'availableSeats' => $available,
            'price'          => $e['price'],
            'image'          => $e['image_url'],
            'imageUrl'       => $e['image_url'],
            'status'         => $e['status'],
            'createdAt'      => $e['created_at'] ?? null,
            'attendees'      => $capacity - $available,
            'avgRating'      => $avg,
            'reviewCount'    => $cnt,
        ];
    }
}
