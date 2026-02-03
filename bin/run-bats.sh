#!/bin/bash
#
# Plugin Tests Framework - BATS Runner
# Runs BATS tests in Docker for cross-platform consistency
#
# Usage: run-bats.sh [options] <test-file-or-directory>
#

set -e

# Find the workspace root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if we're in the framework directory or project root
if [[ -f "$SCRIPT_DIR/../bats/setup.bash" ]]; then
    # We're in plugin-tests/bin, workspace is grandparent
    WORKSPACE_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
elif [[ -f "$SCRIPT_DIR/tests/framework/bats/setup.bash" ]]; then
    # We're in project root with framework as submodule
    WORKSPACE_ROOT="$SCRIPT_DIR"
else
    # Assume we're in project root
    WORKSPACE_ROOT="$(pwd)"
fi

# Check if Docker is available
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is required to run BATS tests"
    echo "Install Docker or run tests directly with: bats <test-file>"
    exit 1
fi

# Run BATS in Docker
docker run --rm \
    -v "$WORKSPACE_ROOT:/code" \
    -w /code \
    bats/bats:latest \
    "$@"
