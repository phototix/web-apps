<?php

declare(strict_types=1);

/**
 * Version bump script for CSS and JS files
 * 
 * Usage: php scripts/bump_version.php
 * 
 * This script generates a new version timestamp and updates
 * the version in app/template.php to bust browser cache.
 */

function bump_version(): void
{
    // Generate new version based on current timestamp (YYYYMMDDHHMMSS format)
    $newVersion = date('YmdHis');
    
    echo "Generating new version: {$newVersion}\n";
    
    // Read the template.php file
    $templateFile = __DIR__ . '/../app/template.php';
    $content = file_get_contents($templateFile);
    
    if ($content === false) {
        echo "Error: Could not read template.php file\n";
        exit(1);
    }
    
    // Find and replace the version line in app_asset_version() function
    $pattern = '/\$version = \'(\d+)\'; \/\/ Default version, updated by bump_version\.php/';
    $replacement = "\$version = '{$newVersion}'; // Default version, updated by bump_version.php";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent === null) {
        echo "Error: Could not find version pattern in template.php\n";
        exit(1);
    }
    
    // Write the updated content back
    if (file_put_contents($templateFile, $newContent) === false) {
        echo "Error: Could not write to template.php file\n";
        exit(1);
    }
    
    echo "Successfully updated version to {$newVersion}\n";
    echo "CSS and JS files will now use ?v={$newVersion} for cache busting\n";
}

// Run the version bump
bump_version();