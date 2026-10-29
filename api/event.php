<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

final class EventEndpoint {
    public function __construct(private PDO $pdo) {}

    public function handle(): void {
        require_method('GET');

        try {
            $stmt = $this->pdo->prepare('SELECT id, title, event_date, venue, description, schedule, speakers FROM events WHERE id = 1');
            $stmt->execute();
            $event = $stmt->fetch();
        } catch (Exception $e) {
            error_log('Event query failed: ' . $e->getMessage());
            fail('Something went wrong', 500);
        }

        if (!$event) {
            fail('Event not found', 404);
        }

        ok('', ['event' => $event], 200);
    }
}

(new EventEndpoint(db()))->handle();

