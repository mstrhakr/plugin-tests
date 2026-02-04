# Plugin Test Framework

A testing framework for plugins built for the Unraid® platform. Provides mocks, helpers, and reusable CI/CD workflows for both PHP and Bash testing.

> **Trademark Notice:** Unraid® is a registered trademark of Lime Technology, Inc. This project is not affiliated with, endorsed by, or sponsored by Lime Technology, Inc.

## Features

- **PHP Mocks** - Emulates platform-specific globals (`$var`, `$disks`, `$shares`) and functions (`parse_plugin_cfg()`, `autov()`)
- **BATS Helpers** - Test helpers for bash scripts with command mocking
- **Docker Environment** - Container-based test environment matching the target platform
- **GitHub Actions** - Reusable workflows for CI/CD integration
- **PHPStan Configuration** - Pre-configured static analysis ignoring platform-specific functions

## Quick Start

### Installation

Add as a dev dependency or submodule:

```bash
# As a Git submodule
git submodule add https://github.com/mstrhakr/plugin-tests.git tests/framework

# Or clone for standalone use
git clone https://github.com/mstrhakr/plugin-tests.git
```

### PHP Testing

```php
<?php
use PluginTests\TestCase;

class MyPluginTest extends TestCase
{
    public function testConfigParsing(): void
    {
        // Mock returns your test config
        $this->mockPluginConfig('myplugin', [
            'setting1' => 'value1',
            'debug' => 'yes'
        ]);
        
        // Now parse_plugin_cfg() returns your mock
        $cfg = parse_plugin_cfg('myplugin');
        $this->assertEquals('value1', $cfg['setting1']);
    }
}
```

### Bash Testing with BATS

```bash
#!/usr/bin/env bats

load '../framework/bats/setup'

@test "script handles missing config gracefully" {
    run ./scripts/myscript.sh --config /nonexistent
    [ "$status" -eq 1 ]
    [[ "$output" == *"Config not found"* ]]
}

@test "docker compose is called correctly" {
    # Mock docker command
    mock_command docker
    
    run ./scripts/compose.sh up mystack
    
    assert_mock_called docker "compose up"
}
```

## Project Structure

```
plugin-tests/
├── src/php/                    # PHP mock library
│   ├── bootstrap.php           # Test bootstrap with function mocks
│   ├── PluginBootstrap.php     # Easy plugin initialization
│   ├── helpers.php             # includeWithSwitch() and helpers
│   ├── TestCase.php            # Base PHPUnit test class
│   ├── StreamWrapper/
│   │   └── UnraidStreamWrapper.php  # Path interception for real files
│   ├── Mocks/
│   │   ├── GlobalsMock.php     # $var, $disks, $shares
│   │   ├── FunctionMocks.php   # parse_plugin_cfg, plugin(), Markdown(), etc.
│   │   └── DockerMock.php      # Docker API mocking
│   └── Fixtures/
│       └── defaults.php        # Default mock values
│
├── bats/                       # BATS test helpers
│   ├── setup.bash              # Test setup/teardown
│   ├── helpers/
│   │   ├── mocks.bash          # Command mocking
│   │   └── assertions.bash     # Custom assertions
│   └── fixtures/               # Test data
│
├── bin/                        # Cross-platform test runners
│   ├── run-bats.cmd            # Windows BATS runner (Docker)
│   ├── run-bats.sh             # Unix BATS runner (Docker)
│   ├── run-tests.cmd           # Windows full test runner
│   └── run-tests.sh            # Unix full test runner
│
├── workflows/                  # Reusable GitHub Actions
│   ├── test-php.yml
│   ├── test-bash.yml
│   └── lint.yml
│
├── examples/                   # Example tests
│   ├── php/
│   └── bash/
│
├── composer.json
├── phpunit.xml
└── phpstan.neon
```

## Usage in Your Plugin

### 1. Add the Framework

```bash
cd your-plugin
git submodule add https://github.com/mstrhakr/plugin-tests.git tests/framework
```

### 2. Create composer.json

```json
{
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^2.1"
    },
    "autoload-dev": {
        "psr-4": {
            "PluginTests\\": "tests/framework/src/php/"
        }
    }
}
```

### 3. Create tests/bootstrap.php (Recommended)

Use `PluginBootstrap` for easy setup with automatic path mapping:

```php
<?php
require_once __DIR__ . '/framework/src/php/bootstrap.php';

use PluginTests\PluginBootstrap;

PluginBootstrap::init(
    'myplugin',                              // Plugin name
    __DIR__ . '/../source/myplugin/php',     // Source PHP directory
    [
        'config' => [                        // Default config values
            'SETTING1' => 'value1',
        ],
    ]
);
```

This automatically:
- Maps all PHP files to their Unraid paths
- Mocks common Unraid system files (Wrappers.php, DockerClient.php, etc.)
- Sets up temp directories for testing
- Enables testing REAL plugin code without modifications

### 4. Create phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/framework/src/php/bootstrap.php">
    <testsuites>
        <testsuite name="Plugin Tests">
            <directory>tests/unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### 4. Configure VS Code (Optional)

Add to `.vscode/settings.json` for IDE test integration:

```json
{
    "phpunit.php": "php",
    "phpunit.phpunit": "vendor/bin/phpunit",
    "bats-test-runner.testPattern": "**/*.bats",
    "bats-test-runner.batsExecutable": "${workspaceFolder}/tests/framework/bin/run-bats.cmd"
}
```

### 5. Running Tests

```powershell
# PHP tests (native)
php vendor/bin/phpunit

# BATS tests (via Docker)
tests/framework/bin/run-bats.cmd tests/unit/*.bats

# All tests (via Docker for consistency)
tests/framework/bin/run-tests.cmd all
```

### 6. Use Reusable Workflows

```yaml
# .github/workflows/test.yml
name: Tests
on: [push, pull_request]

jobs:
  php-tests:
    uses: mstrhakr/plugin-tests/.github/workflows/test-php.yml@main
    
  bash-tests:
    uses: mstrhakr/plugin-tests/.github/workflows/test-bash.yml@main
```

## Mocked Platform Functions

### PHP Functions

| Function | Mock Behavior |
|----------|---------------|
| `parse_plugin_cfg($plugin)` | Returns configured mock array |
| `autov($path)` | Returns path with `?v=test` |
| `csrf_token()` | Returns predictable test token |
| `my_scale_status()` | Configurable scale values |

### PHP Globals

| Global | Default Mock |
|--------|--------------|
| `$var` | Basic system info (name, timezone, etc.) |
| `$disks` | Empty disk array |
| `$shares` | Empty shares array |
| `$dockerClient` | Mock Docker client |

### Bash Mocks

| Command | Mock Behavior |
|---------|---------------|
| `docker` | Configurable responses |
| `logger` | Captures log messages |
| `notify` | Captures notifications |

## Contributing

Contributions welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT License - see [LICENSE](LICENSE)
