<?php

/**
 * Plugin Tests Framework - Globals Mock
 *
 * Provides mock implementations of platform-specific global variables
 * like $var, $disks, $shares, etc.
 */

declare(strict_types=1);

namespace PluginTests\Mocks;

/**
 * Manages mock global variables
 */
class GlobalsMock
{
    /**
     * Initialize global variables with defaults
     */
    public static function initialize(): void
    {
        global $var, $disks, $shares, $users, $dockerClient, $dockerManPaths, $docroot, $display, $driver;

        $var = self::getDefaultVar();
        $disks = [];
        $shares = [];
        $users = [];
        $dockerClient = new MockDockerClient();
        $docroot = '/usr/local/emhttp';
        $display = self::getDefaultDisplay();
        $driver = ['bridge' => 'bridge', 'host' => 'host', 'none' => 'null'];
        $dockerManPaths = self::getDefaultDockerManPaths();
    }

    /**
     * Reset globals to defaults
     */
    public static function reset(): void
    {
        self::initialize();
    }

    /**
     * Set $docroot value
     *
     * @param string $path Document root path
     */
    public static function setDocroot(string $path): void
    {
        global $docroot;
        $docroot = $path;
    }

    /**
     * Set $display values (merged with defaults)
     *
     * @param array<string, mixed> $values Values to set
     */
    public static function setDisplay(array $values): void
    {
        global $display;
        $display = array_merge($display ?? self::getDefaultDisplay(), $values);
    }

    /**
     * Set $dockerManPaths values
     *
     * @param array<string, string> $paths Paths to set
     */
    public static function setDockerManPaths(array $paths): void
    {
        global $dockerManPaths;
        $dockerManPaths = array_merge($dockerManPaths ?? self::getDefaultDockerManPaths(), $paths);
    }

    /**
     * Set $driver (network drivers)
     *
     * @param array<string, string> $drivers Network drivers
     */
    public static function setDriver(array $drivers): void
    {
        global $driver;
        $driver = $drivers;
    }

    /**
     * Set $var values (merged with defaults)
     *
     * @param array<string, mixed> $values Values to set
     */
    public static function setVar(array $values): void
    {
        global $var;
        $var = array_merge($var ?? self::getDefaultVar(), $values);
    }

    /**
     * Get current $var value
     *
     * @return array<string, mixed>
     */
    public static function getVar(): array
    {
        global $var;
        return $var ?? self::getDefaultVar();
    }

    /**
     * Set $disks array
     *
     * @param array<string, array<string, mixed>> $diskArray
     */
    public static function setDisks(array $diskArray): void
    {
        global $disks;
        $disks = $diskArray;
    }

    /**
     * Set $shares array
     *
     * @param array<string, array<string, mixed>> $shareArray
     */
    public static function setShares(array $shareArray): void
    {
        global $shares;
        $shares = $shareArray;
    }

    /**
     * Set $users array
     *
     * @param array<string, array<string, mixed>> $userArray
     */
    public static function setUsers(array $userArray): void
    {
        global $users;
        $users = $userArray;
    }

    /**
     * Get default $var array
     *
     * @return array<string, mixed>
     */
    public static function getDefaultVar(): array
    {
        return [
            // System identification
            'NAME' => 'TestServer',
            'timeZone' => 'America/New_York',
            'version' => '7.0.0',
            'csrf_token' => 'test-csrf-token-12345',

            // Array state
            'fsState' => 'Started',
            'mdState' => 'STARTED',
            'mdNumDisks' => 4,
            'mdNumDisabled' => 0,
            'mdNumInvalid' => 0,
            'mdNumMissing' => 0,
            'mdNumNew' => 0,
            'mdResync' => 0,
            'mdResyncPos' => 0,
            'mdResyncSize' => 0,

            // Docker
            'dockerRunning' => true,

            // Network
            'USE_SSL' => 'no',
            'PORT' => '80',
            'PORTSSL' => '443',
            'LOCAL_TLD' => 'local',

            // Paths
            'emhttpDir' => '/usr/local/emhttp',
            'bootDir' => '/boot',

            // Registration
            'regState' => 'REGISTERED',
            'regTy' => 'Plus',
        ];
    }

    /**
     * Get default $display array
     *
     * @return array<string, mixed>
     */
    public static function getDefaultDisplay(): array
    {
        return [
            'unit' => 'C',
            'number' => '.,',
            'scale' => -1,
            'text' => 0,
            'raw' => false,
            'date' => '%Y-%m-%d',
            'time' => '%H:%M',
            'critical' => 0,
            'warning' => 0,
        ];
    }

    /**
     * Get default $dockerManPaths array
     * Source: /usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php
     *
     * @return array<string, string>
     */
    public static function getDefaultDockerManPaths(): array
    {
        global $docroot;
        $docroot = $docroot ?? '/usr/local/emhttp';
        return [
            'autostart-file' => '/var/lib/docker/unraid-autostart',
            'update-status' => '/var/lib/docker/unraid-update-status.json',
            'template-repos' => '/boot/config/plugins/dockerMan/template-repos',
            'templates-user' => '/boot/config/plugins/dockerMan/templates-user',
            'templates-usb' => '/boot/config/plugins/dockerMan/templates',
            'images' => '/var/lib/docker/unraid/images',
            'user-prefs' => '/boot/config/plugins/dockerMan/userprefs.cfg',
            'plugin' => "$docroot/plugins/dynamix.docker.manager",
            'images-ram' => "$docroot/state/plugins/dynamix.docker.manager/images",
            'webui-info' => "$docroot/state/plugins/dynamix.docker.manager/docker.json",
        ];
    }
}

/**
 * Mock Docker client for testing
 */
class MockDockerClient
{
    /** @var array<string, array<string, mixed>> Mock containers */
    private array $containers = [];

    /** @var array<string, mixed> Mock info */
    private array $info = [
        'ServerVersion' => '24.0.0',
        'OperatingSystem' => 'Slackware 15.0',
    ];

    /**
     * Set mock containers
     *
     * @param array<string, array<string, mixed>> $containers
     */
    public function setContainers(array $containers): void
    {
        $this->containers = $containers;
    }

    /**
     * Get containers (mock implementation)
     *
     * @param array<string, mixed> $filters
     * @return array<string, array<string, mixed>>
     */
    public function getContainers(array $filters = []): array
    {
        return $this->containers;
    }

    /**
     * Get Docker info
     *
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Set Docker info
     *
     * @param array<string, mixed> $info
     */
    public function setInfo(array $info): void
    {
        $this->info = array_merge($this->info, $info);
    }
}
