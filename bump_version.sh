#!/bin/bash

# Version bump script for CSS and JS files
# Usage: ./bump_version.sh

echo "Bumping version for CSS and JS files..."

# Run the PHP version bump script
php scripts/bump_version.php

echo ""
echo "Version bump complete!"
echo "All CSS and JS files will now use the new version for cache busting."