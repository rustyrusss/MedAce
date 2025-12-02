<?php
/**
 * Environment Configuration Loader
 * This file loads environment variables from the .env file
 */

// Path to .env file (adjust if your structure is different)
$envFile = __DIR__ . '/../.env';

// Check if .env file exists
if (file_exists($envFile)) {
    // Read the .env file
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Skip empty lines
        if (empty(trim($line))) {
            continue;
        }
        
        // Parse the line
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set the environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
} else {
    // Log error but don't expose in production
    error_log("Warning: .env file not found at: " . $envFile);
}

/**
 * Helper function to get environment variables
 * Falls back to getenv() if $_ENV is not set
 */
function env($key, $default = null) {
    // Try $_ENV first
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    // Try $_SERVER
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    // Try getenv()
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    // Return default
    return $default;
}
?>