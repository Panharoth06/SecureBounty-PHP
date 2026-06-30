<?php

/**
 * PHPUnit Bootstrap
 *
 * Loads the Composer autoloader and sets up the test database connection.
 */

declare(strict_types=1);

// Send PHP's error/deprecation/warning display to STDERR rather than STDOUT.
// In the CLI test runner, any output written through PHP's normal output layer
// (e.g. a displayed deprecation notice) marks headers as "already sent", which
// then breaks controller code that calls http_response_code()/session_start().
// PHPUnit captures and reports deprecations/warnings independently, so routing
// the raw display to STDERR keeps STDOUT clean without hiding any diagnostics.
ini_set('display_errors', 'stderr');

// Load Composer autoloader
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "Composer autoloader not found. Run 'composer install' first.\n");
    exit(1);
}

require_once $autoloader;

// Load test environment variables from phpunit.xml <php> block
// These are already available via $_ENV when PHPUnit processes the config.
// We also set them via putenv for code that reads from getenv().
$envVars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_ENV'];

foreach ($envVars as $var) {
    if (isset($_ENV[$var])) {
        putenv("{$var}={$_ENV[$var]}");
    }
}

// Load the test database helper
require_once __DIR__ . '/TestDatabaseHelper.php';
