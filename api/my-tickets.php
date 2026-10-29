<?php

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ticketing.php';

final class MyTicketsEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('GET');
        $user = require_login();

        $reader = new TicketReader($this->pdo);
        $tickets = $reader->getByUser((int)$user['id']);

        ok('Tickets loaded', ['tickets' => $tickets]);
    }
}

(new MyTicketsEndpoint(db()))->handle();
