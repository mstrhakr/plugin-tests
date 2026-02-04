<?php

/**
 * Plugin Tests Framework - Unraid Stream Wrapper
 * 
 * Intercepts file:// operations to redirect Unraid paths to local test files.
 * This allows testing actual plugin source code that uses hardcoded Unraid paths
 * like /usr/local/emhttp/plugins/...
 * 
 * Usage:
 *   UnraidStreamWrapper::addMapping('/usr/local/emhttp/plugins/myplugin/php/util.php', '/local/path/util.php');
 *   UnraidStreamWrapper::register();
 *   require_once '/usr/local/emhttp/plugins/myplugin/php/util.php'; // Actually loads local file
 */

declare(strict_types=1);

namespace PluginTests\StreamWrapper;

class UnraidStreamWrapper
{
    /** @var resource|null */
    public $context;
    
    /** @var resource|null */
    private $handle = null;
    
    /** @var resource|false */
    private $dirHandle = false;
    
    /** @var array<string, string> Path mappings from Unraid paths to local paths */
    private static array $pathMappings = [];
    
    /** @var array<string, string> Files that should return mock content */
    private static array $mockContent = [];
    
    /** @var bool Whether wrapper is registered */
    private static bool $registered = false;
    
    /** @var int Recursion depth counter to prevent infinite loops */
    private static int $recursionDepth = 0;
    
    /** @var int Maximum allowed recursion depth */
    private const MAX_RECURSION_DEPTH = 10;
    
    /** @var bool Debug mode - logs path resolution */
    private static bool $debug = false;
    
    /**
     * Register the stream wrapper
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        
        // Unregister the built-in file:// wrapper
        stream_wrapper_unregister('file');
        
        // Register our wrapper
        stream_wrapper_register('file', self::class);
        
        self::$registered = true;
    }
    
    /**
     * Unregister the stream wrapper and restore default
     */
    public static function unregister(): void
    {
        if (!self::$registered) {
            return;
        }
        
        stream_wrapper_unregister('file');
        stream_wrapper_restore('file');
        
        self::$registered = false;
    }
    
    /**
     * Check if wrapper is registered
     */
    public static function isRegistered(): bool
    {
        return self::$registered;
    }
    
