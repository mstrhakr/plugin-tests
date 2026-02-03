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
│   ├── TestCase.php            # Base PHPUnit test class
│   ├── Mocks/
│   │   ├── GlobalsMock.php     # $var, $disks, $shares
│   │   ├── FunctionMock.php    # parse_plugin_cfg, autov, etc.
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
├── docker/                     # Test environment
│   ├── Dockerfile
│   └── docker-compose.yml
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

### 3. Create phpunit.xml

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

### 4. Use Reusable Workflows

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
