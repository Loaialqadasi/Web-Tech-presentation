<?php
declare(strict_types=1);

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;

/**
 * JWT encode/decode helper.
 *
 * Mirror of `api/middleware/auth.js` `generateToken()` in the Node version.
 * Tokens are signed with HS256 and expire after JWT_EXPIRES_HOURS (default 24h).
 */
final class JwtHelper
{
    public static function secret(): string
    {
        $s = getenv('JWT_SECRET') ?: 'unievent_secret_key_2026_freshdev';
        return $s;
    }

    public static function expiresIn(): int
    {
        $h = (int)(getenv('JWT_EXPIRES_HOURS') ?: '24');
        return $h * 3600;
    }

    /**
     * Generate a JWT for a user. Payload matches the Node version:
     *   { user_id, email, role, name, iat, exp }
     */
    public static function encode(array $user): string
    {
        $now = time();
        $payload = [
            'user_id' => (int)$user['user_id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
            'name'    => $user['name'],
            'iat'     => $now,
            'exp'     => $now + self::expiresIn(),
        ];
        return JWT::encode($payload, self::secret(), 'HS256');
    }

    /**
     * Decode & verify a JWT. Returns the payload as array or null on failure.
     */
    public static function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));
            return (array)$decoded;
        } catch (ExpiredException $e) {
            return ['__expired' => true];
        } catch (SignatureInvalidException | InvalidArgumentException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract Bearer token from Authorization header.
     */
    public static function extractBearerToken(string $authHeader): ?string
    {
        if (!$authHeader) {
            return null;
        }
        // "Bearer TOKEN"
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