    /**
     * Enable/disable debug mode
     */
    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }
    
    /**
     * Log debug message if debug mode is enabled
     */
    private static function debugLog(string $message): void
    {
        if (self::$debug) {
            error_log("[UnraidStreamWrapper] $message");
        }
    }
    
    /**
     * Add a path mapping
     * 
     * @param string $unraidPath The Unraid path (e.g., /usr/local/emhttp/plugins/myplugin/php/util.php)
     * @param string $localPath The local path to use instead
     */
    public static function addMapping(string $unraidPath, string $localPath): void
    {
        self::$pathMappings[$unraidPath] = $localPath;
    }
    
    /**
     * Add multiple path mappings at once
     * 
     * @param array<string, string> $mappings Array of unraidPath => localPath
     */
    public static function addMappings(array $mappings): void
    {
        foreach ($mappings as $unraidPath => $localPath) {
            self::$pathMappings[$unraidPath] = $localPath;
        }
    }
    
    /**
     * Add mock content for a file (creates temp file with content)
     * 
     * @param string $path The path that should return this content
     * @param string $content The PHP content to return
     */
    public static function addMockContent(string $path, string $content): void
    {
        self::$mockContent[$path] = $content;
    }
    
    /**
     * Clear all mappings and mock content
     */
    public static function reset(): void
    {
        self::$pathMappings = [];
        self::$mockContent = [];
    }
    
    /**
     * Get all current mappings (for debugging)
     * 
     * @return array<string, string>
     */
    public static function getMappings(): array
    {
        return self::$pathMappings;
    }
    
    /**
     * Strip the file:// prefix from a path
     */
    private static function stripFilePrefix(string $path): string
    {
        return (string) preg_replace('#^file://#', '', $path);
    }
    
    /**
     * Resolve a path through our mappings
     * Only redirects Unraid paths, passes through everything else
     */
    private function resolvePath(string $path): string
    {
        // Recursion protection
        self::$recursionDepth++;
        if (self::$recursionDepth > self::MAX_RECURSION_DEPTH) {
            self::$recursionDepth--;
            self::debugLog("MAX RECURSION exceeded for: $path");
            return $path; // Fail fast - return original path
        }
        
        try {
            // Normalize path separators for comparison
            $normalizedPath = str_replace('\\', '/', $path);
            
            self::debugLog("Resolving: $normalizedPath");
            
            // Check for exact mapping
            if (isset(self::$pathMappings[$normalizedPath])) {
                self::debugLog("  -> Mapped to: " . self::$pathMappings[$normalizedPath]);
                return self::$pathMappings[$normalizedPath];
            }
            
            // Check for mock content
            if (isset(self::$mockContent[$normalizedPath])) {
                $tempFile = sys_get_temp_dir() . '/unraid_mock_' . md5($normalizedPath) . '.php';
                self::debugLog("  -> Mock content, temp file: $tempFile");
                
                // Temporarily unregister to write file
                self::unregister();
                file_put_contents($tempFile, self::$mockContent[$normalizedPath]);
                self::register();
                return $tempFile;
            }
            
            // Pass through unchanged
            self::debugLog("  -> Passthrough (no mapping)");
            return $path;
        } finally {
            self::$recursionDepth--;
        }
    }
    
    /**
     * Open a file
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        // Strip file:// prefix if present
        $path = self::stripFilePrefix($path);
        
        self::debugLog("stream_open: $path (mode: $mode)");
        
        $resolvedPath = $this->resolvePath($path);
        
        // Temporarily unregister to use real file operations
        self::unregister();
        
        try {
            $handle = @fopen($resolvedPath, $mode);
            
            if ($handle !== false) {
                $this->handle = $handle;
                $opened_path = $resolvedPath;
                self::debugLog("  -> Opened successfully: $resolvedPath");
                return true;
            }
            
            self::debugLog("  -> Failed to open: $resolvedPath");
            return false;
        } finally {
            // Always re-register
            self::register();
        }
    }
    
    /**
     * Read from file
     * @param int<1, max> $count
     */
    public function stream_read(int $count): string|false
    {
        if ($this->handle === null) {
            return false;
        }
        return fread($this->handle, $count);
    }
    
    /**
     * Write to file
     */
    public function stream_write(string $data): int
    {
        if ($this->handle === null) {
            return 0;
        }
        $result = fwrite($this->handle, $data);
        return $result === false ? 0 : $result;
    }
    
    /**
     * Lock/unlock file
     * @param int<0, 7> $operation
     */
    public function stream_lock(int $operation): bool
    {
        if ($this->handle === null) {
            return false;
        }
        // Handle the case where $operation is 0 (which is invalid for flock)
        if ($operation === 0) {
            return true;
        }
        return flock($this->handle, $operation);
    }
    
    /**
     * Check for end of file
     */
    public function stream_eof(): bool
    {
        if ($this->handle === null) {
            return true;
        }
        return feof($this->handle);
    }
    
    /**
     * Close the file
     */
    public function stream_close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
    
    /**
     * Get file stats
     */
    public function stream_stat(): array|false
    {
        if ($this->handle === null) {
            return false;
        }
        return fstat($this->handle);
    }
    
    /**
     * Seek in file
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->handle === null) {
            return false;
        }
        return fseek($this->handle, $offset, $whence) === 0;
    }
    
    /**
     * Get current position
     */
    public function stream_tell(): int|false
    {
        if ($this->handle === null) {
            return false;
        }
        return ftell($this->handle);
    }
    
    /**
     * Flush output
     */
    public function stream_flush(): bool
    {
        if ($this->handle === null) {
            return false;
        }
        return fflush($this->handle);
    }
    
    /**
     * URL stat (for file_exists, is_file, etc.)
     */
    public function url_stat(string $path, int $flags): array|false
    {
        // Strip file:// prefix if present
        $path = self::stripFilePrefix($path);
        
        self::debugLog("url_stat: $path (flags: $flags)");
        
        $resolvedPath = $this->resolvePath($path);
        
        // Temporarily unregister
        self::unregister();
        
        try {
            if ($flags & STREAM_URL_STAT_QUIET) {
                $stat = @stat($resolvedPath);
            } else {
                $stat = stat($resolvedPath);
            }
            
            self::debugLog("  -> stat result: " . ($stat !== false ? 'found' : 'not found'));
            return $stat !== false ? $stat : false;
        } finally {
            // Always re-register
            self::register();
        }
    }
    
    /**
     * Unlink (delete) a file
     */
    public function unlink(string $path): bool
    {
        $path = self::stripFilePrefix($path);
        $resolvedPath = $this->resolvePath($path);
        
        self::unregister();
        try {
            return @unlink($resolvedPath);
        } finally {
            self::register();
        }
    }
    
    /**
     * Rename a file
     */
    public function rename(string $from, string $to): bool
    {
        $from = self::stripFilePrefix($from);
        $to = self::stripFilePrefix($to);
        
        $resolvedFrom = $this->resolvePath($from);
        $resolvedTo = $this->resolvePath($to);
        
        self::unregister();
        try {
            return @rename($resolvedFrom, $resolvedTo);
        } finally {
            self::register();
        }
    }
    
    /**
     * Create a directory
     */
    public function mkdir(string $path, int $mode, int $options): bool
    {
        $path = self::stripFilePrefix($path);
        $resolvedPath = $this->resolvePath($path);
        
        self::unregister();
        try {
            return @mkdir($resolvedPath, $mode, (bool)($options & STREAM_MKDIR_RECURSIVE));
        } finally {
            self::register();
        }
    }
    
    /**
     * Remove a directory
     */
    public function rmdir(string $path, int $options): bool
    {
        $path = self::stripFilePrefix($path);
        $resolvedPath = $this->resolvePath($path);
        
        self::unregister();
        try {
            return @rmdir($resolvedPath);
        } finally {
            self::register();
        }
    }
    
    /**
     * Open a directory
     */
    public function dir_opendir(string $path, int $options): bool
    {
        $path = self::stripFilePrefix($path);
        $resolvedPath = $this->resolvePath($path);
        
        self::unregister();
        try {
            $this->dirHandle = @opendir($resolvedPath);
            return $this->dirHandle !== false;
        } finally {
            self::register();
        }
    }
    
    /**
     * Read directory entry
     */
    public function dir_readdir(): string|false
    {
        if ($this->dirHandle === false) {
            return false;
        }
        return readdir($this->dirHandle);
    }
    
    /**
     * Rewind directory
     */
    public function dir_rewinddir(): bool
    {
        if ($this->dirHandle === false) {
            return false;
        }
        rewinddir($this->dirHandle);
        return true;
    }
    
    /**
     * Close directory
     */
    public function dir_closedir(): bool
    {
        if ($this->dirHandle === false) {
            return false;
        }
        closedir($this->dirHandle);
        $this->dirHandle = false;
        return true;
    }
    
    /**
     * Set metadata (touch, chmod, chown, chgrp)
     */
    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        $path = self::stripFilePrefix($path);
        $resolvedPath = $this->resolvePath($path);
        
        self::unregister();
        
        try {
            return match ($option) {
                STREAM_META_TOUCH => empty($value) ? @touch($resolvedPath) : @touch($resolvedPath, $value[0], $value[1] ?? $value[0]),
                STREAM_META_OWNER_NAME, STREAM_META_OWNER => @chown($resolvedPath, $value),
                STREAM_META_GROUP_NAME, STREAM_META_GROUP => @chgrp($resolvedPath, $value),
                STREAM_META_ACCESS => @chmod($resolvedPath, $value),
                default => false,
            };
        } finally {
            self::register();
        }
    }
    
    /**
     * Truncate stream
     * @param int<0, max> $new_size
     */
    public function stream_truncate(int $new_size): bool
    {
        if ($this->handle === null) {
            return false;
        }
        return ftruncate($this->handle, $new_size);
    }
    
    /**
     * Set stream options
     */
    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false; // Not implemented
    }
}
