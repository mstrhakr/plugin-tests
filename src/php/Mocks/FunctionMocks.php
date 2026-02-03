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
         * @return array<string, string|int|bool|null> Configuration array
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

    if (!function_exists('mk_option')) {
        /**
         * Generate an HTML option element for select dropdowns
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $select Current selected value
         * @param string $value Option value
         * @param string $text Option display text
         * @param string $extra Extra attributes
         * @return string HTML option element
         */
        function mk_option(string $select, string $value, string $text, string $extra = ""): string
        {
            return "<option value='$value'" . ($value == $select ? " selected" : "") . (strlen($extra) ? " $extra" : "") . ">$text</option>";
        }
    }

    if (!function_exists('my_disk')) {
        /**
         * Format disk name for display
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $name Disk name
         * @param bool $raw Return raw name
         * @return string Formatted disk name
         */
        function my_disk(string $name, bool $raw = false): string
        {
            global $display;
            return (_var($display, 'raw') || $raw) ? $name : ucfirst(preg_replace('/(\d+)$/', ' $1', $name));
        }
    }

    if (!function_exists('my_share')) {
        /**
         * Get share information (placeholder - actual implementation varies)
         *
         * @param string $name Share name
         * @return array<string, mixed> Share info
         */
        function my_share(string $name): array
        {
            global $shares;
            return $shares[$name] ?? [];
        }
    }

    if (!function_exists('_var')) {
        /**
         * Safe variable access with default
         * Source: /usr/local/emhttp/plugins/dynamix/include/Wrappers.php
         *
         * @param mixed $name Variable or array
         * @param string|null $key Array key
         * @param mixed $default Default value
         * @return mixed
         */
        function _var(mixed &$name, ?string $key = null, mixed $default = ''): mixed
        {
            return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default);
        }
    }

    if (!function_exists('my_scale')) {
        /**
         * Scale a value with units (KB, MB, GB, etc.)
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param float $value Value to scale
         * @param string &$unit Output unit
         * @param int|null $decimals Decimal places
         * @param int|null $scale Scale limit
         * @param int $kilo Base (1000 or 1024)
         * @return string Formatted value
         */
        function my_scale(float $value, string &$unit, ?int $decimals = null, ?int $scale = null, int $kilo = 1000): string
        {
            $units = ['', 'K', 'M', 'G', 'T', 'P', 'E'];
            $base = $value ? intval(floor(log($value, $kilo))) : 0;
            $base = min($base, count($units) - 1);
            if ($scale !== null && $base > $scale) {
                $base = $scale;
            }
            $value /= pow($kilo, $base);
            $unit = $units[$base] . 'B';
            $decimals = $decimals ?? ($value >= 100 ? 0 : ($value >= 10 ? 1 : 2));
            return number_format($value, $decimals);
        }
    }

    if (!function_exists('my_number')) {
        /**
         * Format number with thousand separators
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param int|float $value Value to format
         * @return string Formatted number
         */
        function my_number(int|float $value): string
        {
            return number_format($value, 0, '.', ($value >= 10000 ? ',' : ''));
        }
    }

    if (!function_exists('my_temp')) {
        /**
         * Format temperature value
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param int|float|string $value Temperature in Celsius
         * @return string Formatted temperature
         */
        function my_temp(int|float|string $value): string
        {
            global $display;
            $unit = _var($display, 'unit', 'C');
            if (!is_numeric($value)) {
                return (string)$value;
            }
            if ($unit == 'F') {
                $value = round(9 / 5 * $value) + 32;
            }
            return $value . '&#8201;&#176;' . $unit;
        }
    }

    if (!function_exists('my_time')) {
        /**
         * Format timestamp
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param int $time Unix timestamp
         * @param string|null $fmt Date format
         * @return string Formatted time
         */
        function my_time(int $time, ?string $fmt = null): string
        {
            if (!$time) {
                return 'unknown';
            }
            $fmt = $fmt ?? 'Y-m-d H:i:s';
            return date($fmt, $time);
        }
    }

    if (!function_exists('compress')) {
        /**
         * Compress a string to max length with ellipsis
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $name String to compress
         * @param int $size Max size
         * @param int $end Characters to keep at end
         * @return string Compressed string
         */
        function compress(string $name, int $size = 18, int $end = 6): string
        {
            return mb_strlen($name) <= $size ? $name : mb_substr($name, 0, $size - ($end ? $end + 3 : 0)) . '...' . ($end ? mb_substr($name, -$end) : '');
        }
    }

    if (!function_exists('my_explode')) {
        /**
         * Explode with guaranteed array size
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $split Delimiter
         * @param string $text String to split
         * @param int $count Expected array size
         * @return array<int, string>
         */
        function my_explode(string $split, string $text, int $count = 2): array
        {
            return array_pad(explode($split, $text ?? "", $count), $count, '');
        }
    }

    if (!function_exists('my_preg_split')) {
        /**
         * Preg split with guaranteed array size
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $split Regex pattern
         * @param string $text String to split
         * @param int $count Expected array size
         * @return array<int, string>
         */
        function my_preg_split(string $split, string $text, int $count = 2): array
        {
            return array_pad(preg_split($split, $text, $count) ?: [], $count, '');
        }
    }

    if (!function_exists('autov')) {
        /**
         * Add version parameter to file URL for cache busting
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $file File path
         * @param bool $ret Return instead of echo
         * @return string|null Versioned URL
         */
        function autov(string $file, bool $ret = false): ?string
        {
            $time = file_exists($file) ? filemtime($file) : time();
            $newFile = "$file?v=$time";
            if ($ret) {
                return $newFile;
            }
            echo $newFile;
            return null;
        }
    }

    if (!function_exists('pgrep')) {
        /**
         * Find process by name (mock - always returns false)
         * Source: /usr/local/emhttp/plugins/dynamix/include/Helpers.php
         *
         * @param string $process_name Process name
         * @param bool $escape_arg Escape argument
         * @return string|false PID or false
         */
        function pgrep(string $process_name, bool $escape_arg = true): string|false
        {
            return false;
        }
    }

    if (!function_exists('my_logger')) {
        /**
         * Log a message via logger command (mock)
         * Source: /usr/local/emhttp/plugins/dynamix/include/Wrappers.php
         *
         * @param string $message Message to log
         * @param string $logger Logger name
         */
        function my_logger(string $message, string $logger = 'webgui'): void
        {
            FunctionMocks::addLog($logger, $message);
        }
    }

    if (!function_exists('http_get_contents')) {
        /**
         * Fetch URL contents (mock - returns false)
         * Source: /usr/local/emhttp/plugins/dynamix/include/Wrappers.php
         *
         * @param string $url URL to fetch
         * @param array<int, mixed> $opts Curl options
         * @param array<string, mixed>|null &$getinfo Info output
         * @return string|false Content or false
         */
        function http_get_contents(string $url, array $opts = [], ?array &$getinfo = null): string|false
        {
            return false;
        }
    }

    if (!function_exists('check_network_connectivity')) {
        /**
         * Check network connectivity (mock - returns true)
         * Source: /usr/local/emhttp/plugins/dynamix/include/Wrappers.php
         *
         * @return bool
         */
        function check_network_connectivity(): bool
        {
            return true;
        }
    }

    if (!function_exists('lan_port')) {
        /**
         * Check if network port exists/is up
         * Source: /usr/local/emhttp/plugins/dynamix/include/Wrappers.php
         *
         * @param string $port Port name (eth0, br0, etc.)
         * @param bool $state Check state
         * @return bool|int
         */
        function lan_port(string $port, bool $state = false): bool|int
        {
            // Mock: eth0 exists and is up
            if ($port === 'eth0') {
                return $state ? 1 : true;
            }
            return false;
        }
    }

    if (!function_exists('ipaddr')) {
        /**
         * Get IP address for interface
         * Source: /usr/local/emhttp/plugins/dynamix/include/Wrappers.php
         *
         * @param string $ethX Interface name
         * @param int $prot IP version (4 or 6)
         * @return string|array<int, string>
         */
        function ipaddr(string $ethX = 'eth0', int $prot = 4): string|array
        {
            $ipv4 = '192.168.1.100';
            $ipv6 = 'fe80::1';
            return match ($prot) {
                4 => $ipv4,
                6 => $ipv6,
                default => [$ipv4, $ipv6],
            };
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
        // Determine level name - use constants if defined, otherwise use raw values
        // @phpstan-ignore-next-line
        $level = match (true) {
            $priority === (defined('LOG_ERR') ? LOG_ERR : 3) => 'ERROR',
            $priority === (defined('LOG_WARNING') ? LOG_WARNING : 4) => 'WARNING',
            $priority === (defined('LOG_DEBUG') ? LOG_DEBUG : 7) => 'DEBUG',
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
