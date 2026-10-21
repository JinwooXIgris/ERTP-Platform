<?php

/**
 * =============================================================================
 * Validation Helper — Server-Side Input Validation for All Endpoints
 
 * Purpose:
 *   Every endpoint validates input before trusting it. This file provides
 *   five functions to validate:
 *   - require_method()  : Check HTTP method (POST, GET, etc.)
 *   - clean_string()    : Validate and trim strings (2–100 chars, etc.)
 *   - valid_email()     : Validate email format
 *   - valid_int()       : Validate and cast integers
 *   - valid_money()     : Validate money amounts (DECIMAL 10,2)
 * 
 * Design:
 *   Each function returns the cleaned/validated value on success,
 *   or NULL on failure (never throws).
 *   The endpoint decides what to do with NULL (usually call fail()).
 * 
 * Usage (in any endpoint):
 *   require_method('POST');
 *   $data = read_json();
 *   $email = valid_email($data['email'] ?? null);
 *   if (!$email) {
 *       fail('Invalid email', 400, [['field' => 'email', 'message' => 'Enter a valid email']]);
 *   }
 * 
 * =============================================================================
 */

// =============================================================================
// FUNCTION: require_method()
// 
// Purpose: Verify the HTTP request method matches what the endpoint expects
// 
// Parameters:
//   $method (string) : Expected method in uppercase (GET, POST, PUT, DELETE, etc.)
// 
// Returns: Void (no return value)
// 
// Throws: Exits with 405 Method Not Allowed if method doesn't match

// Design Notes:
//   - Always called first in an endpoint (before reading input)
//   - Prevents POST data from being sent to a GET endpoint (and vice versa)
//   - 405 Method Not Allowed is the correct HTTP status for this
//
function require_method(string $method): void {
    
    // Get the request method in uppercase
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    
    // Compare (exact match required)
    if ($requestMethod !== strtoupper($method)) {
        fail(
            "Method not allowed. Use $method.",
            405
        );
    }
}

// =============================================================================
// FUNCTION: clean_string()
// 
// Purpose: Validate and clean a string (trim, check length)
// 
// Parameters:
//   $v (mixed)   : The value to validate (any type)
//   $min (int)   : Minimum length (default 0)
//   $max (int)   : Maximum length (default 100)
// 
// Returns: Cleaned string on success (trimmed, or null)
//          NULL on failure (not a string, too short, too long, etc.)
// 
// Design Notes:
//   - Trims whitespace (leading and trailing)
//   - Returns NULL if input is not a string
//   - Returns NULL if trimmed length < min or > max
//   - Prevents empty strings when min > 0
//   - Used for names, emails (before more specific validation), titles, etc.
//
function clean_string(mixed $v, int $min, int $max): ?string {
    
    // If not a string, return null
    if (!is_string($v)) {
        return null;
    }
    
    // Trim whitespace
    $v = trim($v);
    
    // Check length
    $len = strlen($v);
    if ($len < $min || $len > $max) {
        return null;
    }
    
    // Return cleaned value
    return $v;
}

// =============================================================================
// FUNCTION: valid_email()
// 
// Purpose: Validate email address format
// 
// Parameters:
//   $v (mixed) : The value to validate (any type)
// 
// Returns: Lowercase email string on success
//          NULL on failure (not a valid email)
// 
// Design Notes:
//   - Uses FILTER_VALIDATE_EMAIL (standard, reliable)
//   - Converts to lowercase before storage (database stores email lowercase)
//   - Rejects obviously bad formats
//   - Not 100% RFC-compliant (some edge cases), but good enough for user registration
//   - Doesn't verify the email exists (no bounce check)
//
function valid_email(mixed $v): ?string {
    
    // If not a string, return null
    if (!is_string($v)) {
        return null;
    }
    
    // Trim whitespace
    $v = trim($v);
    
    // Validate using FILTER_VALIDATE_EMAIL
    $validated = filter_var($v, FILTER_VALIDATE_EMAIL);
    
    // If validation failed, return null
    if ($validated === false) {
        return null;
    }
    
    // Return lowercase (for consistent database storage)
    return strtolower($validated);
}

// =============================================================================
// FUNCTION: valid_int()
// 
// Purpose: Validate and cast an integer within a range
// 
// Parameters:
//   $v (mixed) : The value to validate (any type)
//   $min (int) : Minimum allowed value (inclusive)
//   $max (int) : Maximum allowed value (inclusive)
// 
// Returns: Integer on success (within range)
//          NULL on failure (not an int, out of range, etc.)
// 
// Design Notes:
//   - Accepts integers and numeric strings ("5", 5, etc.)
//   - Rejects floats ("5.5", 5.5) — returns null
//   - Rejects non-numeric strings ("abc") — returns null
//   - Rejects zero if min > 0
//   - Used for IDs, quantities, pagination, category IDs
//
function valid_int(mixed $v, int $min, int $max): ?int {
    
    // If it's a string, check if it's numeric
    if (is_string($v)) {
        $v = trim($v);
        if (!is_numeric($v) || strpos($v, '.') !== false) {
            // Non-numeric or contains a decimal point
            return null;
        }
        $v = (int)$v;
    } elseif (is_int($v)) {
        // Already an integer
    } elseif (is_float($v)) {
        // Floats are not accepted (e.g., 5.5)
        return null;
    } else {
        // Any other type
        return null;
    }
    
    // Check range
    if ($v < $min || $v > $max) {
        return null;
    }
    
    // Return as integer
    return $v;
}

// =============================================================================
// FUNCTION: valid_money()
// 
// Purpose: Validate and format a money amount (DECIMAL 10,2)
// 
// Parameters:
//   $v (mixed)     : The value to validate (any type; string or number)
//   $min (string)  : Minimum allowed amount (as string, e.g., "0.00")
//   $max (string)  : Maximum allowed amount (as string, e.g., "1000000.00")
// 
// Returns: Formatted money string on success (e.g., "2000.00")
//          NULL on failure (not a valid amount, out of range, etc.)
// 
// Design Notes:
//   - Accepts strings ("2000.50") and floats/ints (2000.50, 2000)
//   - Formats to exactly 2 decimal places ("2000" → "2000.00")
//   - Compares as floats (handles 2000.005 edge cases)
//   - Used for prices, amounts, totals in the database (DECIMAL 10,2)
//   - JSON responses send money as strings to avoid float precision loss
//
function valid_money(mixed $v, string $min, string $max): ?string {
    
    // Convert to float
    if (is_string($v)) {
        $v = trim($v);
        if (!is_numeric($v)) {
            return null;
        }
        $v = (float)$v;
    } elseif (is_int($v) || is_float($v)) {
        $v = (float)$v;
    } else {
        return null;
    }
    
    // Convert min/max to floats for comparison
    $minFloat = (float)$min;
    $maxFloat = (float)$max;
    
    // Check range
    if ($v < $minFloat || $v > $maxFloat) {
        return null;
    }
    
    // Format to exactly 2 decimal places
    return number_format($v, 2, '.', '');
}

?>
