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
     * Get a typical compose stack configuration for testing
     *
     * @param string $name Stack name
     * @param string $path Stack path
     * @return array<string, mixed>
     */
    public static function composeStack(string $name = 'mystack', string $path = '/mnt/user/appdata/mystack'): array
    {
        return [
            'name' => $name,
            'path' => $path,
            'compose_file' => "$path/docker-compose.yml",
            'autostart' => 'yes',
            'autoupdate' => 'no',
            'priority' => '50',
        ];
    }

    /**
     * Get default compose manager configuration
     *
     * @return array<string, mixed>
     */
    public static function composeManagerConfig(): array
    {
        return [
            'COMPOSE_HTTP_TIMEOUT' => '300',
            'AUTOSTART' => 'yes',
            'AUTOSTART_WAIT_FOR_DOCKER' => 'yes',
            'AUTOSTART_DOCKER_WAIT_TIMEOUT' => '60',
            'AUTOSTART_TIMEOUT' => '300',
            'SHUTDOWN_TIMEOUT' => '30',
            'MAX_RETRIES' => '3',
            'RETRY_DELAY' => '5',
            'DEBUG' => 'no',
        ];
    }
}
