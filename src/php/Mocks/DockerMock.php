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
            protected static function splitImage(string $image): ?array
            {
                if (false === preg_match('@^(.+/)*([^/:]+)(:[^:/]*)*$@', $image, $newSections) || count($newSections) < 3) {
                    return null;
                } else {
                    [, $strRepo, $imagePart, $strTag] = array_merge($newSections, ['']);
                    $strTag = str_replace(':', '', $strTag ?: '');
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
                if (!file_exists($path)) {
                    return [];
                }
                $content = @file_get_contents($path);
                if ($content === false) {
                    return [];
                }
                $objContent = json_decode($content, true);
                if (!is_array($objContent)) {
                    return [];
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
            /** @var list<array<string, mixed>>|null Containers cache */
            private static ?array $containersCache = null;

            /** @var array<string, array<string, mixed>>|null Images cache */
            private static ?array $imagesCache = null;

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
             * Reset all mock responses and caches
             */
            public static function reset(): void
            {
                self::$mockResponses = [];
                self::$containersCache = null;
                self::$imagesCache = null;
            }

            /**
             * Flush caches
             */
            public function flushCaches(): void
            {
                self::$containersCache = null;
                self::$imagesCache = null;
            }

            /**
             * Human readable timing
             *
             * @param int $time Unix timestamp
             * @return string
             */
            public function humanTiming(int $time): string
            {
                $time = time() - $time;
                $tokens = [
                    31536000 => 'year', 2592000 => 'month', 604800 => 'week',
                    86400 => 'day', 3600 => 'hour', 60 => 'minute', 1 => 'second'
                ];
                foreach ($tokens as $unit => $text) {
                    if ($time < $unit) continue;
                    $numberOfUnits = floor($time / $unit);
                    return $numberOfUnits . ' ' . $text . (($numberOfUnits == 1) ? '' : 's') . ' ago';
                }
                return 'just now';
            }

            /**
             * Format bytes to human readable
             *
             * @param int $size Size in bytes
             * @return string
             */
            public function formatBytes(int $size): string
            {
                if ($size == 0) return '0 B';
                $base = log($size) / log(1024);
                $suffix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
                return round(pow(1024, $base - floor($base)), 0) . ' ' . $suffix[(int)floor($base)];
            }

            /**
             * Make a Docker JSON API request (mock)
             *
             * @param string $url API endpoint
             * @param string $method HTTP method
             * @param mixed &$code Response code
             * @param callable|null $callback Callback
             * @param bool $unchunk Unchunk response
             * @param string|null $headers Additional headers
             * @return array<string, mixed>
             */
            public function getDockerJSON(
                string $url, 
                string $method = 'GET', 
                mixed &$code = null,
                ?callable $callback = null,
                bool $unchunk = false,
                ?string $headers = null
            ): array {
                $code = true;
                return self::$mockResponses[$url] ?? [];
            }

            /**
             * Check if container exists
             *
             * @param string $container Container name
             * @return bool
             */
            public function doesContainerExist(string $container): bool
            {
                foreach ($this->getDockerContainers() as $ct) {
                    if ($ct['Name'] == $container) return true;
                }
                return false;
            }

            /**
             * Check if image exists
             *
             * @param string $image Image name
             * @return bool
             */
            public function doesImageExist(string $image): bool
            {
                foreach ($this->getDockerImages() as $img) {
                    if (strpos($img['Tags'][0] ?? '', $image) !== false) return true;
                }
                return false;
            }

            /**
             * Get Docker info
             *
             * @return array<string, mixed>
             */
            public function getInfo(): array
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
             * Start a container
             *
             * @param string $id Container ID
             * @return bool|string
             */
            public function startContainer(string $id): bool|string
            {
                self::$containersCache = null;
                return true;
            }

            /**
             * Stop a container
             *
             * @param string $id Container ID
             * @param int|false $t Timeout
             * @return bool|string
             */
            public function stopContainer(string $id, int|false $t = false): bool|string
            {
                self::$containersCache = null;
                return true;
            }

            /**
             * Restart a container
             *
             * @param string $id Container ID
             * @return bool|string
             */
            public function restartContainer(string $id): bool|string
            {
                self::$containersCache = null;
                return true;
            }

            /**
             * Pause a container
             *
             * @param string $id Container ID
             * @return bool|string
             */
            public function pauseContainer(string $id): bool|string
            {
                self::$containersCache = null;
                return true;
            }

            /**
             * Resume a container
             *
             * @param string $id Container ID
             * @return bool|string
             */
            public function resumeContainer(string $id): bool|string
            {
                self::$containersCache = null;
                return true;
            }

            /**
             * Remove a container
             *
             * @param string $name Container name
             * @param string|false $id Container ID
             * @param int|false $cache Cache cleanup level
             * @return bool|string
             */
            public function removeContainer(string $name, string|false $id = false, int|false $cache = false): bool|string
            {
                self::$containersCache = null;
                return true;
            }

            /**
             * Pull an image
             *
             * @param string $image Image name
             * @param callable|null $callback Progress callback
             * @return array<string, mixed>
             */
            public function pullImage(string $image, ?callable $callback = null): array
            {
                self::$imagesCache = null;
                return [];
            }

            /**
             * Remove an image
             *
             * @param string $id Image ID
             * @return bool|string
             */
            public function removeImage(string $id): bool|string
            {
                self::$imagesCache = null;
                return true;
            }

            /**
             * Get registry auth info
             *
             * @param string $image Image name
             * @return array<string, string>
             */
            public function getRegistryAuth(string $image): array
            {
                $image = \DockerUtil::ensureImageTag($image);
                preg_match('@^([^/]+\.[^/]+/)?([^/]+/)?(.+:)(.+)$@', $image, $matches);
                $matches = array_pad($matches, 5, '');
                [, $registry, $repository, $imagePart, $tag] = $matches;
                return [
                    'username' => '',
                    'password' => '',
                    'registryName' => substr($registry, 0, -1),
                    'repository' => $repository,
                    'imageName' => substr($imagePart, 0, -1),
                    'imageTag' => $tag,
                    'apiUrl' => empty($registry) ? 'https://registry-1.docker.io/v2/' : 'https://' . $registry . 'v2/',
                ];
            }

            /**
             * Get containers list (raw array)
             *
             * @return list<array<string, mixed>>
             */
            public function getContainersList(): array
            {
                return array_values(DockerUtilMock::getContainers());
            }

            /**
             * Get Docker containers (Unraid's method name)
             *
             * @return list<array<string, mixed>>
             */
            public function getDockerContainers(): array
            {
                if (self::$containersCache !== null) {
                    return self::$containersCache;
                }
                self::$containersCache = array_values(DockerUtilMock::getContainers());
                return self::$containersCache;
            }

            /**
             * Get container ID by name
             *
             * @param string $container Container name
             * @return string|null
             */
            public function getContainerID(string $container): ?string
            {
                foreach ($this->getDockerContainers() as $ct) {
                    if (preg_match('%' . preg_quote($container, '%') . '%', $ct['Name'])) {
                        return $ct['Id'] ?? null;
                    }
                }
                return null;
            }

            /**
             * Get image ID by name
             *
             * @param string $image Image name
             * @return string|null
             */
            public function getImageID(string $image): ?string
            {
                if (!strpos($image, ':')) {
                    $image .= ':latest';
                }
                foreach ($this->getDockerImages() as $img) {
                    foreach ($img['Tags'] ?? [] as $tag) {
                        if ($image == $tag) {
                            return $img['Id'];
                        }
                    }
                }
                return null;
            }

            /**
             * Get image name by ID
             *
             * @param string $id Image ID
             * @return string|null
             */
            public function getImageName(string $id): ?string
            {
                foreach ($this->getDockerImages() as $img) {
                    if ($img['Id'] == $id) {
                        return $img['Tags'][0] ?? null;
                    }
                }
                return null;
            }

            /**
             * Get Docker images
             *
             * @return array<string, array<string, mixed>>
             */
            public function getDockerImages(): array
            {
                if (self::$imagesCache !== null) {
                    return self::$imagesCache;
                }
                $images = [];
                foreach (DockerUtilMock::getContainers() as $container) {
                    if (isset($container['Image'])) {
                        $id = substr(md5($container['Image']), 0, 12);
                        $images[$id] = [
                            'RepoTags' => [$container['Image']],
                            'Id' => $id,
                            'Created' => $this->humanTiming(time() - 3600),
                            'Size' => $this->formatBytes(100 * 1024 * 1024),
                            'VirtualSize' => $this->formatBytes(100 * 1024 * 1024),
                            'Repository' => \DockerUtil::parseImageTag($container['Image'])['strRepo'],
                            'usedBy' => [$container['Name'] ?? ''],
                        ];
                    }
                }
                self::$imagesCache = $images;
                return self::$imagesCache;
            }

            /**
             * Get container details
             *
             * @param string $id Container ID
             * @return array<string, mixed>
             */
            public function getContainerDetails(string $id): array
            {
                foreach (DockerUtilMock::getContainers() as $container) {
                    if (($container['Id'] ?? '') === $id || ($container['Name'] ?? '') === $id) {
                        return $container;
                    }
                }
                return [];
            }

            /**
             * Get container log
             *
             * @param string $id Container ID
             * @param callable $callback Log callback
             * @param int|null $tail Tail lines
             * @param int|null $since Since timestamp
             */
            public function getContainerLog(string $id, callable $callback, ?int $tail = null, ?int $since = null): void
            {
                // Mock: return empty log
                $callback('');
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

            /** @var array<string, list<array<string, mixed>>> Mock templates */
            private static array $templates = [];

            /**
             * Set mock templates
             *
             * @param array<string, list<array<string, mixed>>> $templates
             */
            public static function setTemplates(array $templates): void
            {
                self::$templates = $templates;
            }

            /**
             * Get templates by type
             *
             * @param string $type Template type
             * @return list<array<string, mixed>>
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
