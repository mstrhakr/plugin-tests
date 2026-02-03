<?php

/**
 * Plugin Tests Framework - Base Test Case
 *
 * Extend this class for your plugin tests to get access to
 * mock helpers and automatic setup/teardown.
 */

declare(strict_types=1);

namespace PluginTests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PluginTests\Mocks\GlobalsMock;
use PluginTests\Mocks\FunctionMocks;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset all mocks to defaults
        GlobalsMock::reset();
        FunctionMocks::reset();
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        GlobalsMock::reset();
        FunctionMocks::reset();

        parent::tearDown();
    }

    /**
     * Mock a plugin's configuration
     *
     * @param string $plugin Plugin name
     * @param array<string, mixed> $config Configuration array
     */
    protected function mockPluginConfig(string $plugin, array $config): void
    {
        FunctionMocks::setPluginConfig($plugin, $config);
    }

    /**
     * Mock the $var global array
     *
     * @param array<string, mixed> $values Values to merge with defaults
     */
    protected function mockVar(array $values): void
    {
        GlobalsMock::setVar($values);
    }

    /**
     * Mock the $disks global array
     *
     * @param array<string, array<string, mixed>> $disks Disk configurations
     */
    protected function mockDisks(array $disks): void
    {
        GlobalsMock::setDisks($disks);
    }

    /**
     * Mock the $shares global array
     *
     * @param array<string, array<string, mixed>> $shares Share configurations
     */
    protected function mockShares(array $shares): void
    {
        GlobalsMock::setShares($shares);
    }

    /**
     * Set array started state
     */
    protected function setArrayStarted(bool $started = true): void
    {
        GlobalsMock::setVar([
            'fsState' => $started ? 'Started' : 'Stopped',
            'mdState' => $started ? 'STARTED' : 'STOPPED',
        ]);
    }

    /**
     * Set Docker running state
     */
    protected function setDockerRunning(bool $running = true): void
    {
        GlobalsMock::setVar([
            'dockerRunning' => $running,
        ]);
    }

    /**
     * Assert that a log message was recorded
     *
     * @param string $message Expected message (substring match)
     * @param string $level Optional log level
     */
    protected function assertLogged(string $message, string $level = ''): void
    {
        $logs = FunctionMocks::getLogs();
        $found = false;

        foreach ($logs as $log) {
            if (str_contains($log['message'], $message)) {
                if ($level === '' || $log['level'] === $level) {
                    $found = true;
                    break;
                }
            }
        }

        $this->assertTrue($found, "Expected log message not found: $message");
    }

    /**
     * Get all captured log messages
     *
     * @return array<int, array{level: string, message: string}>
     */
    protected function getLogs(): array
    {
        return FunctionMocks::getLogs();
    }

    /**
     * Create a temporary directory for test files
     *
     * @return string Path to temp directory
     */
    protected function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/plugin-tests-' . uniqid();
        mkdir($dir, 0755, true);

        // Register for cleanup
        $this->registerTempDir($dir);

        return $dir;
    }

    /**
     * Directories to clean up after test
     * @var array<string>
     */
    private array $tempDirs = [];

    /**
     * Register a temp directory for cleanup
     */
    private function registerTempDir(string $dir): void
    {
        $this->tempDirs[] = $dir;
    }

    /**
     * Clean up temp directories
     */
    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->recursiveDelete($dir);
            }
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

    /**
     * Recursively delete a directory
     */
    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
