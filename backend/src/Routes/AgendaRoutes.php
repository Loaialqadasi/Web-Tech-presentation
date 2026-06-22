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
 * Event Agenda routes
 *   GET    /api/events/{eventId}/agenda              List agenda items for an event
 *   POST   /api/events/{eventId}/agenda              (organizer|admin) Create agenda item
 *   PUT    /api/events/{eventId}/agenda/{agendaId}   (organizer|admin) Update agenda item
 *   DELETE /api/events/{eventId}/agenda/{agendaId}   (organizer|admin) Delete agenda item
 */
final class AgendaRoutes
{
    use JsonResponder;

    public function __invoke(App $app): void
    {
        $app->group('/api/events/{eventId:[0-9]+}/agenda', function (RouteCollectorProxy $g): void {

            // ─── GET /api/events/{eventId}/agenda ─────────────────────────────
            $g->get('', function (Request $request, Response $response, array $args): Response {
                $eventId = (int)$args['eventId'];

                try {
                    $pdo = Database::pdo();

                    // Verify event exists
                    $stmt = $pdo->prepare('SELECT event_id FROM events WHERE event_id = ?');
                    $stmt->execute([$eventId]);
                    if (!$stmt->fetch()) {
                        return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                    }

                    $stmt = $pdo->prepare(
                        'SELECT * FROM event_agenda_items WHERE event_id = ? ORDER BY start_time ASC'
                    );
                    $stmt->execute([$eventId]);
                    $rows = $stmt->fetchAll();

                    $agendaItems = array_map(function ($item) {
                        return [
                            'agendaId'    => (int)$item['agenda_id'],
                            'eventId'     => (int)$item['event_id'],
                            'title'       => $item['title'],
                            'description' => $item['description'] ?? '',
                            'startTime'   => $item['start_time'],
                            'endTime'     => $item['end_time'],
                            'createdAt'   => $item['created_at'],
                        ];
                    }, $rows);

                    return self::json($response, 200, ['success' => true, 'agendaItems' => $agendaItems]);
                } catch (\Throwable $e) {
                    error_log('[Agenda] GET error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to load agenda items.']);
                }
            });

            // ─── POST /api/events/{eventId}/agenda ────────────────────────────
            $g->post('', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['eventId'];
                $body = (array)$request->getParsedBody();

                $title       = trim((string)($body['title'] ?? ''));
                $description = trim((string)($body['description'] ?? ''));
                $startTime   = trim((string)($body['startTime'] ?? ''));
                $endTime     = trim((string)($body['endTime'] ?? ''));

                if ($title === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Agenda item title is required.']);
                }
                if ($startTime === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Start time is required.']);
                }
                if ($endTime === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'End time is required.']);
                }

                try {
                    $pdo = Database::pdo();

                // Verify event exists and user is owner or admin
                $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                $stmt->execute([$eventId]);
                $event = $stmt->fetch();
                if (!$event) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                }
                if ((int)$event['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Only the event organizer or admin can manage agenda items.']);
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO event_agenda_items (event_id, title, description, start_time, end_time) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$eventId, $title, $description, $startTime, $endTime]);
                $agendaId = (int)$pdo->lastInsertId();

                return self::json($response, 201, [
                    'success'   => true,
                    'message'   => 'Agenda item created successfully!',
                    'agendaId'  => $agendaId,
                    'agendaItem' => [
                        'agendaId'    => $agendaId,
                        'eventId'     => $eventId,
                        'title'       => $title,
                        'description' => $description,
                        'startTime'   => $startTime,
                        'endTime'     => $endTime,
                    ],
                ]);
                } catch (\Throwable $e) {
                    error_log('[Agenda] POST error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to create agenda item.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── PUT /api/events/{eventId}/agenda/{agendaId} ──────────────────
            $g->put('/{agendaId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['eventId'];
                $agendaId = (int)$args['agendaId'];
                $body = (array)$request->getParsedBody();

                try {
                    $pdo = Database::pdo();

                // Verify event and ownership
                $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                $stmt->execute([$eventId]);
                $event = $stmt->fetch();
                if (!$event) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                }
                if ((int)$event['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Only the event organizer or admin can manage agenda items.']);
                }

                // Verify agenda item exists and belongs to this event
                $stmt = $pdo->prepare('SELECT * FROM event_agenda_items WHERE agenda_id = ? AND event_id = ?');
                $stmt->execute([$agendaId, $eventId]);
                if (!$stmt->fetch()) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Agenda item not found.']);
                }

                $updates = [];
                $values  = [];

                if (isset($body['title']))       { $updates[] = 'title = ?';       $values[] = trim((string)$body['title']); }
                if (isset($body['description'])) { $updates[] = 'description = ?'; $values[] = trim((string)$body['description']); }
                if (isset($body['startTime']))   { $updates[] = 'start_time = ?';  $values[] = trim((string)$body['startTime']); }
                if (isset($body['endTime']))     { $updates[] = 'end_time = ?';    $values[] = trim((string)$body['endTime']); }

                if (count($updates) === 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'No fields to update.']);
                }

                $values[] = $agendaId;
                $sql = 'UPDATE event_agenda_items SET ' . implode(', ', $updates) . ' WHERE agenda_id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return self::json($response, 200, ['success' => true, 'message' => 'Agenda item updated successfully!']);
                } catch (\Throwable $e) {
                    error_log('[Agenda] PUT error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update agenda item.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());

            // ─── DELETE /api/events/{eventId}/agenda/{agendaId} ───────────────
            $g->delete('/{agendaId}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $eventId = (int)$args['eventId'];
                $agendaId = (int)$args['agendaId'];

                try {
                    $pdo = Database::pdo();

                // Verify event and ownership
                $stmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ?');
                $stmt->execute([$eventId]);
                $event = $stmt->fetch();
                if (!$event) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                }
                if ((int)$event['organizer_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'Only the event organizer or admin can manage agenda items.']);
                }

                // Verify agenda item exists and belongs to this event
                $stmt = $pdo->prepare('SELECT * FROM event_agenda_items WHERE agenda_id = ? AND event_id = ?');
                $stmt->execute([$agendaId, $eventId]);
                if (!$stmt->fetch()) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Agenda item not found.']);
                }

                $stmt = $pdo->prepare('DELETE FROM event_agenda_items WHERE agenda_id = ?');
                $stmt->execute([$agendaId]);

                return self::json($response, 200, ['success' => true, 'message' => 'Agenda item deleted successfully!']);
                } catch (\Throwable $e) {
                    error_log('[Agenda] DELETE error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete agenda item.']);
                }
            })->add(new RoleMiddleware('organizer', 'admin'))->add(new AuthMiddleware());
        });
    }
}
