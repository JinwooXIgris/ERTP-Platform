<?php
/**
 * =============================================================================
 * Event Registration & Ticket Payment Platform — Configuration (Example)
 * =============================================================================
 * 
 * INSTRUCTIONS:
 * 
 * 1. Copy this file to includes/config.php:
 *    cp includes/config.example.php includes/config.php
 * 
 * 2. Fill in your local database credentials below
 * 
 * 3. Add includes/config.php to .gitignore (never commit real credentials):
 *    echo "includes/config.php" >> .gitignore
 * 
 * 4. Each team member has their own config.php with their local setup
 * 
 * =============================================================================
 * DATABASE CREDENTIALS (Update for your local environment)
 * =============================================================================
 */

// Database host
define('DB_HOST', 'localhost');

// Database name (must match the schema.sql creation)
define('DB_NAME', 'your_database_name');

// MySQL username
define('DB_USER', 'your_username');

// MySQL password
define('DB_PASS', 'your_password');

/**
 * =============================================================================
 * APPLICATION SETTINGS
 * =============================================================================
 */

// Payment mode: 'simulated' (prototype) or 'real' (production with gateway)
// For the 2-week project, use 'simulated'
// The payment_gateway.php file contains the logic to swap between modes
define('PAYMENT_MODE', 'simulated');

// Ticket prefix for human-readable ticket numbers
// Example: "UIT26-7K4Q9" (UIT26 + random 5 chars)
define('TICKET_PREFIX', 'UIT26');

// Maximum tickets per order
// Prevents someone from buying the entire conference at once
define('MAX_TICKETS_PER_ORDER', 10);

/**
 * =============================================================================
 * SESSION & SECURITY
 * =============================================================================
 */

// Session lifetime (in seconds)
// 24 hours = 86400 seconds
define('SESSION_LIFETIME', 86400);

// Flag for session cookies: HttpOnly
// Prevents JavaScript from accessing the session cookie (protects against XSS)
define('SESSION_HTTPONLY', true);

// Flag for session cookies: SameSite
// Prevents CSRF attacks; 'Lax' allows top-level navigation, 'Strict' blocks all cross-site
define('SESSION_SAMESITE', 'Lax');

/**
 * =============================================================================
 * TIMEZONE & LOCALIZATION
 * =============================================================================
 */

// Default timezone for all timestamps
// Africa/Lagos is UTC+1 with no daylight savings
// IMPORTANT: MySQL must also be configured to this timezone
// In db.php: $pdo->exec("SET time_zone = '+01:00'");
define('TIMEZONE', 'Africa/Lagos');

// Currency symbol
define('CURRENCY_SYMBOL', '₦');

// Currency code
define('CURRENCY_CODE', 'NGN');

/**
 * =============================================================================
 * ENVIRONMENT & LOGGING
 * =============================================================================
 */

// Environment: 'development' or 'production'
// In development: show detailed error messages (for debugging)
// In production: log errors to file, show user-friendly messages
define('ENVIRONMENT', 'development');

// Error logging directory
// Errors are logged here instead of dumped to the user
define('LOG_DIR', __DIR__ . '/../logs/');

// Create log directory if it doesn't exist
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

/**
 * =============================================================================
 * STUBS & TESTING
 * =============================================================================
 */

// Use stub (fake) JSON responses for development
// Set to true in frontend development (endpoints not ready yet)
// Set to false when connecting to real endpoints
define('USE_STUBS', false);

/**
 * =============================================================================
 * VALIDATION RULES
 * =============================================================================
 */

// Minimum and maximum length for various fields
define('MIN_FULL_NAME', 2);
define('MAX_FULL_NAME', 100);

define('MIN_PASSWORD', 8);
define('MAX_PASSWORD', 72);  // Bcrypt limit

define('MIN_PHONE', 10);
define('MAX_PHONE', 15);

/**
 * =============================================================================
 * REMINDER: DO NOT COMMIT CONFIG.PHP
 * =============================================================================
 * 
 * This file (config.php) contains local database credentials and must
 * NEVER be committed to version control.
 * 
 * Add to .gitignore:
 *   echo "includes/config.php" >> .gitignore
 * 
 * If credentials are accidentally committed, regenerate them immediately.
 * 
 * =============================================================================
 */
?>
