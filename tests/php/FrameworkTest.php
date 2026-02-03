<?php

/**
 * Framework Self-Test - Verifies the mock library works correctly
 */

declare(strict_types=1);

namespace PluginTests\Tests;

use PluginTests\TestCase;
use PluginTests\Mocks\FunctionMocks;
use PluginTests\Mocks\GlobalsMock;

class FrameworkTest extends TestCase
{
    public function testMockPluginConfigWorks(): void
    {
        $this->mockPluginConfig('test.plugin', [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $config = parse_plugin_cfg('test.plugin');

        $this->assertEquals('value1', $config['key1']);
        $this->assertEquals('value2', $config['key2']);
    }

    public function testMockPluginConfigResetsBetweenTests(): void
    {
        // This test runs after the previous one
        // Config should be empty since setUp() calls reset()
        $config = parse_plugin_cfg('test.plugin');

        $this->assertEmpty($config);
    }

    public function testVarGlobalIsInitialized(): void
    {
        global $var;

        $this->assertIsArray($var);
        $this->assertArrayHasKey('NAME', $var);
        $this->assertArrayHasKey('version', $var);
        $this->assertArrayHasKey('csrf_token', $var);
    }

    public function testSetVarMergesWithDefaults(): void
    {
        $this->mockVar(['customKey' => 'customValue']);

        global $var;

        // Should have both default and custom keys
        $this->assertEquals('TestServer', $var['NAME']);
        $this->assertEquals('customValue', $var['customKey']);
    }

    public function testSetArrayStarted(): void
    {
        $this->setArrayStarted(true);
        global $var;
        $this->assertEquals('Started', $var['fsState']);

        $this->setArrayStarted(false);
        $this->assertEquals('Stopped', $var['fsState']);
    }

    public function testSetDockerRunning(): void
    {
        $this->setDockerRunning(true);
        global $var;
        $this->assertTrue($var['dockerRunning']);

        $this->setDockerRunning(false);
        $this->assertFalse($var['dockerRunning']);
    }

    public function testAutovFunction(): void
    {
        $result = autov('/test/path.css');
        $this->assertStringContainsString('/test/path.css', $result);
        $this->assertStringContainsString('?v=', $result);
    }

    public function testCsrfTokenFunction(): void
    {
        $token = csrf_token();
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testTranslationFunction(): void
    {
        $result = _('Hello World');
        $this->assertEquals('Hello World', $result);
    }

    public function testNotifyCapture(): void
    {
        notify('TestEvent', 'TestSubject', 'TestDescription');

        $this->assertLogged('TestSubject');
    }

    public function testSyslogCapture(): void
    {
        // Use plugin_log() which always captures to FunctionMocks for testing
        // This works on all systems regardless of whether real syslog exists
        plugin_log(LOG_INFO, 'Test syslog message', false);

        $this->assertLogged('Test syslog message');
    }

    public function testTempDirCreation(): void
    {
        $dir = $this->createTempDir();

        $this->assertDirectoryExists($dir);
        $this->assertStringStartsWith(sys_get_temp_dir(), $dir);
    }

    public function testMockDisks(): void
    {
        $this->mockDisks([
            'disk1' => ['name' => 'disk1', 'status' => 'OK'],
        ]);

        global $disks;
        $this->assertCount(1, $disks);
        $this->assertEquals('disk1', $disks['disk1']['name']);
    }

    public function testMockShares(): void
    {
        $this->mockShares([
            'appdata' => ['name' => 'appdata'],
        ]);

        global $shares;
        $this->assertCount(1, $shares);
        $this->assertArrayHasKey('appdata', $shares);
    }
}
