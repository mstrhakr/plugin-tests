#!/usr/bin/env bats
#
# Example BATS Test - Compose Script
#
# This demonstrates how to test bash scripts from the compose plugin
# using the plugin-tests framework.
#

# Load the test framework
load '../../bats/setup'

# ============================================================
# Setup/Teardown
# ============================================================

test_setup() {
    # Additional setup for these tests
    export MOCK_SKIP_SLEEP=true
}

# ============================================================
# Basic Docker Mock Tests
# ============================================================

@test "docker compose up is called correctly" {
    # Run a command that would call docker compose
    run docker compose up -d
    
    # Check it was called
    assert_mock_called "docker" "compose up"
    assert_success
}

@test "docker compose down is called correctly" {
    run docker compose down
    
    assert_mock_called "docker" "compose down"
    assert_success
}

@test "docker compose fails when configured to fail" {
    # Configure mock to fail
    mock_docker_compose_exit 1
    
    run docker compose up -d
    
    assert_failure
    assert_output_contains "Error"
}

# ============================================================
# Docker Daemon State Tests
# ============================================================

@test "docker info succeeds when daemon is running" {
    mock_docker_running true
    
    run docker info
    
    assert_success
    assert_output_contains "ServerVersion"
}

@test "docker info fails when daemon is not running" {
    mock_docker_running false
    
    run docker info
    
    assert_failure
    assert_output_contains "Cannot connect"
}

# ============================================================
# Logging Tests
# ============================================================

@test "logger captures messages" {
    logger -t "compose.manager" "Test message"
    
    assert_logged "Test message"
}

@test "logger captures tag and priority" {
    logger -t "compose.manager" -p local7.error "Error occurred"
    
    # Check the log file directly
    run get_logs "compose.manager"
    assert_output_contains "Error occurred"
}

# ============================================================
# Test Helpers
# ============================================================

@test "create_test_compose_file creates valid file" {
    local compose_file
    compose_file=$(create_test_compose_file)
    
    assert_file_exists "$compose_file"
    assert_file_contains "$compose_file" "services:"
    assert_file_contains "$compose_file" "image: alpine"
}

@test "create_test_stack creates directory structure" {
    local stack_dir
    stack_dir=$(create_test_stack "mystack")
    
    assert_dir_exists "$stack_dir"
    assert_file_exists "$stack_dir/docker-compose.yml"
}

@test "create_test_config creates config file" {
    local config_file
    config_file=$(create_test_config "$TEST_TEMP_DIR/test.cfg" \
        'SETTING1="value1"' \
        'SETTING2="value2"')
    
    assert_file_exists "$config_file"
    assert_file_contains "$config_file" 'SETTING1="value1"'
    assert_file_contains "$config_file" 'SETTING2="value2"'
}

# ============================================================
# Custom Compose File Tests
# ============================================================

@test "custom compose file content is written" {
    local content='version: "3.8"
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"'
    
    local compose_file
    compose_file=$(create_test_compose_file "$TEST_TEMP_DIR/custom.yml" "$content")
    
    assert_file_contains "$compose_file" "nginx:latest"
    assert_file_contains "$compose_file" "80:80"
}

# ============================================================
# Mock Call Counting
# ============================================================

@test "mock tracks multiple calls" {
    docker compose pull
    docker compose up -d
    docker compose ps
    
    assert_mock_called_times "docker compose" 3
}

@test "mock tracks specific commands" {
    docker compose up -d
    docker compose up -d
    docker compose down
    
    assert_mock_called_times "docker compose up" 2
    assert_mock_called_times "docker compose down" 1
}

# ============================================================
# Notification Tests
# ============================================================

@test "notify captures notification" {
    notify "Test Event" "Test Subject" "Test Description"
    
    assert_notified "Test Subject"
}
