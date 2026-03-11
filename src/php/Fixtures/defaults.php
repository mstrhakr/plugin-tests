<?php

/**
 * Plugin Tests Framework - Default Fixtures
 *
 * Default values for common mock scenarios.
 */

declare(strict_types=1);

namespace PluginTests\Fixtures;

/**
 * Default fixture data for common test scenarios
 */
class Defaults
{
    /**
     * Get a typical disk configuration
     *
     * @param string $name Disk name (e.g., 'disk1')
     * @param string $status Disk status
     * @return array<string, mixed>
     */
    public static function disk(string $name = 'disk1', string $status = 'DISK_OK'): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'type' => 'Data',
            'device' => 'sda',
            'size' => '4000787030016',
            'fsSize' => '3907017728',
            'fsFree' => '1953508864',
            'fsType' => 'xfs',
            'temp' => '35',
        ];
    }

    /**
     * Get a typical share configuration
     *
     * @param string $name Share name
     * @return array<string, mixed>
     */
    public static function share(string $name = 'appdata'): array
    {
        return [
            'name' => $name,
            'comment' => "User share: $name",
            'allocator' => 'highwater',
            'floor' => '0',
            'splitLevel' => '',
            'include' => '',
            'exclude' => '',
            'useCache' => 'yes',
            'cow' => 'auto',
        ];
    }

    /**
     * Get a typical Docker container fixture
     *
     * @param string $name Container name
     * @param string $image Image name
     * @param string $state Container state (running, exited, paused)
     * @return array<string, mixed>
     */
    public static function container(string $name = 'test-container', string $image = 'alpine:latest', string $state = 'running'): array
    {
        return [
            'Name' => $name,
            'Image' => $image,
            'State' => $state,
            'Status' => $state === 'running' ? 'Up 2 hours' : 'Exited (0) 1 hour ago',
            'Id' => hash('sha256', $name),
            'Created' => '2025-01-01T00:00:00Z',
            'Ports' => [],
            'Mounts' => [],
            'Labels' => [],
            'NetworkSettings' => [
                'Networks' => [
                    'bridge' => [
                        'IPAddress' => '172.17.0.2',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get a typical user configuration
     *
     * @param string $name Username
     * @param string $desc User description
     * @return array<string, mixed>
     */
    public static function user(string $name = 'testuser', string $desc = ''): array
    {
        return [
            'name' => $name,
            'desc' => $desc,
            'idx' => '1000',
        ];
    }

    /**
     * Get a typical pool (cache) configuration
     *
     * @param string $name Pool name
     * @param string $fsType Filesystem type
     * @return array<string, mixed>
     */
    public static function pool(string $name = 'cache', string $fsType = 'btrfs'): array
    {
        return [
            'name' => $name,
            'fsType' => $fsType,
            'slots' => '1',
            'devices' => 'nvme0n1',
            'fsSize' => '500107862016',
            'fsFree' => '250053931008',
            'fsStatus' => 'Mounted',
        ];
    }

    /**
     * Get a typical notification fixture
     *
     * @param string $event Notification event
     * @param string $subject Notification subject
     * @param string $importance Notification importance (normal, warning, alert)
     * @return array<string, mixed>
     */
    public static function notification(string $event = 'test', string $subject = 'Test Notification', string $importance = 'normal'): array
    {
        return [
            'event' => $event,
            'subject' => $subject,
            'description' => '',
            'importance' => $importance,
            'timestamp' => time(),
        ];
    }

    /**
     * Get a typical network interface configuration
     *
     * @param string $name Interface name
     * @return array<string, mixed>
     */
    public static function networkInterface(string $name = 'eth0'): array
    {
        return [
            'name' => $name,
            'ipaddr' => '192.168.1.100',
            'netmask' => '255.255.255.0',
            'gateway' => '192.168.1.1',
            'mtu' => '1500',
            'bonding' => 'no',
            'bridging' => 'no',
        ];
    }

    /**
     * Get a typical plugin info fixture
     *
     * @param string $name Plugin name
     * @param string $version Plugin version
     * @return array<string, mixed>
     */
    public static function pluginInfo(string $name = 'test.plugin', string $version = '2025.01.01'): array
    {
        return [
            'name' => $name,
            'version' => $version,
            'author' => 'Test Author',
            'pluginURL' => '',
            'support' => '',
            'icon' => 'icon-plugin',
            'launch' => '',
        ];
    }
}
