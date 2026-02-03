<?php

/**
 * Plugin Tests Framework - Docker Mocks
 *
 * Provides mock implementations of Docker-related classes and functions
 * that exist in the Unraid environment.
 */

declare(strict_types=1);

namespace PluginTests\Mocks {

    /**
     * Mock for Unraid's DockerUtil class
     */
    class DockerUtilMock
    {
        /** @var array<string, array<string, mixed>> Container data */
        private static array $containers = [];

        /** @var array<string, array{local: string|null, remote: string|null}> Image update status with SHA values */
        private static array $updateStatus = [];

        /** @var array<string, string> JSON file storage for loadJSON/saveJSON */
        private static array $jsonFiles = [];

        /**
         * Reset all mock data
         */
        public static function reset(): void
        {
            self::$containers = [];
            self::$updateStatus = [];
            self::$jsonFiles = [];
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
    }

} // end namespace PluginTests\Mocks

// ============================================================
// Global class definitions (mock implementations)
// ============================================================
namespace {

    use PluginTests\Mocks\DockerUtilMock;

    // Create the DockerUtil class if it doesn't exist
    if (!class_exists('DockerUtil')) {
        /**
         * Mock DockerUtil class matching Unraid's implementation
         */
        class DockerUtil
        {
            /**
             * Ensure image has a tag (adds :latest if missing)
             *
             * @param string $image Image name
             * @return string Image with tag
             */
            public static function ensureImageTag(string $image): string
            {
                // If no tag specified, add :latest
                if (strpos($image, ':') === false) {
                    // Handle library images (official Docker Hub images)
                    if (strpos($image, '/') === false) {
                        return 'library/' . $image . ':latest';
                    }
                    return $image . ':latest';
                }
                // Handle official images with tag but no library/ prefix
                if (strpos($image, '/') === false) {
                    $parts = explode(':', $image);
                    return 'library/' . $parts[0] . ':' . $parts[1];
                }
                return $image;
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

                // Fall back to real file if it exists
                if (is_file($path)) {
                    $content = file_get_contents($path);
                    if ($content) {
                        return json_decode($content, true) ?: [];
                    }
                }
                return [];
            }

            /**
             * Save JSON to a file
             *
             * @param string $path File path
             * @param array<string, mixed> $data Data to save
             * @return bool
             */
            public static function saveJSON(string $path, array $data): bool
            {
                // Store in mock storage
                DockerUtilMock::setJsonFile($path, $data);

                // Also write to real file if directory exists
                $dir = dirname($path);
                if (is_dir($dir)) {
                    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
                }
                return true;
            }
        }
    }

    // Create DockerUpdate class if it doesn't exist
    if (!class_exists('DockerUpdate')) {
        /**
         * Mock DockerUpdate class for checking container image updates
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
         * Mock DockerClient for API calls
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
                ];
            }
        }
    }

} // end global namespace
