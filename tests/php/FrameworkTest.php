<?php

/**
 * Framework Self-Test - Verifies the mock library works correctly
 */

declare(strict_types=1);

namespace PluginTests\Tests;

use PluginTests\TestCase;
use PluginTests\Mocks\FunctionMocks;
use PluginTests\Mocks\GlobalsMock;
use PluginTests\Mocks\DockerUtilMock;

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

    // ===========================================
    // Docker Mock Tests
    // ===========================================

    public function testDockerUtilEnsureImageTag(): void
    {
        // Official image without tag
        $result = \DockerUtil::ensureImageTag('nginx');
        $this->assertEquals('library/nginx:latest', $result);

        // Official image with tag
        $result = \DockerUtil::ensureImageTag('nginx:1.25');
        $this->assertEquals('library/nginx:1.25', $result);

        // User image without tag
        $result = \DockerUtil::ensureImageTag('linuxserver/plex');
        $this->assertEquals('linuxserver/plex:latest', $result);

        // User image with tag
        $result = \DockerUtil::ensureImageTag('linuxserver/plex:latest');
        $this->assertEquals('linuxserver/plex:latest', $result);
    }

    public function testDockerUpdateMock(): void
    {
        // Set up mock update status
        $this->mockUpdateStatus('library/nginx:latest', 'sha256:abc123', 'sha256:abc123');
        $this->mockUpdateStatus('library/redis:latest', 'sha256:old111', 'sha256:new222');

        $update = new \DockerUpdate();

        // nginx is up to date
        $update->reloadUpdateStatus('library/nginx:latest');
        $this->assertTrue($update->getUpdateStatus('library/nginx:latest'));

        // redis has update available
        $update->reloadUpdateStatus('library/redis:latest');
        $this->assertFalse($update->getUpdateStatus('library/redis:latest'));
    }

    public function testDockerClientGetContainers(): void
    {
        $this->mockContainers([
            'nginx_container' => [
                'Name' => 'nginx_container',
                'Image' => 'nginx:latest',
                'State' => 'running',
            ],
            'redis_container' => [
                'Name' => 'redis_container',
                'Image' => 'redis:latest',
                'State' => 'stopped',
            ],
        ]);

        $client = new \DockerClient();
        $containers = $client->getDockerContainers();

        $this->assertCount(2, $containers);
        $this->assertEquals('nginx_container', $containers[0]['Name']);
    }

    public function testDockerUtilLoadSaveJson(): void
    {
        $testPath = '/tmp/test-update-status.json';
        $testData = [
            'nginx:latest' => ['local' => 'sha256:abc', 'remote' => 'sha256:abc'],
        ];

        // Save JSON
        \DockerUtil::saveJSON($testPath, $testData);

        // Load it back
        $loaded = \DockerUtil::loadJSON($testPath);

        $this->assertEquals($testData, $loaded);
    }

    // ===========================================
    // mk_option Test
    // ===========================================

    public function testMkOption(): void
    {
        // Selected option - actual Unraid uses single quotes
        $result = mk_option('yes', 'yes', 'Yes');
        $this->assertStringContainsString('selected', $result);
        $this->assertStringContainsString("value='yes'", $result);

        // Non-selected option
        $result = mk_option('yes', 'no', 'No');
        $this->assertStringNotContainsString('selected', $result);
        $this->assertStringContainsString("value='no'", $result);
    }

    // ===========================================
    // New Helper Function Tests
    // ===========================================

    public function testVar(): void
    {
        $arr = ['foo' => 'bar', 'num' => 42];

        // Key exists
        $this->assertEquals('bar', _var($arr, 'foo'));

        // Key missing - default
        $this->assertEquals('default', _var($arr, 'missing', 'default'));

        // Null key returns whole variable
        $this->assertEquals($arr, _var($arr, null, []));

        // Undefined variable
        $undefined = null;
        $this->assertEquals('fallback', _var($undefined, null, 'fallback'));
    }

    public function testMyScale(): void
    {
        $unit = '';

        // Small value
        $result = my_scale(500, $unit);
        $this->assertEquals('B', $unit);

        // Kilobytes
        $result = my_scale(1500, $unit);
        $this->assertEquals('KB', $unit);

        // Megabytes
        $result = my_scale(1500000, $unit);
        $this->assertEquals('MB', $unit);

        // Gigabytes
        $result = my_scale(1500000000, $unit);
        $this->assertEquals('GB', $unit);
    }

    public function testCompress(): void
    {
        // Short string unchanged
        $this->assertEquals('short', compress('short'));

        // Long string compressed
        $long = 'this is a very long string that needs compression';
        $result = compress($long, 18, 6);
        $this->assertLessThanOrEqual(18, mb_strlen($result));
        $this->assertStringContainsString('...', $result);
    }

    public function testMyExplode(): void
    {
        // Normal split
        $result = my_explode(':', 'a:b', 2);
        $this->assertEquals(['a', 'b'], $result);

        // Missing parts padded
        $result = my_explode(':', 'a', 3);
        $this->assertEquals(['a', '', ''], $result);
    }

    public function testAutov(): void
    {
        // Non-existent file
        $result = autov('/nonexistent/file.js', true);
        $this->assertStringContainsString('?v=', $result);
    }

    // =====================================================
    // Tests for plugin() mock
    // =====================================================

    public function testPluginAttributeVersion(): void
    {
        FunctionMocks::setPluginAttributes('/var/log/plugins/test.plg', [
            'version' => '2024.01.15',
            'name' => 'Test Plugin',
            'author' => 'Test Author',
        ]);

        $version = plugin('version', '/var/log/plugins/test.plg');
        $this->assertEquals('2024.01.15', $version);
    }

    public function testPluginAttributeName(): void
    {
        FunctionMocks::setPluginAttributes('/var/log/plugins/test.plg', [
            'version' => '2024.01.15',
            'name' => 'Test Plugin',
            'pluginURL' => 'https://example.com/test.plg',
        ]);

        $name = plugin('name', '/var/log/plugins/test.plg');
        $this->assertEquals('Test Plugin', $name);

        $url = plugin('pluginURL', '/var/log/plugins/test.plg');
        $this->assertEquals('https://example.com/test.plg', $url);
    }

    public function testPluginAttributeReturnsFalseWhenNotSet(): void
    {
        $result = plugin('version', '/var/log/plugins/nonexistent.plg');
        $this->assertFalse($result);
    }

    public function testPluginAttributeReturnsFalseForMissingAttribute(): void
    {
        FunctionMocks::setPluginAttributes('/var/log/plugins/test.plg', [
            'version' => '1.0.0',
        ]);

        // Request an attribute that doesn't exist
        $result = plugin('support', '/var/log/plugins/test.plg');
        $this->assertFalse($result);
    }

    public function testPluginAttributesReturnsJson(): void
    {
        FunctionMocks::setPluginAttributes('/var/log/plugins/test.plg', [
            'version' => '1.0.0',
            'name' => 'Test',
        ]);

        $result = plugin('attributes', '/var/log/plugins/test.plg');
        $this->assertIsString($result);

        $decoded = json_decode($result, true);
        $this->assertEquals('1.0.0', $decoded['version']);
        $this->assertEquals('Test', $decoded['name']);
    }

    public function testPluginCommandOutput(): void
    {
        FunctionMocks::setPluginCommandOutput('changes', '/var/log/plugins/test.plg', "Version 1.0.1\n- Bug fixes\n- New feature");

        $result = plugin('changes', '/var/log/plugins/test.plg');
        $this->assertStringContainsString('Version 1.0.1', $result);
        $this->assertStringContainsString('Bug fixes', $result);
    }

    public function testPluginCommandReturnsFalseWhenNotSet(): void
    {
        $result = plugin('check', '/var/log/plugins/test.plg');
        $this->assertFalse($result);
    }
}
