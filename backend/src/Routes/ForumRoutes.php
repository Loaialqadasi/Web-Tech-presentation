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
 * Forum routes — mirrors api/routes/forum.js
 *   GET    /api/forum/posts
 *   GET    /api/forum/posts/{id}
 *   POST   /api/forum/posts                (auth)
 *   DELETE /api/forum/posts/{id}           (auth, owner|admin)
 *   GET    /api/forum/posts/{id}/comments
 *   POST   /api/forum/comments             (auth)
 *   DELETE /api/forum/comments/{id}        (auth, owner|admin)
 */
final class ForumRoutes
{
    use JsonResponder;

    public function __invoke(App $app): void
    {
        $app->group('/api/forum', function (RouteCollectorProxy $g): void {

            // ─── GET /api/forum/posts ───────────────────────────────────────
            $g->get('/posts', function (Request $request, Response $response): Response {
                $q = $request->getQueryParams();
                $eventId = $q['eventId'] ?? null;

                $sql = 'SELECT * FROM forum_posts';
                $params = [];
                if ($eventId) {
                    $sql .= ' WHERE event_id = ?';
                    $params[] = (int)$eventId;
                }
                $sql .= ' ORDER BY created_at DESC';

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                $cstmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM comments WHERE post_id = ?');

                $posts = array_map(function ($p) use ($cstmt) {
                    $cstmt->execute([(int)$p['post_id']]);
                    $cnt = (int)$cstmt->fetch()['cnt'];
                    return [
                        'postId'       => (int)$p['post_id'],
                        'userId'       => (int)$p['user_id'],
                        'eventId'      => $p['event_id'] !== null ? (int)$p['event_id'] : null,
                        'title'        => $p['title'],
                        'content'      => $p['content'],
                        'author'       => $p['author'],
                        'createdAt'    => $p['created_at'],
                        'commentCount' => $cnt,
                    ];
                }, $rows);

                return self::json($response, 200, ['success' => true, 'posts' => $posts]);
                } catch (\Throwable $e) {
                    error_log('[Forum] GET /posts error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch forum posts.']);
                }
            });

            // ─── GET /api/forum/posts/{id}/comments ─────────────────────────
            // (Declared before /posts/{id} so /comments route wins on /{id}/comments)
            $g->get('/posts/{id}/comments', function (Request $request, Response $response, array $args): Response {
                $id = (int)$args['id'];
                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM comments WHERE post_id = ? ORDER BY created_at ASC');
                $stmt->execute([$id]);
                $rows = $stmt->fetchAll();

                $comments = array_map(function ($c) {
                    return [
                        'commentId' => (int)$c['comment_id'],
                        'postId'    => (int)$c['post_id'],
                        'userId'    => (int)$c['user_id'],
                        'content'   => $c['comment_text'],
                        'author'    => $c['author'],
                        'createdAt' => $c['created_at'],
                    ];
                }, $rows);

                return self::json($response, 200, ['success' => true, 'comments' => $comments]);
                } catch (\Throwable $e) {
                    error_log('[Forum] GET /posts/{id}/comments error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch comments.']);
                }
            });

            // ─── GET /api/forum/posts/{id} ──────────────────────────────────
            $g->get('/posts/{id}', function (Request $request, Response $response, array $args): Response {
                $id = (int)$args['id'];
                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM forum_posts WHERE post_id = ?');
                $stmt->execute([$id]);
                $p = $stmt->fetch();

                if (!$p) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Post not found.']);
                }

                return self::json($response, 200, [
                    'success' => true,
                    'post' => [
                        'postId'    => (int)$p['post_id'],
                        'userId'    => (int)$p['user_id'],
                        'eventId'   => $p['event_id'] !== null ? (int)$p['event_id'] : null,
                        'title'     => $p['title'],
                        'content'   => $p['content'],
                        'author'    => $p['author'],
                        'createdAt' => $p['created_at'],
                    ],
                ]);
                } catch (\Throwable $e) {
                    error_log('[Forum] GET /posts/{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch post.']);
                }
            });

            // ─── POST /api/forum/posts ──────────────────────────────────────
            $g->post('/posts', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $b = (array)$request->getParsedBody();

                $title   = trim((string)($b['title']   ?? ''));
                $content = trim((string)($b['content'] ?? ''));
                $eventId = isset($b['eventId']) ? (int)$b['eventId'] : null;

                if (strlen($title) < 5) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Title must be at least 5 characters.']);
                }
                if (strlen($content) < 20) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Content must be at least 20 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare(
                    'INSERT INTO forum_posts (user_id, event_id, title, content, author) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([(int)$user['user_id'], $eventId ?: null, $title, $content, $user['name']]);
                $postId = (int)$pdo->lastInsertId();

                return self::json($response, 201, [
                    'success' => true,
                    'message' => 'Post created successfully!',
                    'postId'  => $postId,
                    'post'    => [
                        'postId'    => $postId,
                        'userId'    => (int)$user['user_id'],
                        'eventId'   => $eventId ?: null,
                        'title'     => $title,
                        'content'   => $content,
                        'author'    => $user['name'],
                        'createdAt' => date('c'),
                    ],
                ]);
                } catch (\Throwable $e) {
                    error_log('[Forum] POST /posts error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to create post.']);
                }
            })->add(new AuthMiddleware());

            // ─── PUT /api/forum/posts/{id} ──────────────────────────────────
            $g->put('/posts/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];
                $b = (array)$request->getParsedBody();

                $title   = trim((string)($b['title']   ?? ''));
                $content = trim((string)($b['content'] ?? ''));
                $eventId = isset($b['eventId']) && $b['eventId'] !== '' ? (int)$b['eventId'] : null;

                if (strlen($title) < 5) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Title must be at least 5 characters.']);
                }
                if (strlen($content) < 20) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Content must be at least 20 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM forum_posts WHERE post_id = ?');
                $stmt->execute([$id]);
                $p = $stmt->fetch();
                if (!$p) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Post not found.']);
                }
                if ((int)$p['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'You can only edit your own posts.']);
                }

                $stmt = $pdo->prepare(
                    'UPDATE forum_posts SET title = ?, content = ?, event_id = ? WHERE post_id = ?'
                );
                $stmt->execute([$title, $content, $eventId ?: null, $id]);

                return self::json($response, 200, [
                    'success' => true,
                    'message' => 'Post updated successfully!',
                ]);
                } catch (\Throwable $e) {
                    error_log('[Forum] PUT /posts/{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update post.']);
                }
            })->add(new AuthMiddleware());

            // ─── DELETE /api/forum/posts/{id} ───────────────────────────────
            $g->delete('/posts/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM forum_posts WHERE post_id = ?');
                $stmt->execute([$id]);
                $p = $stmt->fetch();
                if (!$p) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Post not found.']);
                }
                if ((int)$p['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'You can only delete your own posts.']);
                }

                $stmt = $pdo->prepare('DELETE FROM forum_posts WHERE post_id = ?');
                $stmt->execute([$id]);

                return self::json($response, 200, ['success' => true, 'message' => 'Post deleted successfully!']);
                } catch (\Throwable $e) {
                    error_log('[Forum] DELETE /posts/{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete post.']);
                }
            })->add(new AuthMiddleware());

            // ─── POST /api/forum/comments ───────────────────────────────────
            $g->post('/comments', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $b = (array)$request->getParsedBody();
                $postId = (int)($b['postId'] ?? 0);
                $content = trim((string)($b['content'] ?? ''));

                if ($postId <= 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Post ID is required.']);
                }
                if (strlen($content) < 2) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Comment must be at least 2 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT post_id FROM forum_posts WHERE post_id = ?');
                $stmt->execute([$postId]);
                if (!$stmt->fetch()) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Post not found.']);
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO comments (post_id, user_id, comment_text, author) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$postId, (int)$user['user_id'], $content, $user['name']]);
                $commentId = (int)$pdo->lastInsertId();

                return self::json($response, 201, [
                    'success'   => true,
                    'message'   => 'Comment added!',
                    'commentId' => $commentId,
                    'comment'   => [
                        'commentId' => $commentId,
                        'postId'    => $postId,
                        'userId'    => (int)$user['user_id'],
                        'content'   => $content,
                        'author'    => $user['name'],
                        'createdAt' => date('c'),
                    ],
                ]);
                } catch (\Throwable $e) {
                    error_log('[Forum] POST /comments error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to add comment.']);
                }
            })->add(new AuthMiddleware());

            // ─── PUT /api/forum/comments/{id} ───────────────────────────────
            $g->put('/comments/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];
                $b = (array)$request->getParsedBody();
                $content = trim((string)($b['content'] ?? ''));

                if (strlen($content) < 2) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Comment must be at least 2 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM comments WHERE comment_id = ?');
                $stmt->execute([$id]);
                $c = $stmt->fetch();
                if (!$c) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Comment not found.']);
                }
                if ((int)$c['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'You can only edit your own comments.']);
                }

                $stmt = $pdo->prepare('UPDATE comments SET comment_text = ? WHERE comment_id = ?');
                $stmt->execute([$content, $id]);

                return self::json($response, 200, ['success' => true, 'message' => 'Comment updated successfully!']);
                } catch (\Throwable $e) {
                    error_log('[Forum] PUT /comments/{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update comment.']);
                }
            })->add(new AuthMiddleware());

            // ─── DELETE /api/forum/comments/{id} ────────────────────────────
            $g->delete('/comments/{id}', function (Request $request, Response $response, array $args): Response {
                $user = $request->getAttribute('user');
                $id = (int)$args['id'];

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM comments WHERE comment_id = ?');
                $stmt->execute([$id]);
                $c = $stmt->fetch();
                if (!$c) {
                    return self::json($response, 404, ['success' => false, 'message' => 'Comment not found.']);
                }
                if ((int)$c['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                    return self::json($response, 403, ['success' => false, 'message' => 'You can only delete your own comments.']);
                }

                $stmt = $pdo->prepare('DELETE FROM comments WHERE comment_id = ?');
                $stmt->execute([$id]);

                return self::json($response, 200, ['success' => true, 'message' => 'Comment deleted!']);
                } catch (\Throwable $e) {
                    error_log('[Forum] DELETE /comments/{id} error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to delete comment.']);
                }
            })->add(new AuthMiddleware());
        });
    }
}
