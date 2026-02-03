#!/usr/bin/env bash
#
# Plugin Tests Framework - BATS Setup
#
# Source this file in your BATS tests to get access to
# mock functions and test helpers.
#

# Get the directory of this script
PLUGIN_TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Load helpers
source "${PLUGIN_TESTS_DIR}/helpers/mocks.bash"
source "${PLUGIN_TESTS_DIR}/helpers/assertions.bash"

# Test fixtures directory
FIXTURES_DIR="${PLUGIN_TESTS_DIR}/fixtures"

# Temp directory for test files (cleaned up automatically)
TEST_TEMP_DIR=""

# ============================================================
# Setup/Teardown Hooks
# ============================================================

# Called before each test
setup() {
    # Create a temp directory for this test
    TEST_TEMP_DIR="$(mktemp -d)"
    
    # Reset all mocks
    reset_all_mocks
    
    # Call user's setup if defined
    if declare -f test_setup > /dev/null; then
        test_setup
    fi
}

# Called after each test
teardown() {
    # Call user's teardown if defined
    if declare -f test_teardown > /dev/null; then
        test_teardown
    fi
    
    # Clean up temp directory
    if [[ -n "$TEST_TEMP_DIR" && -d "$TEST_TEMP_DIR" ]]; then
        rm -rf "$TEST_TEMP_DIR"
    fi
    
    # Reset mocks
    reset_all_mocks
}

# ============================================================
# Helper Functions
# ============================================================

# Create a test compose file
# Usage: create_test_compose_file [path] [content]
create_test_compose_file() {
    local path="${1:-$TEST_TEMP_DIR/docker-compose.yml}"
    local content="${2:-}"
    
    mkdir -p "$(dirname "$path")"
    
    if [[ -z "$content" ]]; then
        content='version: "3"
services:
  test:
    image: alpine:latest
    command: sleep infinity'
    fi
    
    echo "$content" > "$path"
    echo "$path"
}

# Create a test stack directory structure
# Usage: create_test_stack [name]
create_test_stack() {
    local name="${1:-teststack}"
    local stack_dir="$TEST_TEMP_DIR/$name"
    
    mkdir -p "$stack_dir"
    # Suppress output from create_test_compose_file
    create_test_compose_file "$stack_dir/docker-compose.yml" > /dev/null
    
    echo "$stack_dir"
}

# Create a mock config file
# Usage: create_test_config [path] [key=value pairs...]
create_test_config() {
    local path="$1"
    shift
    
    mkdir -p "$(dirname "$path")"
    
    for pair in "$@"; do
        echo "$pair" >> "$path"
    done
    
    echo "$path"
}

# Wait for a condition with timeout
# Usage: wait_for "condition" [timeout_seconds]
wait_for() {
    local condition="$1"
    local timeout="${2:-10}"
    local elapsed=0
    
    while ! eval "$condition"; do
        if [[ $elapsed -ge $timeout ]]; then
            return 1
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done
    
    return 0
}

# Get captured logs
# Usage: get_logs [filter]
get_logs() {
    local filter="${1:-}"
    
    if [[ -n "$filter" ]]; then
        grep "$filter" "$MOCK_LOG_FILE" 2>/dev/null || true
    else
        cat "$MOCK_LOG_FILE" 2>/dev/null || true
    fi
}

# Clear captured logs
clear_logs() {
    > "$MOCK_LOG_FILE"
}
