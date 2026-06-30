<?php

/**
 * Load key=value pairs from a .env file into the environment.
 *
 * @param string $path  Absolute path to the .env file.
 */
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException(".env file not found at: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comment lines
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Skip malformed lines without a key=value pair
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if (!empty($key)) {
                // Do not override values already provided by the environment
                // (e.g. those injected by phpunit.xml). This lets the test
                // runner control configuration without the .env file clobbering it.
                if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
                    continue;
                }

                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// ── Load credentials ────────────────────────────────────────────────────────
// The .env file lives alongside this config file (config/.env). When running
// under the testing environment (APP_ENV=testing, set via phpunit.xml) prefer
// config/.env.testing if it exists so tests target the test database.
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '';

$envFile = __DIR__ . '/.env';

if ($appEnv === 'testing' && file_exists(__DIR__ . '/.env.testing')) {
    $envFile = __DIR__ . '/.env.testing';
}

loadEnv($envFile);

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

// ── Connect via MySQLi ───────────────────────────────────────────────────────
$conn = new mysqli($host, $user, $pass, $dbName, (int) $port);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Return the connection object for injection into repositories
return $conn;