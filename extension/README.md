# Plugin Tests VS Code Extension

VS Code extension for running BATS and PHPUnit tests with proper multi-root workspace support.

## Features

- **BATS Test Support**: Discover and run BATS (Bash Automated Testing System) tests
- **PHPUnit Support**: Discover and run PHPUnit tests
- **Docker Integration**: Run BATS tests in Docker for cross-platform consistency
- **Multi-Root Workspace Support**: Properly handles VS Code multi-root workspaces (unlike other extensions!)
- **Test Explorer Integration**: Full integration with VS Code's native Test Explorer

## Installation

### From VSIX (Local Installation)

1. Build the extension:
   ```bash
   cd extension
   npm install
   npm run build
   npm run package
   ```

2. Install in VS Code:
   - Open VS Code
   - Press `Ctrl+Shift+P` → "Extensions: Install from VSIX..."
   - Select the generated `.vsix` file

### From Source (Development)

1. Clone and open in VS Code:
   ```bash
   cd extension
   npm install
   ```

2. Press `F5` to launch Extension Development Host

## Configuration

Settings can be configured in `.vscode/settings.json`:

```json
{
    // BATS Settings
    "pluginTests.bats.enabled": true,
    "pluginTests.bats.pattern": "**/*.bats",
    "pluginTests.bats.useDocker": true,
    "pluginTests.bats.dockerImage": "bats/bats:latest",
    
    // PHPUnit Settings
    "pluginTests.phpunit.enabled": true,
    "pluginTests.phpunit.pattern": "**/*Test.php",
    "pluginTests.phpunit.executable": "vendor/bin/phpunit",
    
    // General
    "pluginTests.timeout": 30000
}
```

## Multi-Root Workspace Support

This extension correctly handles multi-root workspaces by:

1. Using **absolute paths** as test item IDs
2. Calculating paths **relative to each workspace folder**, not the workspace root
3. Setting the **correct working directory** for each test run

This fixes the common issue where other test extensions fail with "No such file or directory" errors in multi-root setups.

## Requirements

- **Docker** (recommended): For running BATS tests in a consistent environment
- **PHP**: For running PHPUnit tests
- **PHPUnit**: Must be installed in each project that has PHP tests

## Development

```bash
# Install dependencies
npm install

# Build
npm run build

# Watch mode
npm run watch

# Package as VSIX
npm run package
```
