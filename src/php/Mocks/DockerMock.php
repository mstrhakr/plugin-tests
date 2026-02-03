<?php

/**
 * Plugin Tests Framework - Docker Mocks
 *
 * Provides mock implementations of Docker-related classes and functions
 * that exist in the Unraid environment.
 */

declare(strict_types=1);

namespace PluginTests\Mocks;

/**
 * Mock for Unraid's DockerUtil class
 */
class DockerUtilMock
{
    /** @var array<string, array<string, mixed>> Container data */
    private static array $containers = [];

    /** @var array<string, string> Image update status */
    private static array $updateStatus = [];

    /**
     * Reset all mock data
     */
    public static function reset(): void
    {
        self::$containers = [];
        self::$updateStatus = [];
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
     * Set update status for an image
     *
     * @param string $image Image name
     * @param string $status Status (true/false/undef)
     */
    public static function setUpdateStatus(string $image, string $status): void
    {
        self::$updateStatus[$image] = $status;
    }

    /**
     * Get update status for an image
     *
     * @param string $image
     * @return string|null
     */
    public static function getUpdateStatus(string $image): ?string
    {
        return self::$updateStatus[$image] ?? null;
    }
}

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
         * Check if image has update available
         *
         * @param string $image Image name
         * @return string|null 'true', 'false', or null if unknown
         */
        public static function getUpdateStatus(string $image): ?string
        {
            return DockerUtilMock::getUpdateStatus($image);
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
         * Get containers list
         *
         * @return array<int, array<string, mixed>>
         */
        public function getContainersList(): array
        {
            return array_values(DockerUtilMock::getContainers());
        }
    }
}
