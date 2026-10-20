VERIFICATION MODULE (gate ticket verification) - works with the group database "uiconf"

It reads the group's tables: tickets, orders, users, ticket_categories.
It writes to: tickets (marks a ticket used) and verification_log.
There are NO extra tables to create.

SETUP (XAMPP)
1. In phpMyAdmin, import schema.sql and then seed.sql (the group's files, in the repository).
2. OPTIONAL, to test before the payment part is ready: import verification_test_data.sql ONCE.
   It adds the tickets UIT26-TEST1, UIT26-TEST2 and UIT26-USED1.
3. Put the folder verification_module in C:\xampppsss\htdocs\ERTP-Platform\
4. Open http://localhost/ERTP-Platform/verification_module/
5. Login: verifier@uiconf.test / password
   (or any user in the users table whose role is verifier or admin)

HOW IT WORKS
- One scan checks the ticket AND marks it as used, in one atomic UPDATE (as the schema describes).
- You can type the ticket number (UIT26-7K4Q9) or the long verification code.
- Results: valid (admitted), used (already scanned), invalid (not found).
- Every scan is saved in verification_log.

DEMO SCRIPT (success and failure)
1. Login with a wrong password -> error. Login with verifier@uiconf.test / password -> works.
2. Verify UIT26-TEST1 -> green "Valid ticket. Guest admitted."
3. Verify UIT26-TEST1 again -> orange "Ticket already used."
4. Verify UIT26-USED1 -> orange "already used".
5. Verify FAKE-123 -> red "Invalid ticket."
6. Look at Recent scans, and at the tickets and verification_log tables in phpMyAdmin.

TEST CASES (for the write-up)
Valid ticket           UIT26-TEST1            valid, status becomes used
Reuse                  UIT26-TEST1 again      used, entry refused
Already used           UIT26-USED1            used, entry refused
Fake ticket            FAKE-123               invalid
Bad characters         AB#$%                  invalid
Empty input            (nothing)              "Enter or paste a ticket number"
Lowercase input        uit26-test2            valid
Long code              (the verification_code of an issued ticket)   valid
No login               call the API without login   401, login needed
Wrong password         verifier@uiconf.test / wrong    error message
Attendee account       attendee@uiconf.test / password   refused (not a gate officer)
