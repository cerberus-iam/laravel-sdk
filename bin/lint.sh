#!/usr/bin/env bash

# Exit on error
set -e

# Exit when undeclared variables are used
set -u

# Make pipe commands return the exit status of the last command that fails
set -o pipefail

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Change to project root
cd "$PROJECT_ROOT"

# Default configuration
DUSTER_PACKAGE="tightenco/duster"
DUSTER_PATH="vendor/bin/duster"
DIRECTORIES_TO_ANALYSE="app"
FIX_MODE=0
AUTO_INSTALL=1
LINT_ONLY=0
EXTRA_DIRS=""
VERBOSE=0

# Function to show usage
show_usage() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -d, --directories DIRS  Specify directories to analyze (comma-separated)"
    echo "  -f, --fix               Run in fix mode instead of just linting"
    echo "  -l, --lint-only         Only run PHP syntax check, skip Duster"
    echo "  -n, --no-install        Don't automatically install Duster if missing"
    echo "  -v, --verbose           Show more detailed output"
    echo "  -h, --help              Display this help message"
    echo ""
    echo "Example:"
    echo "  $0 --directories app,tests --fix"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
    -d | --directories)
        DIRECTORIES_TO_ANALYSE="$2"
        shift 2
        ;;
    --directories=*)
        DIRECTORIES_TO_ANALYSE="${1#*=}"
        shift
        ;;
    -f | --fix)
        FIX_MODE=1
        shift
        ;;
    -l | --lint-only)
        LINT_ONLY=1
        shift
        ;;
    -n | --no-install)
        AUTO_INSTALL=0
        shift
        ;;
    -v | --verbose)
        VERBOSE=1
        shift
        ;;
    -h | --help)
        show_usage
        exit 0
        ;;
    *)
        # Assume any additional parameters are extra directories
        if [[ -d "$1" ]]; then
            EXTRA_DIRS="$EXTRA_DIRS $1"
        else
            echo "Error: Unknown option or directory not found: $1"
            show_usage
            exit 1
        fi
        shift
        ;;
    esac
done

# Add any extra directories
if [[ -n "$EXTRA_DIRS" ]]; then
    DIRECTORIES_TO_ANALYSE="$DIRECTORIES_TO_ANALYSE $EXTRA_DIRS"
fi

# Convert comma-separated directories to space-separated
DIRECTORIES_TO_ANALYSE="${DIRECTORIES_TO_ANALYSE//,/ }"

# Function to check if a composer package is installed
is_composer_package_installed() {
    if [[ $VERBOSE -eq 1 ]]; then
        echo "Checking if package $1 is installed..."
    fi
    composer show "$1" >/dev/null 2>&1
}

# Function to validate PHP syntax in a directory
validate_php_syntax() {
    local directory="$1"
    local status=0
    local file_count=0
    local error_count=0

    echo "Checking PHP syntax in $directory..."

    while IFS= read -r -d $'\0' file; do
        file_count=$((file_count + 1))

        if [[ $VERBOSE -eq 1 ]]; then
            echo "Checking syntax of $file"
        fi

        # Check PHP syntax
        if ! php -l "$file" >/dev/null 2>&1; then
            error_count=$((error_count + 1))
            status=1
            # Always show errors regardless of verbose mode
            php -l "$file"
        fi
    done < <(find "$directory" -type f -name "*.php" -print0)

    echo "✓ Checked $file_count PHP files in $directory with $error_count errors"
    return $status
}

# Main script logic
if [[ $LINT_ONLY -eq 0 ]]; then
    # Check if Duster is installed
    if ! is_composer_package_installed $DUSTER_PACKAGE; then
        if [[ $AUTO_INSTALL -eq 1 ]]; then
            echo "Installing $DUSTER_PACKAGE..."
            composer require --dev $DUSTER_PACKAGE
        else
            echo "Error: $DUSTER_PACKAGE is not installed. Run with --no-install=false to install it automatically."
            exit 1
        fi
    fi

    # Verify Duster binary exists
    if [[ ! -f "$DUSTER_PATH" ]]; then
        echo "Error: Duster binary not found at $DUSTER_PATH"
        exit 1
    fi

    # Run Duster
    if [[ $FIX_MODE -eq 1 ]]; then
        echo "Running Duster in FIX mode on: $DIRECTORIES_TO_ANALYSE"
        $DUSTER_PATH fix $DIRECTORIES_TO_ANALYSE
    else
        echo "Running Duster LINT analysis on: $DIRECTORIES_TO_ANALYSE"
        $DUSTER_PATH lint $DIRECTORIES_TO_ANALYSE
    fi
fi

# Run PHP syntax validation on each directory separately
EXIT_STATUS=0
for dir in $DIRECTORIES_TO_ANALYSE; do
    if [[ -d "$dir" ]]; then
        if ! validate_php_syntax "$dir"; then
            EXIT_STATUS=1
        fi
    else
        echo "Warning: Directory '$dir' not found, skipping syntax check"
    fi
done

# Final output
if [[ $EXIT_STATUS -eq 0 ]]; then
    echo "✅ All checks completed successfully!"
else
    echo "❌ Checks completed with errors."
fi

exit $EXIT_STATUS
