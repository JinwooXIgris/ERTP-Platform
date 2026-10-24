<?php

/**
 * =============================================================================
 * Response Helper — Standard JSON Envelope for All Endpoints
 * 
 * Purpose:
 *   Every endpoint (auth, event, payment, ticket, verify, admin) returns
 *   the same JSON structure. This file provides three functions to do that:
 *   - ok()          : Success response (with data)
 *   - fail()        : Failure response (with errors)
 *   - json_response(): Generic response (used by ok() and fail())
 *   - read_json()   : Parse php://input as JSON
 * 
 * Usage (in any endpoint):
 *   ok('Users created', ['user_id' => 12], 201);
 *   fail('Invalid email', 400, [['field' => 'email', 'message' => 'Enter a valid email']]);
 * 
 * =============================================================================
 */

// =============================================================================
// FUNCTION: json_response()
// 
// Purpose: Core function that outputs the JSON envelope and exits
// 
// Parameters:
//   $success (bool)     : true for success, false for failure
//   $message (string)   : Human-readable message ("User created", "Invalid email", etc.)
//   $data (array)       : Object/array with response data (empty [] on error)
//   $errors (array)     : Array of field-level errors (only on validation failure)
//   $status (int)       : HTTP status code (200, 201, 400, 401, 403, 404, 409, 500)
// 
// Returns: Never returns; exits after sending JSON and setting status
//
function json_response(
    bool $success,
    string $message,
    array $data = [],
    array $errors = [],
    int $status = 200
): never {
    
    // Set HTTP status code
    http_response_code($status);
    
    // Set Content-Type header
    header('Content-Type: application/json; charset=utf-8');
    
    // Build the response envelope
    $response = [
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'errors'  => $errors,
    ];
    
    // Output JSON and exit
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =============================================================================
// FUNCTION: ok()
// 
// Purpose: Send a success response with data
// 
// Parameters:
//   $message (string) : Success message ("User created", "Ticket issued", etc.)
//   $data (array)     : Response data (order, ticket, user, etc.)
//   $status (int)     : HTTP status (200 default, 201 for creation, 202 for async)
// 
// Returns: Never returns; exits after sending JSON
 
// When to use:
//   - Login successful
//   - User created
//   - Order created
//   - Payment confirmed
//   - Ticket issued
//   - Verification succeeded
//
function ok(
    string $message,
    array $data = [],
    int $status = 200
): never {
    json_response(true, $message, $data, [], $status);
}

// =============================================================================
// FUNCTION: fail()
// 
// Purpose: Send a failure response with optional field-level errors
// 
// Parameters:
//   $message (string) : Failure reason ("Invalid email", "Sold out", "Unauthorized", etc.)
//   $status (int)     : HTTP status (400, 401, 403, 404, 409, 500, etc.)
//   $errors (array)   : Field-level errors (for validation failures)
//                       Format: [['field' => 'email', 'message' => 'Invalid format'], ...]
//   $data (array)     : Optional extra data (e.g., for 402 payment failed, include payment receipt)
// 
// Returns: Never returns; exits after sending JSON
// 
// When to use:
//   - Validation failure (400) — field errors included
//   - Not logged in (401)
//   - Wrong role (403)
//   - Resource not found (404)
//   - Duplicate/conflict (409)
//   - Server error (500)
//
function fail(
    string $message,
    int $status,
    array $errors = [],
    array $data = []
): never {
    json_response(false, $message, $data, $errors, $status);
}

// =============================================================================
// FUNCTION: read_json()
// 
// Purpose: Parse the request body as JSON and return as associative array
// 
// Returns: Associative array of the parsed JSON
// 
// Throws: Exits with 400 if JSON is malformed

// Design Notes:
//   - Reads php://input (raw POST body)
//   - Decodes as associative array (not object)
//   - Returns empty array if body is empty (not an error)
//   - Returns 400 if JSON is invalid
//   - Prevents empty string from being treated as empty JSON
//
function read_json(): array {
    
    // Read the raw request body
    $input = file_get_contents('php://input');
    
    // If empty, return empty array (not an error)
    if (empty($input)) {
        return [];
    }
    
    // Decode JSON into associative array
    $data = json_decode($input, associative: true);
    
    // Check for JSON decode errors
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        fail(
            'Invalid JSON in request body',
            400
        );
    }
    
    // Return the decoded data
    return $data ?? [];
}

?>
