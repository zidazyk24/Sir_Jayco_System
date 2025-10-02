<?php
// Database connection helper for mini_market_system
// Usage: $pdo = db(); $stmt = $pdo->prepare('SELECT ...');

declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = 'localhost';
    $dbname = 'mini_market_system';
    $charset = 'utf8mb4';
    $user = 'root';         // XAMPP default user
    $pass = '';             // XAMPP default password is empty

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        // Avoid exposing credentials or full stack traces in production
        die('Database connection failed. Please check your configuration.');
    }

    return $pdo;
}

// Optional small helpers
function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_exec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

