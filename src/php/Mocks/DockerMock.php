<?php

/**
 * Plugin Tests Framework - Docker Mocks
 *
 * Provides mock implementations of Docker-related classes and functions
 * that exist in the Unraid environment.
 * 
 * Based on Unraid's dynamix.docker.manager plugin:
 * - /usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php
 * - /usr/local/emhttp/plugins/dynamix.docker.manager/include/Helpers.php
 */

declare(strict_types=1);

namespace PluginTests\Mocks {

    /**
     * Mock data store for Unraid's DockerUtil class
     */
    class DockerUtilMock
    {
        /** @var array<string, array<string, mixed>> Container data */
        private static array $containers = [];

        /** @var array<string, array{local: string|null, remote: string|null}> Image update status with SHA values */
        private static array $updateStatus = [];

        /** @var array<string, string> JSON file storage for loadJSON/saveJSON */
        private static array $jsonFiles = [];

        /** @var string Mock host IP */
        private static string $hostIP = '192.168.1.100';

        /** @var array<string, string> Network drivers */
        private static array $networkDrivers = ['bridge' => 'bridge', 'host' => 'host', 'none' => 'null'];

        /**
         * Reset all mock data
         */
        public static function reset(): void
        {
            self::$containers = [];
            self::$updateStatus = [];
            self::$jsonFiles = [];
            self::$hostIP = '192.168.1.100';
            self::$networkDrivers = ['bridge' => 'bridge', 'host' => 'host', 'none' => 'null'];
        }

        /**
         * Set container data for testing
         *
         * @param array<string, array<string, mixed>> $containers
         */
        public static function setContainers(array $containers): void
        {
            self::$containers = $containers;
        }

        /**
         * Get all containers
         *
         * @return array<string, array<string, mixed>>
         */
        public static function getContainers(): array
        {
            return self::$containers;
        }

        /**
         * Set update status for an image (with SHA values)
         *
         * @param string $image Image name
         * @param string|null $localSha Local image SHA
         * @param string|null $remoteSha Remote image SHA
         */
        public static function setUpdateStatus(string $image, ?string $localSha, ?string $remoteSha): void
        {
            self::$updateStatus[$image] = [
                'local' => $localSha,
                'remote' => $remoteSha,
            ];
        }

        /**
         * Get update status for an image
         *
         * @param string $image
         * @return array{local: string|null, remote: string|null}|null
         */
        public static function getUpdateStatus(string $image): ?array
        {
            return self::$updateStatus[$image] ?? null;
        }

        /**
         * Get all update statuses
         *
         * @return array<string, array{local: string|null, remote: string|null}>
         */
        public static function getAllUpdateStatus(): array
        {
            return self::$updateStatus;
        }

        /**
         * Set JSON file content (for loadJSON mock)
         *
         * @param string $path File path
         * @param array<string, mixed> $data Data to store
         */
        public static function setJsonFile(string $path, array $data): void
        {
            self::$jsonFiles[$path] = json_encode($data) ?: '{}';
        }

        /**
         * Get JSON file content
         *
         * @param string $path File path
         * @return array<string, mixed>
         */
        public static function getJsonFile(string $path): array
        {
            if (!isset(self::$jsonFiles[$path])) {
                return [];
            }
            return json_decode(self::$jsonFiles[$path], true) ?: [];
        }

        /**
         * Set host IP for testing
         */
        public static function setHostIP(string $ip): void
        {
            self::$hostIP = $ip;
        }

        /**
         * Get host IP
         */
        public static function getHostIP(): string
        {
            return self::$hostIP;
        }

        /**
         * Set network drivers
         * @param array<string, string> $drivers
         */
        public static function setNetworkDrivers(array $drivers): void
        {
            self::$networkDrivers = $drivers;
        }

        /**
         * Get network drivers
         * @return array<string, string>
         */
        public static function getNetworkDrivers(): array
        {
            return self::$networkDrivers;
        }
    }

} // end namespace PluginTests\Mocks

