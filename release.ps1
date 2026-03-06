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
if ($gitStatus -and -not $WhatIf) {
    Fail "Working directory is not clean. Commit or stash changes first."
} elseif ($WhatIf -and $gitStatus) {
    Write-Host "WhatIf: Working directory is not clean. Changes would need to be committed or stashed first."
}

# Get current version from latest tag if not provided
if (-not $Version) {
    $lastTag = git describe --tags --abbrev=0 2>$null
    if ($lastTag -match '^v(\d+\.\d+\.\d+)$') {
        $currentVersion = [version]($lastTag.TrimStart('v'))
        $newVersion = "{0}.{1}.{2}" -f $currentVersion.Major, $currentVersion.Minor, ($currentVersion.Build + 1)
    }
    else {
        Fail "No valid version tag found. Please provide a version using -Version parameter."
    }
} else {
    $newVersion = $Version
}

$tag = "v$newVersion"

# Make a release commit (optional, e.g. update CHANGELOG or version file)
# Here, just create an empty commit for the tag

$commitMsg = "Release $tag"
if ($WhatIf) {
    Write-Host "WhatIf: Last tag: $lastTag"
    Write-Host "WhatIf: New version: $newVersion"
    Write-Host "WhatIf: Would create commit with message: '$commitMsg'"
    Write-Host "WhatIf: Would create tag: '$tag'"
    Write-Host "WhatIf: Would push commit and tag to origin."
    exit 0
}
else {
    git commit --allow-empty -m "$commitMsg"
    if ($LASTEXITCODE -ne 0) {
        Fail "Failed to create release commit."
    }

    git tag $tag
    if ($LASTEXITCODE -ne 0) {
        Fail "Failed to create tag $tag."
    }

    git push origin HEAD
    if ($LASTEXITCODE -ne 0) {
        Fail "Failed to push commit."
    }

    git push origin $tag
    if ($LASTEXITCODE -ne 0) {
        Fail "Failed to push tag $tag."
    }
}
Write-Host "Release $tag created and pushed. CI will handle floating tags and release."
