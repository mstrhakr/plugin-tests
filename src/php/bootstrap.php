<?php

/**
 * Plugin Tests Framework - Bootstrap
 *
 * Loads all mock functions and sets up the test environment.
 * Include this file in your phpunit.xml bootstrap.
 */

declare(strict_types=1);

// Load the mock functions
require_once __DIR__ . '/Mocks/FunctionMocks.php';
require_once __DIR__ . '/Mocks/GlobalsMock.php';
require_once __DIR__ . '/Mocks/DockerMock.php';
require_once __DIR__ . '/Fixtures/defaults.php';

// Load the stream wrapper and helpers
require_once __DIR__ . '/StreamWrapper/UnraidStreamWrapper.php';
require_once __DIR__ . '/PluginBootstrap.php';
require_once __DIR__ . '/helpers.php';

// Initialize default globals
PluginTests\Mocks\GlobalsMock::initialize();