// ============================================================
// Global class definitions (mock implementations)
// Based on actual Unraid source from DockerClient.php
// ============================================================
namespace {

    use PluginTests\Mocks\DockerUtilMock;

    // Create the DockerUtil class if it doesn't exist
    if (!class_exists('DockerUtil')) {
        /**
         * Mock DockerUtil class matching Unraid's implementation
         * Source: /usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php
         */
        class DockerUtil
        {
            /**
             * Ensure image has a tag (adds :latest if missing)
             * Matches Unraid's actual implementation
             *
             * @param string $image Image name
             * @return string Image with tag
             */
            public static function ensureImageTag(string $image): string
            {
                extract(static::parseImageTag($image));
                return "$strRepo:$strTag";
            }

            /**
             * Parse image into repo and tag components
             * Matches Unraid's actual implementation
             *
             * @param string $image
             * @return array{strRepo: string, strTag: string}
             */
            public static function parseImageTag(string $image): array
            {
                $strRepo = '';
                $strTag = '';
                
                if (strpos($image, 'sha256:') === 0) {
                    // sha256 was provided instead of actual repo name so truncate it for display
                    $strRepo = substr($image, 7, 12);
                } elseif (strpos($image, '/') === false) {
                    return static::parseImageTag('library/' . $image);
                } else {
                    $parsedImage = static::splitImage($image);
                    if (!empty($parsedImage)) {
                        $strRepo = $parsedImage['strRepo'];
                        $strTag = $parsedImage['strTag'];
                    } else {
                        // Unprocessable input
                        $strRepo = $image;
                    }
                }
                // Add :latest tag to image if it's absent
                if (empty($strTag)) {
                    $strTag = 'latest';
                }
                return array_map('trim', ['strRepo' => $strRepo, 'strTag' => $strTag]);
            }

            /**
             * Split image string into components
             *
             * @param string $image
             * @return array{strRepo: string, strTag: string}|null
             */
            private static function splitImage(string $image): ?array
            {
                if (false === preg_match('@^(.+/)*([^/:]+)(:[^:/]*)*$@', $image, $newSections) || count($newSections) < 3) {
                    return null;
                } else {
                    [, $strRepo, $imagePart, $strTag] = array_merge($newSections, ['']);
                    $strTag = str_replace(':', '', $strTag ?? '');
                    return [
                        'strRepo' => $strRepo . $imagePart,
                        'strTag' => $strTag,
                    ];
                }
            }

            /**
             * Get container info by name
             *
             * @param string $name Container name
             * @return array<string, mixed>|null
             */
            public static function getContainer(string $name): ?array
            {
                $containers = DockerUtilMock::getContainers();
                return $containers[$name] ?? null;
            }

            /**
             * Get all running containers
             *
             * @return array<string, array<string, mixed>>
             */
            public static function getRunningContainers(): array
            {
                return array_filter(
                    DockerUtilMock::getContainers(),
                    fn($c) => ($c['State'] ?? '') === 'running'
                );
            }

            /**
             * Load JSON from a file
             * Matches Unraid's actual implementation
             *
             * @param string $path File path
             * @return array<string, mixed>
             */
            public static function loadJSON(string $path): array
            {
                // Check mock storage first
                $mockData = DockerUtilMock::getJsonFile($path);
                if (!empty($mockData)) {
                    return $mockData;
                }

                // Match Unraid's implementation
                $objContent = (file_exists($path)) ? json_decode(@file_get_contents($path), true) : [];
                if (empty($objContent)) {
                    $objContent = [];
                }
                return $objContent;
            }

            /**
             * Save JSON to a file
             * Matches Unraid's actual implementation
             *
             * @param string $path File path
             * @param array<string, mixed> $content Data to save
             * @return int|false
             */
            public static function saveJSON(string $path, array $content): int|false
            {
                // Store in mock storage
                DockerUtilMock::setJsonFile($path, $content);

                // Match Unraid's implementation
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                return file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            /**
             * Execute docker command (mock)
             *
             * @param string $cmd Docker command
             * @param bool $a Return array if true
             * @return string|array<int, string>
             */
            public static function docker(string $cmd, bool $a = false): string|array
            {
                // Mock implementation - return empty
                return $a ? [] : '';
            }

            /**
             * Get container IP address
             *
             * @param string $name Container name
             * @param int $version IP version (4 or 6)
             * @return string
             */
            public static function myIP(string $name, int $version = 4): string
            {
                $container = DockerUtilMock::getContainers()[$name] ?? null;
                if ($container && isset($container['IPAddress'])) {
                    return $container['IPAddress'];
                }
                return '';
            }

            /**
             * Get network drivers
             *
             * @return array<string, string>
             */
            public static function driver(): array
            {
                return DockerUtilMock::getNetworkDrivers();
            }

            /**
             * Get custom networks
             *
             * @return array<int, string>
             */
            public static function custom(): array
            {
                $drivers = DockerUtilMock::getNetworkDrivers();
                return array_keys(array_filter($drivers, fn($d) => $d === 'bridge' || $d === 'macvlan' || $d === 'ipvlan'));
            }

            /**
             * Get network list with subnets
             *
             * @param array<int, string> $custom Custom networks
             * @return array<string, string>
             */
            public static function network(array $custom): array
            {
                $list = ['bridge' => '', 'host' => '', 'none' => ''];
                foreach ($custom as $net) {
                    $list[$net] = '172.17.0.0/16'; // Mock subnet
                }
                return $list;
            }

            /**
             * Get available CPUs
             *
             * @return array<int, string>
             */
            public static function cpus(): array
            {
                return ['0', '1', '2', '3']; // Mock 4 CPUs
            }

            /**
             * Map container to value
             *
             * @param string $ct Container name
             * @param string $type Type to get
             * @return string
             */
            public static function ctMap(string $ct, string $type = 'Name'): string
            {
                $container = DockerUtilMock::getContainers()[$ct] ?? null;
                return $container[$type] ?? '';
            }

            /**
             * Get network port
             *
             * @return string
             */
            public static function port(): string
            {
                return 'eth0';
            }

            /**
             * Get host IP address
             *
             * @return string
             */
            public static function host(): string
            {
                return DockerUtilMock::getHostIP();
            }
        }
    }

    // Create DockerUpdate class if it doesn't exist
    if (!class_exists('DockerUpdate')) {
        /**
         * Mock DockerUpdate class for checking container image updates
         * Source: /usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerUpdate.php
         */
        class DockerUpdate
        {
            /** @var array<string, bool|null> Cached update status */
            private array $statusCache = [];

            /**
             * Reload update status for an image
             *
             * @param string $image Image name
             * @return void
             */
            public function reloadUpdateStatus(string $image): void
            {
                $status = DockerUtilMock::getUpdateStatus($image);

                if ($status === null) {
                    // Unknown image
                    $this->statusCache[$image] = null;
                    return;
                }

                // Compare local and remote SHA
                if ($status['local'] === null || $status['remote'] === null) {
                    $this->statusCache[$image] = null;
                } elseif ($status['local'] === $status['remote']) {
                    $this->statusCache[$image] = true; // Up to date
                } else {
                    $this->statusCache[$image] = false; // Update available
                }
            }

            /**
             * Get update status for an image
             *
             * @param string $image Image name
             * @return bool|null true = up to date, false = update available, null = unknown
             */
            public function getUpdateStatus(string $image): ?bool
            {
                return $this->statusCache[$image] ?? null;
            }
        }
    }

    // Create DockerClient mock if needed
    if (!class_exists('DockerClient')) {
        /**
         * Mock DockerClient for Docker API calls
         * Source: /usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php
         */
        class DockerClient
        {
            /** @var array<string, mixed> Mock responses */
            private static array $mockResponses = [];

            /**
             * Set a mock response for an API call
             *
             * @param string $endpoint API endpoint
             * @param mixed $response Response data
             */
            public static function setMockResponse(string $endpoint, mixed $response): void
            {
                self::$mockResponses[$endpoint] = $response;
            }

            /**
             * Reset all mock responses
             */
            public static function reset(): void
            {
                self::$mockResponses = [];
            }

            /**
             * Make an API request (returns mock data)
             *
             * @param string $method HTTP method
             * @param string $endpoint API endpoint
             * @param array<string, mixed> $params Parameters
             * @return mixed
             */
            public function request(string $method, string $endpoint, array $params = []): mixed
            {
                return self::$mockResponses[$endpoint] ?? [];
            }

            /**
             * Get containers list (raw array)
             *
             * @return array<int, array<string, mixed>>
             */
            public function getContainersList(): array
            {
                return array_values(DockerUtilMock::getContainers());
            }

            /**
             * Get Docker containers (Unraid's method name)
             *
             * @return array<int, array<string, mixed>>
             */
            public function getDockerContainers(): array
            {
                return array_values(DockerUtilMock::getContainers());
            }

            /**
             * Get Docker info
             *
             * @return array<string, mixed>
             */
            public function getDockerInfo(): array
            {
                return [
                    'ServerVersion' => '24.0.0',
                    'OperatingSystem' => 'Slackware 15.0',
                    'Architecture' => 'x86_64',
                    'NCPU' => 4,
                    'MemTotal' => 16 * 1024 * 1024 * 1024,
                ];
            }

            /**
             * Get Docker images
             *
             * @return array<int, array<string, mixed>>
             */
            public function getDockerImages(): array
            {
                $images = [];
                foreach (DockerUtilMock::getContainers() as $container) {
                    if (isset($container['Image'])) {
                        $images[] = [
                            'RepoTags' => [$container['Image']],
                            'Id' => 'sha256:' . substr(md5($container['Image']), 0, 12),
                        ];
                    }
                }
                return $images;
            }
        }
    }

    // Create DockerTemplates mock if needed
    if (!class_exists('DockerTemplates')) {
        /**
         * Mock DockerTemplates for template management
         * Source: /usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php
         */
        class DockerTemplates
        {
            public bool $verbose = false;

            /** @var array<string, array<string, mixed>> Mock templates */
            private static array $templates = [];

            /**
             * Set mock templates
             *
             * @param array<string, array<string, mixed>> $templates
             */
            public static function setTemplates(array $templates): void
            {
                self::$templates = $templates;
            }

            /**
             * Get templates by type
             *
             * @param string $type Template type
             * @return array<int, array<string, mixed>>
             */
            public function getTemplates(string $type): array
            {
                return self::$templates[$type] ?? [];
            }

            /**
             * Get template value for a repository
             *
             * @param string $Repository Docker repository
             * @param string $field Field to get
             * @param string $scope Scope
             * @param string $name Name
             * @return string|null
             */
            public function getTemplateValue(string $Repository, string $field, string $scope = 'all', string $name = ''): ?string
            {
                return null;
            }

            /**
             * Get user template for container
             *
             * @param string $Container Container name
             * @return string|false
             */
            public function getUserTemplate(string $Container): string|false
            {
                return false;
            }

            /**
             * Download templates from repos
             *
             * @param string|null $Dest Destination path
             * @param string|null $Urls URL file
             * @return array<string, array<int, string>>|null
             */
            public function downloadTemplates(?string $Dest = null, ?string $Urls = null): ?array
            {
                return [];
            }

            /**
             * Get all container info
             *
             * @param bool $reload Reload data
             * @param bool $com Check community apps
             * @param bool $communityApplications Use community applications
             * @return array<string, mixed>
             */
            public function getAllInfo(bool $reload = false, bool $com = true, bool $communityApplications = false): array
            {
                return [];
            }
        }
    }

} // end global namespace
