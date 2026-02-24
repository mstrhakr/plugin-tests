# PowerShell script to create a release commit and tag for CI
# This script will:
# 1. Ensure working directory is clean
# 2. Bump version (if needed) or use provided version
# 3. Create a release commit (if needed)
# 4. Create a tag (vX.Y.Z) and push it to origin

param(
    [string]$Version = "",
    [switch]$WhatIf
)

function Fail($msg) {
    Write-Error $msg
    exit 1
}

# Ensure git is available
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Fail "git is not installed or not in PATH."
}

# Ensure working directory is clean
$gitStatus = git status --porcelain
if ($gitStatus) {
    Fail "Working directory is not clean. Commit or stash changes first."
}

# Get current version from latest tag if not provided
if (-not $Version) {
    $lastTag = git describe --tags --abbrev=0 2>$null
    if ($lastTag -match '^v(\d+\.\d+\.\d+)$') {
        $Version = [version]($lastTag.TrimStart('v'))
        $Version = "{0}.{1}.{2}" -f $Version.Major, $Version.Minor, ($Version.Build + 1)
    }
    else {
        $Version = "0.1.0"
    }
}

$tag = "v$Version"

# Make a release commit (optional, e.g. update CHANGELOG or version file)
# Here, just create an empty commit for the tag

$commitMsg = "Release $tag"
if ($WhatIf) {
    Write-Host "Would create commit with message: '$commitMsg'"
    Write-Host "Would create tag: '$tag'"
    Write-Host "Would push commit and tag to origin."
    exit 0
}
else {
    if (-not (git commit --allow-empty -m "$commitMsg")) {
        Fail "Failed to create release commit."
    }

    if (-not (git tag $tag)) {
        Fail "Failed to create tag $tag."
    }

    if (-not (git push origin HEAD)) {
        Fail "Failed to push commit."
    }

    if (-not (git push origin $tag)) {
        Fail "Failed to push tag $tag."
    }
}
Write-Host "Release $tag created and pushed. CI will handle floating tags and release."
