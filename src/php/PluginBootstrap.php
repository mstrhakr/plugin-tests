<?php

/**
 * Plugin Tests Framework - Plugin Bootstrap Helper
 * 
 * Provides a simple API for setting up plugin test environments.
 * Handles path mapping, mock content, and common Unraid system file stubs.
 * 
 * Usage in your plugin's tests/bootstrap.php:
 * 
 *   require_once __DIR__ . '/framework/src/php/bootstrap.php';
 *   
 *   use PluginTests\PluginBootstrap;
 *   
 *   PluginBootstrap::init('myplugin', __DIR__ . '/../source/myplugin');
 */

declare(strict_types=1);

namespace PluginTests;

use PluginTests\StreamWrapper\UnraidStreamWrapper;
use PluginTests\Mocks\FunctionMocks;

class PluginBootstrap
{
    /** @var string|null Current plugin name */
    private static ?string $pluginName = null;
    
    /** @var string|null Source root path */
    private static ?string $sourceRoot = null;
    
    /** @var bool Whether bootstrap has been initialized */
    private static bool $initialized = false;
    
    /**
     * Initialize the test environment for a plugin
     * 
     * @param string $pluginName The plugin name (e.g., 'compose.manager')
     * @param string $sourceRoot Absolute path to the plugin's source PHP directory
     * @param array<string, mixed> $options Optional configuration:
     *   - 'config' => array: Plugin config values for parse_plugin_cfg()
     *   - 'autoMapFiles' => bool: Auto-map all .php files in sourceRoot (default: true)
     *   - 'subPath' => string: Subpath within /usr/local/emhttp/plugins/{name}/ (default: 'php')
     */
    public static function init(string $pluginName, string $sourceRoot, array $options = []): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$pluginName = $pluginName;
        $resolved = realpath($sourceRoot);
        
        if ($resolved === false) {
            throw new \RuntimeException("Source root does not exist: $sourceRoot");
        }
        
        self::$sourceRoot = $resolved;
        
        // Normalize path separators
        self::$sourceRoot = str_replace('/', DIRECTORY_SEPARATOR, self::$sourceRoot);
        
        // Set up mock content for common Unraid system files
        self::setupSystemMocks();
        
        // Set up temp directories for testing
        self::setupTempDirectories();
        
        // Auto-map source files if enabled (default: true)
        $autoMap = $options['autoMapFiles'] ?? true;
        $subPath = $options['subPath'] ?? 'php';
        
        if ($autoMap) {
            self::autoMapSourceFiles($subPath);
        }
        
        // Set plugin config if provided
        if (isset($options['config']) && is_array($options['config'])) {
            FunctionMocks::setPluginConfig($pluginName, $options['config']);
        }
        
        // Register the stream wrapper
        UnraidStreamWrapper::register();
        
        self::$initialized = true;
    }
    
    /**
     * Set up mock stubs for common Unraid system files
     */
    private static function setupSystemMocks(): void
    {
        // Dynamix Wrappers.php - file_put_contents_atomic, my_parse_ini_*, etc.
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/plugins/dynamix/include/Wrappers.php',
            "<?php\n// Mock Wrappers.php - functions provided by plugin-tests framework\n"
        );
        
        // Dynamix Helpers.php - various helper functions
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/plugins/dynamix/include/Helpers.php',
            "<?php\n// Mock Helpers.php - functions provided by plugin-tests framework\n"
        );
        
        // Dynamix Translations.php - _() translation function
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/plugins/dynamix/include/Translations.php',
            "<?php\n// Mock Translations.php - _() function provided by plugin-tests framework\n"
        );
        
        // Docker Manager DockerClient.php - DockerClient class
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php',
            "<?php\n// Mock DockerClient.php - classes provided by plugin-tests framework\n"
        );
        
        // Docker Manager CreateDocker.php - Docker container creation UI
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php',
            "<?php\n// Mock CreateDocker.php - container creation helpers\n"
        );
        
        // Plugin Manager PluginHelpers.php - plugin management functions
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/plugins/dynamix.plugin.manager/include/PluginHelpers.php',
            "<?php\n// Mock PluginHelpers.php - plugin management helpers\n"
        );
        
        // WebGui Markdown.php - Markdown parsing
        UnraidStreamWrapper::addMockContent(
            '/usr/local/emhttp/webGui/include/Markdown.php',
            "<?php\n// Mock Markdown.php - Parsedown class should be mocked separately if needed\nif (!class_exists('Parsedown')) { class Parsedown { public function text(\$t) { return \$t; } } }\n"
        );
    }
    
    /**
     * Set up temporary directories for testing
     */
    private static function setupTempDirectories(): void
    {
        $varIniDir = sys_get_temp_dir() . '/emhttp';
        
        if (!is_dir($varIniDir)) {
            mkdir($varIniDir, 0755, true);
        }
        
        // Create default var.ini with array started
        file_put_contents("$varIniDir/var.ini", "mdState=STARTED\nfsState=Started\n");
        
        // Map the var.ini path
        UnraidStreamWrapper::addMapping('/var/local/emhttp/var.ini', "$varIniDir/var.ini");
    }
    
    /**
     * Auto-map all PHP files in the source directory
     * 
     * @param string $subPath The subpath within the plugin emhttp directory (e.g., 'php', 'include')
     */
    private static function autoMapSourceFiles(string $subPath = 'php'): void
    {
        if (self::$sourceRoot === null || self::$pluginName === null) {
            return; // Not initialized yet
        }
        
        $baseUnraidPath = "/usr/local/emhttp/plugins/" . self::$pluginName . "/$subPath";
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = substr($file->getPathname(), strlen(self::$sourceRoot));
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                
                $unraidPath = $baseUnraidPath . $relativePath;
                $localPath = $file->getPathname();
                
                UnraidStreamWrapper::addMapping($unraidPath, $localPath);
            }
        }
    }
    
    /**
     * Add a custom path mapping
     * 
     * @param string $unraidPath The Unraid path
     * @param string $localPath The local path
     */
    public static function addMapping(string $unraidPath, string $localPath): void
    {
        UnraidStreamWrapper::addMapping($unraidPath, $localPath);
    }
    
    /**
     * Add mock content for a path
     * 
     * @param string $path The path
     * @param string $content The PHP content
     */
    public static function addMockContent(string $path, string $content): void
    {
        UnraidStreamWrapper::addMockContent($path, $content);
    }
    
    /**
     * Set mock array state (started/stopped)
     * 
     * @param bool $started Whether array is started
     */
    public static function setArrayState(bool $started): void
    {
        $varIniDir = sys_get_temp_dir() . '/emhttp';
        $state = $started ? 'STARTED' : 'STOPPED';
        file_put_contents("$varIniDir/var.ini", "mdState=$state\nfsState=" . ($started ? 'Started' : 'Stopped') . "\n");
    }
    
    /**
     * Get the plugin name
     */
    public static function getPluginName(): ?string
    {
        return self::$pluginName;
    }
    
    /**
     * Get the source root path
     */
    public static function getSourceRoot(): ?string
    {
        return self::$sourceRoot;
    }
    
    /**
     * Reset the bootstrap (for testing the framework itself)
     */
    public static function reset(): void
    {
        UnraidStreamWrapper::reset();
        if (UnraidStreamWrapper::isRegistered()) {
            UnraidStreamWrapper::unregister();
        }
        self::$pluginName = null;
        self::$sourceRoot = null;
        self::$initialized = false;
    }
}
