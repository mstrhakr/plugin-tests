# Mocking Guide for Unraid Plugin Tests

This guide documents what the plugin-tests framework mocks automatically and what you may need to configure manually.

## Overview

The framework intercepts `file://` protocol calls and redirects Unraid paths to your local source files. This allows you to test real plugin code without modifications.

## Automatic System File Mocks

The `PluginBootstrap::init()` function automatically provides stub content for common Unraid system files:

| Unraid Path | Description | Status |
|-------------|-------------|--------|
| `/usr/local/emhttp/plugins/dynamix/include/Wrappers.php` | File operations (`file_put_contents_atomic`, `my_parse_ini_*`) | ✅ Mocked |
| `/usr/local/emhttp/plugins/dynamix/include/Helpers.php` | Various Dynamix helpers | ✅ Mocked |
| `/usr/local/emhttp/plugins/dynamix/include/Translations.php` | Translation `_()` function | ✅ Mocked |
| `/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php` | Docker client class | ✅ Mocked |
| `/usr/local/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php` | Container creation helpers | ✅ Mocked |
| `/usr/local/emhttp/plugins/dynamix.plugin.manager/include/PluginHelpers.php` | Plugin management (`plugin()` function) | ✅ Mocked |
| `/usr/local/emhttp/webGui/include/Markdown.php` | Markdown parsing (`Parsedown` class) | ✅ Mocked |

## Automatic Function Mocks

These functions are provided automatically by `FunctionMocks.php`:

### Unraid Configuration Functions
| Function | Behavior |
|----------|----------|
| `parse_plugin_cfg($plugin, $default)` | Returns config set via `FunctionMocks::setPluginConfig()` |
| `plugin($action, $path)` | Returns mock version/URL/changes based on action |

### Translation Functions
| Function | Behavior |
|----------|----------|
| `_($text, ...$args)` | Returns text with sprintf formatting |
| `Markdown($text)` / `markdown($text)` | Returns text (uses Parsedown if available) |

### Docker Mock Classes
| Class | Description |
|-------|-------------|
| `DockerClient` | Mock Docker API client with container operations |
| `DockerTemplates` | Mock template management |

### File System Functions
| Function | Behavior |
|----------|----------|
| `readJsonFile($path)` | Reads and decodes JSON files |
| `writeJsonFile($path, $data)` | Writes JSON to a temp location |
| `file_put_contents_atomic($path, $data)` | Writes to temp location with atomic semantics |

### Logging Functions  
| Function | Behavior |
|----------|----------|
| `logger($msg, $type)` | Captures to `FunctionMocks::getLogs()` |
| `syslog($priority, $msg)` | Captures to log array (Windows-safe) |

## Auto-Mapped Plugin Files

When you call `PluginBootstrap::init()`, all `.php` files in your source directory are automatically mapped:

```
Local: source/myplugin/php/MyClass.php
Maps to: /usr/local/emhttp/plugins/myplugin/php/MyClass.php
```

## Adding Custom Mocks

### Custom Path Mappings

```php
use PluginTests\PluginBootstrap;

// Map a specific file
PluginBootstrap::addMapping(
    '/usr/local/emhttp/plugins/otherplugin/include/SomeFile.php',
    __DIR__ . '/fixtures/SomeFile.php'
);
```

### Custom Mock Content

```php
// Add inline mock PHP code
PluginBootstrap::addMockContent(
    '/path/to/mock.php',
    '<?php function myMockFunction() { return "mocked"; }'
);
```

### Custom Plugin Config

```php
use PluginTests\Mocks\FunctionMocks;

FunctionMocks::setPluginConfig('myplugin', [
    'SETTING1' => 'value1',
    'FEATURE_ENABLED' => 'yes',
]);
```

## Testing POST Actions

Many Unraid plugins use `$_POST['action']` switches. Use `includeWithSwitch()`:

```php
// Simulates: $_POST['action'] = 'getConfig'
$result = includeWithSwitch($path, 'getConfig');

// With additional POST data:
$result = includeWithSwitch($path, 'saveConfig', [
    'setting1' => 'value1'
]);
```

## What You May Need to Add

Depending on your plugin, you may need to mock additional system files:

### Not Yet Mocked (Add if needed)
- `/usr/local/emhttp/plugins/dynamix/include/Preselect.php` - CSS/JS preloading
- `/usr/local/emhttp/plugins/dynamix.docker.manager/include/Helpers.php` - Docker-specific helpers
- `/usr/local/emhttp/state/var.ini` - Server state variables
- `/var/run/nginx.pid` - Nginx process ID

### Add Your Own Stubs

```php
// In your test bootstrap.php:
PluginBootstrap::addMockContent(
    '/usr/local/emhttp/plugins/dynamix/include/Preselect.php',
    '<?php // stub'
);
```

## Example Bootstrap Setup

```php
<?php
// tests/bootstrap.php

require_once __DIR__ . '/framework/src/php/bootstrap.php';

use PluginTests\PluginBootstrap;

PluginBootstrap::init(
    'myplugin',                              // Plugin name
    __DIR__ . '/../source/myplugin/php',     // Source directory
    [
        'config' => [                        // Default config values
            'SETTING1' => 'default',
        ],
        'subPath' => 'php',                  // Subpath in emhttp (default: 'php')
        'autoMapFiles' => true,              // Auto-map all .php files (default: true)
    ]
);
```

## Debugging Tips

### Check Mapped Paths
```php
use PluginTests\StreamWrapper\UnraidStreamWrapper;

// Get all current mappings
$mappings = UnraidStreamWrapper::getMappings();
print_r($mappings);
```

### Check Mock Content
```php
$mocks = UnraidStreamWrapper::getMockContent();
print_r($mocks);
```

### Verify File Resolution
The stream wrapper logs path resolutions to help debug issues:
- Local files take precedence over mock content
- Mock content is used only if no local mapping exists
