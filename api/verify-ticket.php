<?php

require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ticketing.php';

final class VerifyTicketEndpoint
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(): void
    {
        require_method('POST');

        $officer = require_role('verifier', 'admin');

        $data = read_json();
        $value = clean_string($data['value'] ?? null, 1, 100);

        if ($value === null) {
            fail('Please fix the highlighted fields', 400, [
                ['field' => 'value', 'message' => 'Enter a ticket number or code (1 to 100 characters)']
            ]);
        }

        $verifier = new TicketVerifier($this->pdo);
        $outcome = $verifier->verify($value, (int)$officer['id']);

        $ticketId = $outcome['ticket'] ? (int)$outcome['ticket']['id'] : null;
        $log = new VerificationLog($this->pdo);
        $log->record($ticketId, $value, $outcome['result'], (int)$officer['id']);

        $ticket = null;
        if ($outcome['ticket']) {
            $ticket = [
                'ticket_number' => $outcome['ticket']['ticket_number'],
                'category'      => $outcome['ticket']['category'],
                'holder_name'   => $outcome['ticket']['holder_name'],
                'used_at'       => $outcome['ticket']['used_at']
            ];
        }

        ok($outcome['message'], ['result' => $outcome['result'], 'ticket' => $ticket]);
    }
}

(new VerifyTicketEndpoint(db()))->handle();
