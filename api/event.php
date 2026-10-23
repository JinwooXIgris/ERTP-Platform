<?php

/**
 * =============================================================================
 * GET /api/event.php
 * 
 * Purpose: Return conference event details
 * 
 * Auth: None (public endpoint)
 * Method: GET only
 * 
 * Response:
 *   HTTP 200 + event object:
 *   {
 *     "success": true,
 *     "message": "",
 *     "data": {
 *       "event": {
 *         "id": 1,
 *         "title": "University of Ibadan Technology Conference 2026",
 *         "event_date": "2026-11-20 09:00:00",
 *         "venue": "Kunle Torimiro Centre, ...",
 *         "description": "...",
 *         "schedule": "...",
 *         "speakers": "..."
 *       }
 *     },
 *     "errors": []
 *   }
 * 
 * Design Notes:
 *   - Public endpoint; no authentication required
 *   - Returns all event fields from the events table
 *   - Only one event per deployment (future: multi-event extension)
 *   - Cached by frontend or served on every page load (fast query)
 * =============================================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

// 1. Check HTTP method (GET only)
require_method('GET');

// 2. Query the database for event (always ID = 1 for this deployment)
try {
    $stmt = db()->prepare('SELECT id, title, event_date, venue, description, schedule, speakers FROM events WHERE id = 1');
    $stmt->execute();
    $event = $stmt->fetch();
} catch (Exception $e) {
    // Log the error internally; don't expose to user
    error_log("Event query failed: " . $e->getMessage());
    fail('Something went wrong', 500);
}

// 3. If event not found (should never happen after seeding)
if (!$event) {
    fail('Event not found', 404);
}

// 4. Return success with event data
ok('', ['event' => $event], 200);

?>
