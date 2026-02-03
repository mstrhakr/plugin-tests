<?php

/**
 * Plugin Tests Framework - Function Mocks
 *
 * Provides mock implementations of platform-specific PHP functions
 * that don't exist outside the target environment.
 */

declare(strict_types=1);

namespace PluginTests\Mocks {

    /**
     * Registry for function mock data
     */
    class FunctionMocks
    {
        /** @var array<string, array<string, mixed>> Plugin configurations */
        private static array $pluginConfigs = [];

        /** @var array<int, array{level: string, message: string}> Captured log messages */
        private static array $logs = [];

        /** @var string CSRF token value */
        private static string $csrfToken = 'test-csrf-token-12345';

        /** @var array<string, mixed> Scale status values */
        private static array $scaleStatus = [];

        /**
         * Reset all mocks to defaults
         */
        public static function reset(): void
        {
            self::$pluginConfigs = [];
            self::$logs = [];
            self::$csrfToken = 'test-csrf-token-12345';
            self::$scaleStatus = [];
        }

        /**
         * Set configuration for a plugin
         *
         * @param string $plugin Plugin name
         * @param array<string, mixed> $config Configuration values
         */
        public static function setPluginConfig(string $plugin, array $config): void
        {
            self::$pluginConfigs[$plugin] = $config;
        }

        /**
         * Get configuration for a plugin
         *
         * @param string $plugin Plugin name
         * @return array<string, mixed>
         */
        public static function getPluginConfig(string $plugin): array
        {
            return self::$pluginConfigs[$plugin] ?? [];
        }

        /**
         * Add a log entry
         *
         * @param string $level Log level
         * @param string $message Log message
         */
        public static function addLog(string $level, string $message): void
        {
            self::$logs[] = ['level' => $level, 'message' => $message];
        }

        /**
         * Get all log entries
         *
         * @return array<int, array{level: string, message: string}>
         */
        public static function getLogs(): array
        {
            return self::$logs;
        }

        /**
         * Set CSRF token
         */
        public static function setCsrfToken(string $token): void
        {
            self::$csrfToken = $token;
        }

        /**
         * Get CSRF token
         */
        public static function getCsrfToken(): string
        {
            return self::$csrfToken;
        }

        /**
         * Set scale status values
         *
         * @param array<string, mixed> $status
         */
        public static function setScaleStatus(array $status): void
        {
            self::$scaleStatus = $status;
        }

        /**
         * Get scale status
         *
         * @return array<string, mixed>
         */
        public static function getScaleStatus(): array
        {
            return self::$scaleStatus;
        }
    }

} // end namespace PluginTests\Mocks

// ============================================================
// Global function definitions (mock implementations)
// ============================================================
namespace {

    use PluginTests\Mocks\FunctionMocks;

    if (!function_exists('parse_plugin_cfg')) {
        /**
         * Parse plugin configuration file
         *
         * @param string $plugin Plugin name
         * @param bool $default Whether to merge with defaults
         * @return array<string, mixed> Configuration array
         */
        function parse_plugin_cfg(string $plugin, bool $default = false): array
        {
            $config = FunctionMocks::getPluginConfig($plugin);

            // If no mock config set, try to load from default.cfg in a standard location
            if (empty($config) && $default) {
                // Return empty array - in real tests, you'd mock this
                return [];
            }

            return $config;
        }
    }

    if (!function_exists('autov')) {
        /**
         * Add version query string to asset path for cache busting
         *
         * @param string $path Asset path
         * @return string Path with version query string
         */
        function autov(string $path): string
        {
            return $path . '?v=test';
        }
    }

    if (!function_exists('csrf_token')) {
        /**
         * Get CSRF token for form submissions
         *
         * @return string CSRF token
         */
        function csrf_token(): string
        {
            return FunctionMocks::getCsrfToken();
        }
    }

    if (!function_exists('my_scale_status')) {
        /**
         * Get system scale status
         *
         * @param string $key Status key
         * @return mixed Status value
         */
        function my_scale_status(string $key = ''): mixed
        {
            $status = FunctionMocks::getScaleStatus();

            if ($key === '') {
                return $status;
            }

            return $status[$key] ?? null;
        }
    }

    if (!function_exists('_')) {
        /**
         * Translation function (returns input unchanged in tests)
         *
         * @param string $text Text to translate
         * @return string Translated text
         */
        function _(string $text): string
        {
            return $text;
        }
    }

    if (!function_exists('notify')) {
        /**
         * Send a notification
         *
         * @param string $event Event type
         * @param string $subject Subject
         * @param string $description Description
         * @param string $importance Importance level
         * @param string $link Optional link
         */
        function notify(
            string $event,
            string $subject,
            string $description = '',
            string $importance = 'normal',
            string $link = ''
        ): void {
            FunctionMocks::addLog('NOTIFY', "$event: $subject - $description");
        }
    }

    /**
     * Testable logging function - ALWAYS captures to FunctionMocks for testing.
     * Use this instead of syslog() in plugin code to enable log assertion in tests.
     *
     * @param int $priority Log priority (LOG_INFO, LOG_WARNING, LOG_ERR, LOG_DEBUG)
     * @param string $message Log message
     * @param bool $alsoSyslog Whether to also call real syslog (default: true in production)
     * @return bool
     */
    function plugin_log(int $priority, string $message, bool $alsoSyslog = true): bool
    {
        // Determine level name
        $level = match ($priority) {
            LOG_ERR, 3 => 'ERROR',
            LOG_WARNING, 4 => 'WARNING',
            LOG_DEBUG, 7 => 'DEBUG',
            default => 'INFO',
        };

        // Always capture to mock system for testability
        FunctionMocks::addLog($level, $message);

        // Optionally also call real syslog if it exists and requested
        if ($alsoSyslog && function_exists('syslog')) {
            return \syslog($priority, $message);
        }

        return true;
    }

    // Register syslog functions if not available (Windows)
    if (!function_exists('syslog')) {
        define('LOG_INFO', 6);
        define('LOG_WARNING', 4);
        define('LOG_ERR', 3);
        define('LOG_DEBUG', 7);
        define('LOG_PID', 1);
        define('LOG_LOCAL7', 184);

        function openlog(string $ident, int $option, int $facility): bool
        {
            return true;
        }

        function syslog(int $priority, string $message): bool
        {
            $level = match ($priority) {
                LOG_ERR => 'ERROR',
                LOG_WARNING => 'WARNING',
                LOG_DEBUG => 'DEBUG',
                default => 'INFO',
            };
            FunctionMocks::addLog($level, $message);
            return true;
        }

        function closelog(): bool
        {
            return true;
        }
    }

} // end global namespace
