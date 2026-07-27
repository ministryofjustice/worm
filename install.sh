#!/bin/bash

##################
# Install WORM
#
# Usage:
#   ./install.sh                  # builds the latest git tag
#   ./install.sh 2.1.0            # builds 2.1.0
#   WORM_VERSION=2.1.0 ./install.sh
#   WORM_BIN_DIR=~/bin ./install.sh
##################

set -euo pipefail

# Directory the worm symlink is created in.
WORM_BIN_DIR="${WORM_BIN_DIR:-/usr/local/bin}"

# Fallback used when the source has no git tags to read, e.g. a tarball download.
WORM_FALLBACK_VERSION="2.0.1"

# Resolve the project root from the script location, so the installer works from any cwd.
dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$dir"

# Version stamped into the build. First argument wins, then WORM_VERSION, then the
# latest git tag, so a release is just a tag rather than an edit to this file.
git_version="$(git -C "$dir" tag --points-at HEAD --sort=-v:refname 2>/dev/null | head -n 1 || true)"
if [ -z "$git_version" ]; then
    git_version="$(git -C "$dir" describe --tags --abbrev=0 2>/dev/null || true)"
fi
WORM_VERSION="${1:-${WORM_VERSION:-${git_version:-$WORM_FALLBACK_VERSION}}}"
WORM_VERSION="${WORM_VERSION#v}"

require_command() {
    if ! command -v "$1" &> /dev/null; then
        echo "$2" >&2
        exit 1
    fi
}

require_command php "PHP could not be found on this machine, and is a requirement
    for WORM. Terminating install."

require_command kubectl "kubectl could not be found on this machine, and is a requirement
    for WORM. Terminating install."

require_command cloud-platform "cloud-platform could not be found on this machine, and is a requirement
    for WORM. Run brew install ministryofjustice/cloud-platform-tap/cloud-platform-cli
    Terminating install."

# Build binary of latest worm
php worm app:build --build-version="$WORM_VERSION" --no-interaction

build="$dir/builds/worm"
link="$WORM_BIN_DIR/worm"

if [ ! -x "$build" ]; then
    echo "Expected build at $build, but none was produced. Terminating install." >&2
    exit 1
fi

if [ -e "$link" ] && [ ! -L "$link" ]; then
    echo "$link already exists and is not a symlink. Remove it by hand, then re-run. Terminating install." >&2
    exit 1
fi

# System link to add build to local $PATH
if [ -w "$WORM_BIN_DIR" ]; then
    ln -sfn "$build" "$link"
else
    sudo ln -sfn "$build" "$link"
fi

echo "WORM $WORM_VERSION installed successfully at $link"
