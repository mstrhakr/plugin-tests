# Contributing to Plugin Tests Framework

Thank you for your interest in contributing!

## How to Contribute

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** following our coding standards
3. **Add tests** for any new functionality
4. **Run the test suite** to ensure nothing is broken
5. **Submit a pull request**

## Development Setup

```bash
# Clone the repo
git clone https://github.com/mstrhakr/plugin-tests.git
cd plugin-tests

# Install PHP dependencies
composer install

# Run PHP tests
composer test

# Run BATS tests (requires BATS installed)
bats examples/bash/

# Run static analysis
composer analyse

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## Coding Standards

### PHP
- Follow PSR-12 coding style
- Use strict types (`declare(strict_types=1);`)
- Document all public methods with PHPDoc
- Run PHP-CS-Fixer before committing

### Bash
- Follow [Google's Shell Style Guide](https://google.github.io/styleguide/shellguide.html)
- Use `shellcheck` to lint scripts
- Quote all variables unless intentionally word-splitting

## Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add new mock for Docker volumes
fix: correct return type in GlobalsMock
docs: update README with examples
test: add tests for notification capture
```

## Adding New Mocks

### PHP Mocks

1. Add the function to `src/php/Mocks/FunctionMocks.php`
2. Add state management to the `FunctionMocks` class if needed
3. Add helper method to `TestCase.php` for easy access
4. Add tests to `tests/php/FrameworkTest.php`
5. Document in README

### Bash Mocks

1. Add the mock function to `bats/helpers/mocks.bash`
2. Export the function
3. Add assertions to `bats/helpers/assertions.bash` if needed
4. Add example tests to `examples/bash/`
5. Document in README

## Questions?

Open an issue or start a discussion on GitHub.
