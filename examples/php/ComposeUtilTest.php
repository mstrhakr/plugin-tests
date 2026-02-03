<?php

/**
 * Example PHP Test - Compose Manager Utilities
 *
 * This demonstrates how to test PHP code from the compose plugin
 * using the plugin-tests framework.
 */

declare(strict_types=1);

namespace PluginTests\Examples;

use PluginTests\TestCase;
use PluginTests\Fixtures\Defaults;

class ComposeUtilTest extends TestCase
{
    /**
     * Test parsing plugin configuration
     */
    public function testParsePluginConfig(): void
    {
        // Arrange: Set up mock config
        $this->mockPluginConfig('compose.manager', [
            'COMPOSE_HTTP_TIMEOUT' => '300',
            'AUTOSTART' => 'yes',
            'DEBUG' => 'no',
        ]);

        // Act: Call the function
        $cfg = parse_plugin_cfg('compose.manager');

        // Assert: Verify results
        $this->assertEquals('300', $cfg['COMPOSE_HTTP_TIMEOUT']);
        $this->assertEquals('yes', $cfg['AUTOSTART']);
        $this->assertEquals('no', $cfg['DEBUG']);
    }

    /**
     * Test with default compose manager config fixture
     */
    public function testWithDefaultFixture(): void
    {
        // Use predefined fixture
        $this->mockPluginConfig('compose.manager', Defaults::composeManagerConfig());

        $cfg = parse_plugin_cfg('compose.manager');

        $this->assertEquals('3', $cfg['MAX_RETRIES']);
        $this->assertEquals('5', $cfg['RETRY_DELAY']);
    }

    /**
     * Test array state affects behavior
     */
    public function testArrayStartedState(): void
    {
        // Set array to started
        $this->setArrayStarted(true);

        global $var;
        $this->assertEquals('Started', $var['fsState']);
        $this->assertEquals('STARTED', $var['mdState']);
    }

    /**
     * Test Docker running state
     */
    public function testDockerRunningState(): void
    {
        $this->setDockerRunning(true);

        global $var;
        $this->assertTrue($var['dockerRunning']);

        $this->setDockerRunning(false);
        $this->assertFalse($var['dockerRunning']);
    }

    /**
     * Test mocking disk array
     */
    public function testMockDisks(): void
    {
        $this->mockDisks([
            'disk1' => Defaults::disk('disk1', 'DISK_OK'),
            'disk2' => Defaults::disk('disk2', 'DISK_OK'),
            'cache' => [
                'name' => 'cache',
                'status' => 'DISK_OK',
                'type' => 'Cache',
                'device' => 'nvme0n1',
                'fsType' => 'btrfs',
            ],
        ]);

        global $disks;
        $this->assertCount(3, $disks);
        $this->assertEquals('Data', $disks['disk1']['type']);
        $this->assertEquals('Cache', $disks['cache']['type']);
    }

    /**
     * Test mocking shares
     */
    public function testMockShares(): void
    {
        $this->mockShares([
            'appdata' => Defaults::share('appdata'),
            'isos' => Defaults::share('isos'),
        ]);

        global $shares;
        $this->assertCount(2, $shares);
        $this->assertArrayHasKey('appdata', $shares);
    }

    /**
     * Test autov function mock
     */
    public function testAutovMock(): void
    {
        $result = autov('/plugins/compose.manager/styles/compose.css');
        $this->assertEquals('/plugins/compose.manager/styles/compose.css?v=test', $result);
    }

    /**
     * Test CSRF token mock
     */
    public function testCsrfTokenMock(): void
    {
        $token = csrf_token();
        $this->assertEquals('test-csrf-token-12345', $token);
    }

    /**
     * Test notification logging
     */
    public function testNotificationCapture(): void
    {
        // Call notify
        notify('Compose Manager', 'Stack Started', 'mystack started successfully');

        // Assert it was logged
        $this->assertLogged('Stack Started');
    }

    /**
     * Test temp directory creation
     */
    public function testTempDirectory(): void
    {
        $tempDir = $this->createTempDir();

        $this->assertDirectoryExists($tempDir);

        // Create a test file
        file_put_contents("$tempDir/test.txt", 'hello');
        $this->assertFileExists("$tempDir/test.txt");

        // Temp dir will be cleaned up automatically after test
    }
}
