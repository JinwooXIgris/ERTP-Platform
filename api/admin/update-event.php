<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validate.php';
require_once __DIR__ . '/../../includes/auth.php';

final class AdminUpdateEventEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('POST');
        require_role('admin');

        $data = read_json();

        $title = clean_string($data['title'] ?? null, 1, 150);
        $venue = clean_string($data['venue'] ?? null, 1, 200);
        $eventDate = clean_string($data['event_date'] ?? null, 1, 19);
        $description = clean_string($data['description'] ?? null, 1, 65535);
        $schedule = clean_string($data['schedule'] ?? null, 1, 65535);
        $speakers = clean_string($data['speakers'] ?? null, 1, 65535);

        $errors = [];
        if ($title === null) {
            $errors[] = ['field' => 'title', 'message' => 'Title must be 1-150 characters'];
        }
        if ($venue === null) {
            $errors[] = ['field' => 'venue', 'message' => 'Venue must be 1-200 characters'];
        }
        if ($eventDate === null) {
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

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $eventDate)) {
            fail('Please fix the highlighted fields', 400, [
                ['field' => 'event_date', 'message' => 'Event date must be in format YYYY-MM-DD HH:MM:SS']
            ]);
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE events 
             SET title = ?, event_date = ?, venue = ?, description = ?, schedule = ?, speakers = ?
             WHERE id = 1'
        );
        $updateStmt->execute([$title, $eventDate, $venue, $description, $schedule, $speakers]);

        $selectStmt = $this->pdo->prepare(
            'SELECT id, title, event_date, venue, description, schedule, speakers FROM events WHERE id = 1'
        );
        $selectStmt->execute();
        $event = $selectStmt->fetch();

        if (!$event) {
            fail('Event not found', 404);
        }

        ok('', ['event' => $event], 200);
    }
}

(new AdminUpdateEventEndpoint(db()))->handle();
