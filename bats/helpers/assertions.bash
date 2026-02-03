#!/usr/bin/env bash
#
# Plugin Tests Framework - Custom Assertions
#
# Additional assertion functions for BATS tests.
#

# ============================================================
# Output Assertions
# ============================================================

# Assert output contains a string
# Usage: assert_output_contains "expected"
assert_output_contains() {
    local expected="$1"
    
    if [[ "$output" != *"$expected"* ]]; then
        echo "Expected output to contain: $expected"
        echo "Actual output: $output"
        return 1
    fi
}

# Assert output does not contain a string
# Usage: assert_output_not_contains "unexpected"
assert_output_not_contains() {
    local unexpected="$1"
    
    if [[ "$output" == *"$unexpected"* ]]; then
        echo "Expected output NOT to contain: $unexpected"
        echo "Actual output: $output"
        return 1
    fi
}

# Assert output matches a regex
# Usage: assert_output_matches "pattern"
assert_output_matches() {
    local pattern="$1"
    
    if ! [[ "$output" =~ $pattern ]]; then
        echo "Expected output to match: $pattern"
        echo "Actual output: $output"
        return 1
    fi
}

# Assert output is empty
assert_output_empty() {
    if [[ -n "$output" ]]; then
        echo "Expected empty output"
        echo "Actual output: $output"
        return 1
    fi
}

# ============================================================
# Mock Assertions
# ============================================================

# Assert a mock command was called
# Usage: assert_mock_called "docker" "compose up"
assert_mock_called() {
    local cmd="$1"
    local args="${2:-}"
    
    local search="$cmd"
    [[ -n "$args" ]] && search="$cmd $args"
    
    if ! grep -q "$search" "$MOCK_LOG_FILE" 2>/dev/null; then
        echo "Expected mock to be called: $search"
        echo "Actual calls:"
        cat "$MOCK_LOG_FILE" 2>/dev/null || echo "(none)"
        return 1
    fi
}

# Assert a mock command was NOT called
# Usage: assert_mock_not_called "docker" "rm"
assert_mock_not_called() {
    local cmd="$1"
    local args="${2:-}"
    
    local search="$cmd"
    [[ -n "$args" ]] && search="$cmd $args"
    
    if grep -q "$search" "$MOCK_LOG_FILE" 2>/dev/null; then
        echo "Expected mock NOT to be called: $search"
        echo "Actual calls:"
        cat "$MOCK_LOG_FILE"
        return 1
    fi
}

# Assert mock was called N times
# Usage: assert_mock_called_times "docker compose" 3
assert_mock_called_times() {
    local search="$1"
    local expected="$2"
    
    local actual
    actual=$(grep -c "$search" "$MOCK_LOG_FILE" 2>/dev/null || echo 0)
    
    if [[ "$actual" -ne "$expected" ]]; then
        echo "Expected '$search' to be called $expected times, was called $actual times"
        echo "Calls:"
        cat "$MOCK_LOG_FILE" 2>/dev/null || echo "(none)"
        return 1
    fi
}

# Assert a log message was recorded
# Usage: assert_logged "error message"
assert_logged() {
    local message="$1"
    
    if ! grep -q "\[LOG\].*$message" "$MOCK_LOG_FILE" 2>/dev/null; then
        echo "Expected log message: $message"
        echo "Actual logs:"
        grep "\[LOG\]" "$MOCK_LOG_FILE" 2>/dev/null || echo "(none)"
        return 1
    fi
}

# Assert a notification was sent
# Usage: assert_notified "subject"
assert_notified() {
    local message="$1"
    
    if ! grep -q "\[NOTIFY\].*$message" "$MOCK_LOG_FILE" 2>/dev/null; then
        echo "Expected notification: $message"
        echo "Actual notifications:"
        grep "\[NOTIFY\]" "$MOCK_LOG_FILE" 2>/dev/null || echo "(none)"
        return 1
    fi
}

# ============================================================
# File Assertions
# ============================================================

# Assert file exists
# Usage: assert_file_exists "/path/to/file"
assert_file_exists() {
    local path="$1"
    
    if [[ ! -f "$path" ]]; then
        echo "Expected file to exist: $path"
        return 1
    fi
}

# Assert file does not exist
# Usage: assert_file_not_exists "/path/to/file"
assert_file_not_exists() {
    local path="$1"
    
    if [[ -f "$path" ]]; then
        echo "Expected file NOT to exist: $path"
        return 1
    fi
}

# Assert file contains string
# Usage: assert_file_contains "/path/to/file" "expected content"
assert_file_contains() {
    local path="$1"
    local expected="$2"
    
    if [[ ! -f "$path" ]]; then
        echo "File does not exist: $path"
        return 1
    fi
    
    if ! grep -q "$expected" "$path"; then
        echo "Expected file to contain: $expected"
        echo "File contents:"
        cat "$path"
        return 1
    fi
}

# Assert directory exists
# Usage: assert_dir_exists "/path/to/dir"
assert_dir_exists() {
    local path="$1"
    
    if [[ ! -d "$path" ]]; then
        echo "Expected directory to exist: $path"
        return 1
    fi
}

# ============================================================
# JSON Assertions (requires jq)
# ============================================================

# Assert JSON field equals value
# Usage: assert_json_field "$json" ".field" "expected"
assert_json_field() {
    local json="$1"
    local field="$2"
    local expected="$3"
    
    local actual
    actual=$(echo "$json" | jq -r "$field" 2>/dev/null)
    
    if [[ "$actual" != "$expected" ]]; then
        echo "Expected $field to be: $expected"
        echo "Actual value: $actual"
        return 1
    fi
}

# Assert JSON file field equals value
# Usage: assert_json_file_field "/path/to/file.json" ".field" "expected"
assert_json_file_field() {
    local path="$1"
    local field="$2"
    local expected="$3"
    
    if [[ ! -f "$path" ]]; then
        echo "JSON file does not exist: $path"
        return 1
    fi
    
    local actual
    actual=$(jq -r "$field" "$path" 2>/dev/null)
    
    if [[ "$actual" != "$expected" ]]; then
        echo "Expected $field to be: $expected"
        echo "Actual value: $actual"
        return 1
    fi
}

# ============================================================
# Exit Code Assertions
# ============================================================

# Assert success (exit code 0)
assert_success() {
    # shellcheck disable=SC2154 # status is set by BATS
    if [[ "$status" -ne 0 ]]; then
        echo "Expected success (exit 0), got exit $status"
        echo "Output: $output"
        return 1
    fi
}

# Assert failure (non-zero exit code)
assert_failure() {
    if [[ "$status" -eq 0 ]]; then
        echo "Expected failure (non-zero exit), got exit 0"
        echo "Output: $output"
        return 1
    fi
}

# Assert specific exit code
# Usage: assert_exit_code 1
assert_exit_code() {
    local expected="$1"
    
    if [[ "$status" -ne "$expected" ]]; then
        echo "Expected exit code $expected, got $status"
        echo "Output: $output"
        return 1
    fi
}
