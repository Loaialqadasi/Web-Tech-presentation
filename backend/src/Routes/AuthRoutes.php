<?php
declare(strict_types=1);

namespace App\Routes;

use App\Database;
use App\JwtHelper;
use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Psr7\Response as SlimResponse;

/**
 * Auth routes — mirrors api/routes/auth.js
 *   POST   /api/auth/register
 *   POST   /api/auth/login
 *   GET    /api/auth/profile      (auth)
 *   PUT    /api/auth/profile      (auth)
 *   POST   /api/auth/change-password (auth)
 */
final class AuthRoutes
{
    public function __invoke(App $app): void
    {
        $app->group('/api/auth', function (\Slim\Routing\RouteCollectorProxy $g): void {

            // ─── POST /api/auth/register ─────────────────────────────────────
            $g->post('/register', function (Request $request, Response $response): Response {
                $body = (array)$request->getParsedBody();
                $name = trim((string)($body['name'] ?? ''));
                $email = trim((string)($body['email'] ?? ''));
                $emailLower = strtolower($email);
                $password = (string)($body['password'] ?? '');
                $role = (string)($body['role'] ?? 'student');
                $phone = trim((string)($body['phone'] ?? ''));
                $bio = trim((string)($body['bio'] ?? ''));
                $studentId = trim((string)($body['studentId'] ?? ''));
                $department = trim((string)($body['department'] ?? ''));

                // Validation
                if ($name === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Name is required.']);
                }
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Valid email is required.']);
                }
                if (strlen($password) < 6) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Password must be at least 6 characters.']);
                }

                try {
                $pdo = Database::pdo();

                // Check if email exists
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
                $stmt->execute([$emailLower]);
                if ($stmt->fetch()) {
                    return self::json($response, 409, ['success' => false, 'message' => 'An account with this email already exists.']);
                }

                // Hash password (PHP PASSWORD_BCRYPT = $2y$ 10 rounds; cross-compatible with bcryptjs $2a$)
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

                // Generate avatar initials (e.g. "Siti Nur" => "SN")
                $words = array_values(array_filter(explode(' ', $name), fn($w) => $w !== ''));
                $avatar = '';
                foreach ($words as $w) {
                    $avatar .= strtoupper($w[0]);
                    if (strlen($avatar) >= 2) break;
                }
                $avatar = substr($avatar, 0, 2);

                // Random avatar color (matches Node colors list)
                $colors = ['bg-indigo-500','bg-purple-500','bg-blue-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-cyan-500','bg-teal-500'];
                $avatarColor = $colors[array_rand($colors)];

                $userRole = ($role === 'organizer') ? 'organizer' : 'student';

                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, avatar, avatar_color, phone, bio, student_id, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $emailLower, $hashedPassword, $userRole, $avatar, $avatarColor, $phone, $bio, $studentId, $department]);

                $userId = (int)$pdo->lastInsertId();

                $token = JwtHelper::encode([
                    'user_id' => $userId,
                    'email'   => $emailLower,
                    'role'    => $userRole,
                    'name'    => $name,
                ]);

                $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                return self::json($response, 201, [
                    'success' => true,
                    'message' => 'Account created successfully!',
                    'token'   => $token,
                    'user'    => self::publicUser($user),
                ]);
                } catch (\Throwable $e) {
                    error_log('[Auth] POST /register error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Registration failed. Please try again.']);
                }
            });

            // ─── POST /api/auth/login ────────────────────────────────────────
            $g->post('/login', function (Request $request, Response $response): Response {
                $body = (array)$request->getParsedBody();
                $email = strtolower(trim((string)($body['email'] ?? '')));
                $password = (string)($body['password'] ?? '');

                if ($email === '' || $password === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Email and password are required.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    return self::json($response, 401, ['success' => false, 'message' => 'Invalid email or password.']);
                }

                if (!password_verify($password, $user['password'])) {
                    return self::json($response, 401, ['success' => false, 'message' => 'Invalid email or password.']);
                }

                $token = JwtHelper::encode([
                    'user_id' => (int)$user['user_id'],
                    'email'   => $user['email'],
                    'role'    => $user['role'],
                    'name'    => $user['name'],
                ]);

                return self::json($response, 200, [
                    'success' => true,
                    'message' => "Welcome back, {$user['name']}!",
                    'token'   => $token,
                    'user'    => self::publicUser($user),
                ]);
                } catch (\Throwable $e) {
                    error_log('[Auth] POST /login error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Login failed. Please try again.']);
                }
            });

            // ─── GET /api/auth/profile ───────────────────────────────────────
            $g->get('/profile', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$user['user_id'];

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch();

                if (!$row) {
                    return self::json($response, 404, ['success' => false, 'message' => 'User not found.']);
                }

                return self::json($response, 200, ['success' => true, 'user' => self::publicUser($row)]);
                } catch (\Throwable $e) {
                    error_log('[Auth] GET /profile error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to fetch profile.']);
                }
            })->add(new AuthMiddleware());

            // ─── PUT /api/auth/profile ───────────────────────────────────────
            $g->put('/profile', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$user['user_id'];
                $body = (array)$request->getParsedBody();

                try {
                $pdo = Database::pdo();

                // Validate email if provided
                $email = isset($body['email']) ? strtolower(trim((string)$body['email'])) : null;
                if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return self::json($response, 400, ['success' => false, 'message' => 'Valid email is required.']);
                }
                if ($email !== null && $email !== strtolower($user['email'])) {
                    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
                    $stmt->execute([$email, $userId]);
                    if ($stmt->fetch()) {
                        return self::json($response, 409, ['success' => false, 'message' => 'Email already in use.']);
                    }
                }

                // Build UPDATE dynamically
                $updates = [];
                $values  = [];

                $name = isset($body['name']) ? trim((string)$body['name']) : null;
                if ($name !== null) {
                    $updates[] = 'name = ?'; $values[] = $name;
                    // Regenerate avatar initials
                    $words = array_values(array_filter(explode(' ', $name), fn($w) => $w !== ''));
                    $av = '';
                    foreach ($words as $w) { $av .= strtoupper($w[0]); if (strlen($av) >= 2) break; }
                    $updates[] = 'avatar = ?'; $values[] = substr($av, 0, 2);
                }
                if (isset($body['email']))     { $updates[] = 'email = ?';       $values[] = strtolower(trim((string)$body['email'])); }
                if (isset($body['phone']))     { $updates[] = 'phone = ?';       $values[] = trim((string)$body['phone']); }
                if (isset($body['bio']))       { $updates[] = 'bio = ?';         $values[] = trim((string)$body['bio']); }
                if (isset($body['studentId'])) { $updates[] = 'student_id = ?';  $values[] = trim((string)$body['studentId']); }
                if (isset($body['department'])){ $updates[] = 'department = ?';  $values[] = trim((string)$body['department']); }

                if (count($updates) === 0) {
                    return self::json($response, 400, ['success' => false, 'message' => 'No fields to update.']);
                }

                $values[] = $userId;
                $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                // Re-fetch
                $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch();

                $token = JwtHelper::encode([
                    'user_id' => (int)$row['user_id'],
                    'email'   => $row['email'],
                    'role'    => $row['role'],
                    'name'    => $row['name'],
                ]);

                return self::json($response, 200, [
                    'success' => true,
                    'message' => 'Profile updated successfully!',
                    'token'   => $token,
                    'user'    => self::publicUser($row),
                ]);
                } catch (\Throwable $e) {
                    error_log('[Auth] PUT /profile error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to update profile.']);
                }
            })->add(new AuthMiddleware());

            // ─── POST /api/auth/change-password ──────────────────────────────
            $g->post('/change-password', function (Request $request, Response $response): Response {
                $user = $request->getAttribute('user');
                $userId = (int)$user['user_id'];
                $body = (array)$request->getParsedBody();
                $currentPassword = (string)($body['currentPassword'] ?? '');
                $newPassword = (string)($body['newPassword'] ?? '');

                if ($currentPassword === '' || $newPassword === '') {
                    return self::json($response, 400, ['success' => false, 'message' => 'Current and new password are required.']);
                }
                if (strlen($newPassword) < 6) {
                    return self::json($response, 400, ['success' => false, 'message' => 'New password must be at least 6 characters.']);
                }

                try {
                $pdo = Database::pdo();
                $stmt = $pdo->prepare('SELECT password FROM users WHERE user_id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch();

                if (!$row) {
                    return self::json($response, 404, ['success' => false, 'message' => 'User not found.']);
                }
                if (!password_verify($currentPassword, $row['password'])) {
                    return self::json($response, 401, ['success' => false, 'message' => 'Current password is incorrect.']);
                }

                $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $stmt->execute([$hashed, $userId]);

                return self::json($response, 200, ['success' => true, 'message' => 'Password updated successfully!']);
                } catch (\Throwable $e) {
                    error_log('[Auth] POST /change-password error: ' . $e->getMessage());
                    return self::json($response, 500, ['success' => false, 'message' => 'Failed to change password.']);
                }
            })->add(new AuthMiddleware());
        });
    }

    /**
     * Map a raw DB user row to the public user object returned to the client.
     * Matches the shape returned by the Node version.
     */
    public static function publicUser(array $u): array
    {
        return [
            'id'          => (int)$u['user_id'],
            'name'        => $u['name'],
            'email'       => $u['email'],
            'role'        => $u['role'],
            'avatar'      => $u['avatar'] ?? '',
            'avatarColor' => $u['avatar_color'] ?? 'bg-indigo-500',
            'phone'       => $u['phone'] ?? '',
            'bio'         => $u['bio'] ?? '',
            'studentId'   => $u['student_id'] ?? '',
            'department'  => $u['department'] ?? '',
            'createdAt'   => $u['created_at'] ?? null,
        ];
    }

    private static function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
