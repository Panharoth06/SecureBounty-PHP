<?php

/**
 * Property-Based Test Runner (innmind/black-box)
 *
 * Discovers and runs all property test files in this directory.
 * Usage: php tests/property/run.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/TestDatabaseHelper.php';

use Innmind\BlackBox\Application;

Application::new([])
    ->scenariiPerProof(100)
    ->tryToProve(static function (): \Generator {
        // Auto-discover all property test files in this directory
        $directory = __DIR__;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Properties.php') && $file->getFilename() !== 'run.php') {
                yield from require $file->getPathname();
            }
        }
    })
    ->exit();
