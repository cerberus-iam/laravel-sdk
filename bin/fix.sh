#!/usr/bin/env bash

# Exit on error and unbound variables
set -eu

# Function to check if a composer package is installed
is_composer_package_installed() {
    composer show "$1" >/dev/null 2>&1
    return $?
}

# Constants
DUSTER_PACKAGE="tightenco/duster"
DUSTER_PATH="vendor/bin/duster"
SRC_DIR="./app"
ADDITIONAL_DIRS=("./tests" "./database" "./resources/views")

# Check if Duster is installed
if ! is_composer_package_installed "$DUSTER_PACKAGE"; then
    echo "Installing $DUSTER_PACKAGE..."
    composer require --dev "$DUSTER_PACKAGE"
fi

# Create a timestamp for logs
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="duster_run_${TIMESTAMP}.log"

# Check if directories exist before proceeding
if [ ! -d "$SRC_DIR" ]; then
    echo "Error: Source directory $SRC_DIR not found!"
    exit 1
fi

# Run the Duster analysis
echo "Running Duster on $SRC_DIR..."
$DUSTER_PATH fix "$SRC_DIR" | tee -a "$LOG_FILE"

# Run on additional directories if they exist
for dir in "${ADDITIONAL_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Running Duster on $dir..."
        $DUSTER_PATH fix "$dir" | tee -a "$LOG_FILE"
    fi
done

echo "Code formatting completed. Log saved to $LOG_FILE"

# Create a summary of changes
echo "Summary of changes:" | tee -a "$LOG_FILE"
grep -E "Linting|Fixed|Failed" "$LOG_FILE" | sort | uniq -c | tee -a "$LOG_FILE"

exit 0
