VERIFICATION MODULE (ticket verification at the gate)

This module only does verification. It reads the "tickets" table that your partner's
ticketing/payment part creates. It needs these existing tables: tickets, orders, ticket_categories.

SETUP (XAMPP)
1. Make sure the ticketing database exists (ticketing_db from the ticketing module).
2. phpMyAdmin > Import > choose verification_setup.sql > Go.
   (creates gate_officers and verification_log)
3. OPTIONAL, for testing before payment is ready: import sample_tickets.sql.
   It adds TEST-VALID-1, TEST-VALID-2 and TEST-USED-1.
4. Copy the folder verification_module into C:\xampp\htdocs\
5. Open http://localhost/verification_module/
6. Login: gate1 / password

USING A DIFFERENT DATABASE
If your group uses event_ticket_db (main project), change "ticketing_db" to
"event_ticket_db" in classes/Database.php and on the USE line of verification_setup.sql.

DEMO SCRIPT (success and failure)
1. Try to open the page's API without logging in -> "Gate officer login required".
2. Login with a wrong password -> error. Login with gate1 / password -> works.
3. Verify TEST-VALID-1 -> green "Valid ticket".
4. Click Check in guest -> "Ticket checked in".
5. Verify TEST-VALID-1 again -> orange "Ticket already used".
6. Verify TEST-USED-1 -> orange "already used".
7. Verify FAKE-123 -> red "Invalid ticket".
8. Look at the Recent checks table and at the verification_log table in phpMyAdmin.

TEST CASES (for the write-up)
Valid ticket              TEST-VALID-1                 valid, entry allowed
Check in                  TEST-VALID-1 + Check in      checked_in, status becomes used
Reuse                     TEST-VALID-1 again           used, entry refused
Already used ticket       TEST-USED-1                  used, entry refused
Fake ticket               FAKE-123                     invalid
Bad characters            AB#$%                        invalid format
Empty input               (nothing)                    "Enter or paste a ticket number"
Lowercase input           test-valid-2                 valid (turned into capitals)
No login                  call API without login       401, login needed
Wrong password            gate1 / wrong                error message
