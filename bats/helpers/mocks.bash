#!/usr/bin/env bash
#
# Plugin Tests Framework - Command Mocks
#
# Provides mock implementations of common commands used in plugins.
#

# ============================================================
# Mock State
# ============================================================

# File to capture mock calls
MOCK_LOG_FILE="${MOCK_LOG_FILE:-/tmp/plugin-tests-mock.log}"

# File to store mock return values
MOCK_RETURNS_FILE="${MOCK_RETURNS_FILE:-/tmp/plugin-tests-returns}"

# Docker mock state
MOCK_DOCKER_RUNNING=true
MOCK_DOCKER_COMPOSE_EXIT=0
MOCK_DOCKER_CONTAINERS='[]'

# ============================================================
# Reset Functions
# ============================================================

reset_all_mocks() {
    MOCK_DOCKER_RUNNING=true
    MOCK_DOCKER_COMPOSE_EXIT=0
    MOCK_DOCKER_CONTAINERS='[]'
    
    : > "$MOCK_LOG_FILE"
    rm -f "$MOCK_RETURNS_FILE".*
}

# ============================================================
# Mock Configuration
# ============================================================

# Set whether Docker daemon is "running"
mock_docker_running() {
    MOCK_DOCKER_RUNNING="${1:-true}"
}

# Set Docker compose exit code
mock_docker_compose_exit() {
    MOCK_DOCKER_COMPOSE_EXIT="${1:-0}"
}

# Set mock container list (JSON)
mock_docker_containers() {
    MOCK_DOCKER_CONTAINERS="$1"
}

# Set return value for a mock command
# Usage: mock_return "command" "value"
mock_return() {
    local cmd="$1"
    local value="$2"
    echo "$value" > "$MOCK_RETURNS_FILE.$cmd"
}

# Get return value for a mock command
mock_get_return() {
    local cmd="$1"
    local default="${2:-}"
    
    if [[ -f "$MOCK_RETURNS_FILE.$cmd" ]]; then
        cat "$MOCK_RETURNS_FILE.$cmd"
    else
        echo "$default"
    fi
}

# ============================================================
# Mock Commands
# ============================================================

# Mock docker command
docker() {
    case "$1" in
        compose)
            shift
            # Don't log here - _mock_docker_compose will log the full command
            _mock_docker_compose "$@"
            ;;
        ps)
            echo "docker $*" >> "$MOCK_LOG_FILE"
            echo "$MOCK_DOCKER_CONTAINERS"
            ;;
        info)
            echo "docker $*" >> "$MOCK_LOG_FILE"
            if [[ "$MOCK_DOCKER_RUNNING" == "true" ]]; then
                echo '{"ServerVersion": "24.0.0"}'
            else
                echo "Cannot connect to the Docker daemon" >&2
                return 1
            fi
            ;;
        inspect)
            echo "docker $*" >> "$MOCK_LOG_FILE"
            # Return mock container info
            echo '[{"State": {"Running": true}}]'
            ;;
        *)
            echo "docker $*" >> "$MOCK_LOG_FILE"
            ;;
    esac
}

# Mock docker compose subcommand
_mock_docker_compose() {
    echo "docker compose $*" >> "$MOCK_LOG_FILE"
    
    case "$1" in
        up)
            if [[ "$MOCK_DOCKER_COMPOSE_EXIT" -eq 0 ]]; then
                echo "Creating network..."
                echo "Creating container..."
                echo "Started"
            else
                echo "Error: failed to start" >&2
            fi
            return $MOCK_DOCKER_COMPOSE_EXIT
            ;;
        down)
            echo "Stopping containers..."
            echo "Removing containers..."
            return $MOCK_DOCKER_COMPOSE_EXIT
            ;;
        pull)
            echo "Pulling images..."
            return $MOCK_DOCKER_COMPOSE_EXIT
            ;;
        ps)
            echo "$MOCK_DOCKER_CONTAINERS"
            ;;
        config)
            echo "services:"
            echo "  test:"
            echo "    image: alpine:latest"
            ;;
        *)
            return $MOCK_DOCKER_COMPOSE_EXIT
            ;;
    esac
}

# Mock logger command
logger() {
    local tag=""
    local priority=""
    local message=""
    
    while [[ $# -gt 0 ]]; do
        case "$1" in
            -t)
                tag="$2"
                shift 2
                ;;
            -p)
                priority="$2"
                shift 2
                ;;
            *)
                message="$message $1"
                shift
                ;;
        esac
    done
    
    echo "[LOG] tag=$tag priority=$priority message=$message" >> "$MOCK_LOG_FILE"
}

# Mock notify command (Unraid notification)
notify() {
    echo "[NOTIFY] $*" >> "$MOCK_LOG_FILE"
}

# Mock sleep (optionally skip for faster tests)
if [[ "${MOCK_SKIP_SLEEP:-false}" == "true" ]]; then
    sleep() {
        echo "[SLEEP] $1" >> "$MOCK_LOG_FILE"
    }
fi

# Export mock functions
export -f docker
export -f logger
export -f notify
export MOCK_LOG_FILE
export MOCK_RETURNS_FILE
