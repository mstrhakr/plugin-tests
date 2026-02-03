#!/bin/bash
#
# Plugin Tests Framework - Full Test Runner
# Runs both PHP and BATS tests in Docker for consistency
#
# Usage: run-tests.sh [php|bats|all] [additional-args]
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Find workspace root
if [[ -f "$SCRIPT_DIR/../bats/setup.bash" ]]; then
    WORKSPACE_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
else
    WORKSPACE_ROOT="$(pwd)"
fi

TEST_TYPE="${1:-all}"
shift 2>/dev/null || true

run_php() {
    echo "Running PHP tests..."
    docker run --rm \
        -v "$WORKSPACE_ROOT:/code" \
        -w /code \
        php:8.2-cli \
        sh -c "composer install --quiet 2>/dev/null; vendor/bin/phpunit $*"
}

run_bats() {
    echo "Running BATS tests..."
    docker run --rm \
        -v "$WORKSPACE_ROOT:/code" \
        -w /code \
        bats/bats:latest \
        tests/unit/*.bats "$@"
}

case "$TEST_TYPE" in
    php)
        run_php "$@"
        ;;
    bats)
        run_bats "$@"
        ;;
    all)
        echo "Running all tests..."
        echo
        echo "=== PHP Tests ==="
        run_php "$@"
        echo
        echo "=== BATS Tests ==="
        run_bats "$@"
        ;;
    *)
        echo "Usage: run-tests.sh [php|bats|all] [additional-args]"
        exit 1
        ;;
esac
