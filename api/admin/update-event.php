<?php

/**
 * =============================================================================
 * POST /api/admin/update-event.php
 * 
 * Purpose: Update all event details (title, date, venue, description, schedule, speakers)
 * 
 * Auth: Admin only (require_role enforces this)
 * Method: POST only
 * 
 * Request body (ALL 6 FIELDS REQUIRED):
 *   {
 *     "title": "University of Ibadan Technology Conference 2026",
 *     "event_date": "2026-11-20 09:00:00",
 *     "venue": "Kunle Torimiro Centre, University of Ibadan",
 *     "description": "A premier technology conference bringing together...",
 *     "schedule": "09:00 Registration | 10:00 Keynote | ...",
 *     "speakers": "Dr. Adebayo Okafor | Chinedu Okoro | ..."
 *   }
 * 
 * Response (HTTP 200):
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "event": {
 *         "id": 1,
 *         "title": "...",
 *         "event_date": "2026-11-20 09:00:00",
 *         "venue": "...",
 *         "description": "...",
 *         "schedule": "...",
 *         "speakers": "..."
 *       }
 *     },
 *     "errors": []
 *   }
 * 
 * Error cases:
 *   HTTP 400: Invalid field, missing field, or out of range
 *   HTTP 401: Not logged in
 *   HTTP 403: Not admin
 *   HTTP 500: Database error
 * 
 * Design Notes:
 *   - Protected: admin only
 *   - All 6 fields are required and must be non-empty
 *   - title: 1-150 chars
 *   - venue: 1-200 chars
 *   - description, schedule, speakers: TEXT (any length)
 *   - event_date: DATETIME format "YYYY-MM-DD HH:MM:SS"
 *   - Only one event (ID = 1) for this deployment
 *   - Updates all fields atomically (no partial updates)
 * =============================================================================
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

// 1. Check HTTP method (POST only)
require_method('POST');

// 2. Check auth: admin only
require_role('admin');

// 3. Read and validate input
$data = read_json();

$title = clean_string($data['title'] ?? null, 1, 150);
$venue = clean_string($data['venue'] ?? null, 1, 200);
$event_date = clean_string($data['event_date'] ?? null, 1, 19); // "2026-11-20 09:00:00"
$description = clean_string($data['description'] ?? null, 1, 65535);
$schedule = clean_string($data['schedule'] ?? null, 1, 65535);
$speakers = clean_string($data['speakers'] ?? null, 1, 65535);

// Build error list
$errors = [];
if ($title === null) {
    $errors[] = ['field' => 'title', 'message' => 'Title must be 1-150 characters'];
}
if ($venue === null) {
    $errors[] = ['field' => 'venue', 'message' => 'Venue must be 1-200 characters'];
}
if ($event_date === null) {
    $errors[] = ['field' => 'event_date', 'message' => 'Event date must be 1-19 characters in format YYYY-MM-DD HH:MM:SS'];
}
if ($description === null) {
    $errors[] = ['field' => 'description', 'message' => 'Description must be non-empty'];
}
if ($schedule === null) {
    $errors[] = ['field' => 'schedule', 'message' => 'Schedule must be non-empty'];
}
if ($speakers === null) {
    $errors[] = ['field' => 'speakers', 'message' => 'Speakers must be non-empty'];
}

if (!empty($errors)) {
    fail('Please fix the highlighted fields', 400, $errors);
}

// Additional validation: event_date should be a valid datetime
// Simple check to ensure it's in the right format (YYYY-MM-DD HH:MM:SS)
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $event_date)) {
    fail('Please fix the highlighted fields', 400, [
        ['field' => 'event_date', 'message' => 'Event date must be in format YYYY-MM-DD HH:MM:SS']
    ]);
}

try {
    // 4. Update the event (ID = 1, always)
    $updateStmt = db()->prepare(
        'UPDATE events 
         SET title = ?, event_date = ?, venue = ?, description = ?, schedule = ?, speakers = ?
         WHERE id = 1'
    );
    $updateStmt->execute([$title, $event_date, $venue, $description, $schedule, $speakers]);

    // 5. Retrieve the updated event
    $selectStmt = db()->prepare('SELECT id, title, event_date, venue, description, schedule, speakers FROM events WHERE id = 1');
    $selectStmt->execute();
    $event = $selectStmt->fetch();

    if (!$event) {
        // Should never happen (event should exist)
        fail('Event not found', 404);
    }

} catch (Exception $e) {
    error_log("Admin update-event failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

// 6. Return success
ok('', ['event' => $event], 200);

?>
