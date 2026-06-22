<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * Singleton PDO connection wrapper for MySQL.
 *
 * Mirrors the Node.js `mysql2/promise` pool from `api/config/database.js`.
 * Uses prepared statements throughout to prevent SQL injection (required by
 * the SECJ3483 brief: "PDO with prepared statements").
 *
 * Works with:
 *   - Local MySQL (XAMPP/MAMP) — defaults
 *   - TiDB Cloud — set DB_HOST to gateway, DB_PORT=4000, DB_SSL=true
 *
 * Usage:
 *   $pdo = \App\Database::pdo();
 *   $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
 *   $stmt->execute([$email]);
 *   $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host    = getenv('DB_HOST')     ?: '127.0.0.1';
            $port    = getenv('DB_PORT')     ?: '3306';
            $dbName  = getenv('DB_NAME')     ?: 'unievent_db';
            $user    = getenv('DB_USER')     ?: 'root';
            $pass    = getenv('DB_PASS')     ?: '';
            $charset = getenv('DB_CHARSET')  ?: 'utf8mb4';
            $useSsl  = getenv('DB_SSL')      === 'true';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];

            // TiDB Cloud requires TLS — enable SSL with a bundled CA bundle.
            // Render's PHP image ships /etc/ssl/cert.pem; we point to it here.
            if ($useSsl) {
                $caBundle = getenv('DB_SSL_CA') ?: '/etc/ssl/cert.pem';
                if (file_exists($caBundle)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caBundle;
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
                if (getenv('APP_DEBUG') === 'true') {
                    error_log('[Database] Connected to ' . $host . ':' . $port . '/' . $dbName);
                }
            } catch (PDOException $e) {
                error_log('[Database] Connection failed: ' . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed.',
                    'debug'   => getenv('APP_DEBUG') === 'true' ? $e->getMessage() : null,
                ]);
                exit;
            }
        }

        return self::$pdo;
    }
}
