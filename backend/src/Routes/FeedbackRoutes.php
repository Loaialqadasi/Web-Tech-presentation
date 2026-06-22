<?php
declare(strict_types=1);

namespace App\Routes;

use App\Database;
use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Feedback routes — mirrors api/routes/feedback.js
 *   GET    /api/feedback
 *   GET    /api/feedback/event/{eventId}
 *   POST   /api/feedback              (auth)
 *   DELETE /api/feedback/{id}         (auth, owner|admin)
 */
final class FeedbackRoutes
{
    use JsonResponder;

    public function __invoke(App $app): void
    {
        $app->group('/api/feedback', function (RouteCollectorProxy $g): void {

            // ─── GET /api/feedback/event/{eventId} ──────────────────────────
            // (Declared BEFORE /{id} so /event/{id} wins on that prefix.)
            $g->get('/event/{eventId}', function (Request $request, Response $response, array $args): Response {
                $eventId = (int)$args['eventId'];
                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM feedback WHERE event_id = ? ORDER BY created_at DESC');
                $stmt->execute([$eventId]);
                $rows = $stmt->fetchAll();

                $feedback = array_map(fn($f) => self::shapeFeedback($f), $rows);
                return self::json($response, 200, ['success' => true, 'feedback' => $feedback]);
                } catch (\Throwable $e) {
                    error_log('[Feedback] GET /event/{eventId} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch event feedback.']);
                }
            });

            // ─── GET /api/feedback ──────────────────────────────────────────
            $g->get('', function (Request $request, Response $response): Response {
                try {
                $pdo = Database::pdo();
                $rows = $pdo->query('SELECT * FROM feedback ORDER BY created_at DESC')->fetchAll();
                $feedback = array_map(fn($f) => self::shapeFeedback($f), $rows);
                return self::json($response, 200, ['success' => true, 'feedback' => $feedback]);
                } catch (\Throwable $e) {
                    error_log('[Feedback] GET / error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch feedback.']);
                }
            });

            // Alternative path for GET /api/feedback/  (with trailing slash)
            $g->get('/', function (Request $request, Response $response): Response {
                try {
                $pdo = Database::pdo();
                $rows = $pdo->query('SELECT * FROM feedback ORDER BY created_at DESC')->fetchAll();
                $feedback = array_map(fn($f) => self::shapeFeedback($f), $rows);
                return self::json($response, 200, ['success' => true, 'feedback' => $feedback]);
                } catch (\Throwable $e) {
                    error_log('[Feedback] GET / error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch feedback.']);
                }
            });

            // ─── POST /api/feedback ─────────────────────────────────────────
            $g->post('', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $b = (array)$request->getParsedBody();
                $eventId = (int)($b['eventId'] ?? 0);
                $rating  = (int)($b['rating']  ?? 0);
                $review  = trim((string)($b['review'] ?? ''));

                if ($eventId <= 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Please select an event.']);
                }
                if ($rating < 1 || $rating > 5) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Rating must be between 1 and 5.']);
                }
                if (strlen($review) < 10) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Review must be at least 10 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT event_id FROM events WHERE event_id = ?');
                $stmt->execute([$eventId]);
                if (!$stmt->fetch()) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Event not found.']);
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO feedback (user_id, event_id, rating, review, author) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([(int)$user['user_id'], $eventId, $rating, $review, $user['name']]);
                $feedbackId = (int)$pdo->lastInsertId();

                return self::json($response, 201, [
                    'success'    => true,
                    'message'    => 'Feedback submitted successfully!',
                    'feedbackId' => $feedbackId,
                    'feedback'   => [
                        'feedbackId' => $feedbackId,
                        'userId'     => (int)$user['user_id'],
                        'eventId'    => $eventId,
                        'rating'     => $rating,
                        'review'     => $review,
                        'user'       => $user['name'],
                        'createdAt'  => date('c'),
                    ],
                ]);
                } catch (\Throwable $e) {
                    error_log('[Feedback] POST / error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to submit feedback.']);
                }
            })->add(new AuthMiddleware());

            // ─── PUT /api/feedback/{id} ─────────────────────────────────────
            $g->put('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];
                $b = (array)$request->getParsedBody();

                $rating  = (int)($b['rating']  ?? 0);
                $review  = trim((string)($b['review'] ?? ''));

                if ($rating < 1 || $rating > 5) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Rating must be between 1 and 5.']);
                }
                if (strlen($review) < 10) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Review must be at least 10 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM feedback WHERE feedback_id = ?');
                $stmt->execute([$id]);
                $f = $stmt->fetch();
                if (!$f) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Feedback not found.']);
                }
                if ((int)$f['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'You can only edit your own feedback.']);
                }

                $stmt = $pdo->prepare(
                    'UPDATE feedback SET rating = ?, review = ? WHERE feedback_id = ?'
                );
                $stmt->execute([$rating, $review, $id]);

                return self::json($response, 200, [
                    'success' => true,
                    'message' => 'Feedback updated successfully!',
                ]);
                } catch (\Throwable $e) {
                    error_log('[Feedback] PUT /{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update feedback.']);
                }
            })->add(new AuthMiddleware());

            // ─── DELETE /api/feedback/{id} ──────────────────────────────────
            $g->delete('/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM feedback WHERE feedback_id = ?');
                $stmt->execute([$id]);
                $f = $stmt->fetch();
                if (!$f) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Feedback not found.']);
                }

                // Check if the user is the organizer of the event
                $stmtEvent = $pdo->prepare('SELECT organizer_id FROM events WHERE event_id = ?');
                $stmtEvent->execute([(int)$f['event_id']]);
                $event = $stmtEvent->fetch();
                $isOrganizer = $event && (int)$event['organizer_id'] === (int)$user['user_id'];

                $isOwner = (int)$f['user_id'] === (int)$user['user_id'];
                $isAdmin = $user['role'] === 'admin';

                if (!$isOwner && !$isAdmin && !$isOrganizer) {
                    return self::json($response, 403, ['success' => false, 'message' => 'You are not authorized to moderate this feedback.']);
                }

                $stmt = $pdo->prepare('DELETE FROM feedback WHERE feedback_id = ?');
                $stmt->execute([$id]);
                return self::json($response, 200, ['success' => true, 'message' => 'Feedback deleted!']);
                } catch (\Throwable $e) {
                    error_log('[Feedback] DELETE /{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete feedback.']);
                }
            })->add(new AuthMiddleware());
        });
    }

    private static function shapeFeedback(array $f): array
    {
        return [
            'feedbackId' => (int)$f['feedback_id'],
            'userId'     => (int)$f['user_id'],
            'eventId'    => (int)$f['event_id'],
            'rating'     => (int)$f['rating'],
            'review'     => $f['review'],
            'user'       => $f['author'],
            'createdAt'  => $f['created_at'],
        ];
    }
}
