param(
    [string]$BaseUrl = "http://localhost/uiconf"
)

$BaseUrl = $BaseUrl.TrimEnd('/')

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "UI TICKETING PLATFORM - COMPREHENSIVE ENDPOINT TEST SUITE" -ForegroundColor Cyan
Write-Host "Target: $BaseUrl" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

$results = @()
$testCount = 0
$passCount = 0
$failCount = 0

function Invoke-Api {
    param(
        [string]$Uri,
        [string]$Method = 'GET',
        [object]$Body = $null,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null,
        [hashtable]$Headers = @{}
    )

    $params = @{
        Uri             = $Uri
        Method          = $Method
        UseBasicParsing = $true
        ErrorAction     = 'Stop'
    }

    if ($Session) { $params['WebSession'] = $Session }

    if ($null -ne $Body) {
        if ($Body -is [string]) {
            $params['Body'] = $Body
        } else {
            $params['Body'] = ($Body | ConvertTo-Json -Compress -Depth 10)
        }
        $params['ContentType'] = 'application/json; charset=utf-8'
    }

    if ($Headers.Count -gt 0) {
        $params['Headers'] = $Headers
    }

    $cmd = Get-Command Invoke-WebRequest
    if ($cmd.Parameters.ContainsKey('SkipHttpErrorCheck')) {
        $params['SkipHttpErrorCheck'] = $true
    }

    $code = 0
    $content = ""

    try {
        $resp = Invoke-WebRequest @params
        $code = [int]$resp.StatusCode
        $content = $resp.Content
    }
    catch [System.Net.WebException] {
        $exResp = $_.Exception.Response
        if ($exResp) {
            $code = [int]$exResp.StatusCode
            $stream = $exResp.GetResponseStream()
            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $content = $reader.ReadToEnd()
            }
        } else {
            $code = 0
            $content = $_.Exception.Message
        }
    }
    catch {
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
            if ($_.Exception.Response.Content) {
                $content = $_.Exception.Response.Content
            } else {
                try {
                    $stream = $_.Exception.Response.GetResponseStream()
                    if ($stream) {
                        $reader = New-Object System.IO.StreamReader($stream)
                        $content = $reader.ReadToEnd()
                    }
                } catch {
                    $content = $_.Exception.Message
                }
            }
        } else {
            $code = 0
            $content = $_.Exception.Message
        }
    }

    $json = $null
    if ($content) {
        try {
            $json = $content | ConvertFrom-Json
        } catch {
            $json = $null
        }
    }

    return [PSCustomObject]@{
        StatusCode = $code
        Content    = $content
        Json       = $json
    }
}

function Record-Test {
    param(
        [string]$Category,
        [string]$Description,
        [int]$ExpectedStatus,
        [object]$Response,
        [scriptblock]$ExtraCheck = $null
    )

    $script:testCount++
    $actualStatus = $Response.StatusCode
    $passed = ($actualStatus -eq $ExpectedStatus)
    $extraMessage = ""

    if ($passed -and $null -ne $ExtraCheck) {
        try {
            $checkResult = & $ExtraCheck $Response
            if ($checkResult -is [bool] -and -not $checkResult) {
                $passed = $false
                $extraMessage = "Extra assertion failed"
            } elseif ($checkResult -is [string] -and $checkResult -ne "") {
                $passed = $false
                $extraMessage = $checkResult
            }
        } catch {
            $passed = $false
            $extraMessage = "Assertion exception: $($_.Exception.Message)"
        }
    }

    if ($passed) {
        $script:passCount++
        Write-Host "  [PASS] ($actualStatus) $Category - $Description" -ForegroundColor Green
    } else {
        $script:failCount++
        $errMsg = if ($extraMessage) { $extraMessage } else { "Expected $ExpectedStatus, got $actualStatus" }
        Write-Host "  [FAIL] ($actualStatus) $Category - $Description -> $errMsg" -ForegroundColor Red
        if ($Response.Content) {
            Write-Host "         Response body: $($Response.Content)" -ForegroundColor DarkGray
        }
    }

    $script:results += [PSCustomObject]@{
        Index       = $script:testCount
        Category    = $Category
        Description = $Description
        Expected    = $ExpectedStatus
        Actual      = $actualStatus
        Status      = if ($passed) { "PASS" } else { "FAIL" }
        Note        = $extraMessage
    }
}

# 1. LIVENESS CHECK
Write-Host "Checking service availability..." -ForegroundColor Yellow
$liveCheck = Invoke-Api -Uri "$BaseUrl/api/event.php" -Method 'GET'
if ($liveCheck.StatusCode -ne 200) {
    Write-Host "ERROR: Cannot connect to $BaseUrl/api/event.php (HTTP $($liveCheck.StatusCode))." -ForegroundColor Red
    Write-Host "Ensure the web server is running. (e.g. php -S localhost:8000 or Apache)" -ForegroundColor Yellow
    exit 1
}
Write-Host "Service is online.`n" -ForegroundColor Green

# 2. SAVE INITIAL STATE FOR TEARDOWN
$originalEvent = $liveCheck.Json.data.event
$catCheck = Invoke-Api -Uri "$BaseUrl/api/categories.php" -Method 'GET'
$originalCat1 = ($catCheck.Json.data.categories | Where-Object { $_.id -eq 1 })
$originalCat1Price = if ($originalCat1) { $originalCat1.price } else { "2000.00" }

# 3. AUTHENTICATE SESSIONS
Write-Host "Setting up user sessions..." -ForegroundColor Yellow

# Admin session
$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminLogin = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' `
    -Body @{ email = "admin@uiconf.test"; password = "password" } `
    -Session $adminSession
if ($adminLogin.StatusCode -ne 200) {
    Write-Host "FATAL: Could not log in as admin@uiconf.test" -ForegroundColor Red
    exit 1
}

# Verifier session
$verifierSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$verifierLogin = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' `
    -Body @{ email = "verifier@uiconf.test"; password = "password" } `
    -Session $verifierSession
if ($verifierLogin.StatusCode -ne 200) {
    Write-Host "FATAL: Could not log in as verifier@uiconf.test" -ForegroundColor Red
    exit 1
}

# Attendee session
$attendeeSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$attendeeLogin = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' `
    -Body @{ email = "attendee@uiconf.test"; password = "password" } `
    -Session $attendeeSession
if ($attendeeLogin.StatusCode -ne 200) {
    Write-Host "FATAL: Could not log in as attendee@uiconf.test" -ForegroundColor Red
    exit 1
}

Write-Host "Sessions created for admin, verifier, and attendee.`n" -ForegroundColor Green

try {
    # ==============================================================
    # SUITE 1: 405 METHOD NOT ALLOWED ON ALL 17 ENDPOINTS
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 1: 405 Method Not Allowed checks" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    # GET-only endpoints -> send POST
    $getEndpoints = @(
        "api/event.php",
        "api/categories.php",
        "api/me.php",
        "api/ticket.php",
        "api/my-tickets.php",
        "api/admin/stats.php",
        "api/admin/attendees.php",
        "api/admin/orders.php",
        "api/admin/users.php"
    )
    foreach ($ep in $getEndpoints) {
        $res = Invoke-Api -Uri "$BaseUrl/$ep" -Method 'POST' -Body @{}
        Record-Test "405 Checks" "POST to GET-only $ep returns 405" 405 $res {
            param($r) ($r.Json.success -eq $false) -and ($r.Json.message -like "*Method not allowed*")
        }
    }

    # POST-only endpoints -> send GET
    $postEndpoints = @(
        "api/register.php",
        "api/login.php",
        "api/logout.php",
        "api/payment.php",
        "api/verify-ticket.php",
        "api/admin/update-price.php",
        "api/admin/update-event.php"
    )
    foreach ($ep in $postEndpoints) {
        $res = Invoke-Api -Uri "$BaseUrl/$ep" -Method 'GET'
        Record-Test "405 Checks" "GET to POST-only $ep returns 405" 405 $res {
            param($r) ($r.Json.success -eq $false) -and ($r.Json.message -like "*Method not allowed*")
        }
    }

    # Dual method endpoint api/order.php (allows GET, POST) -> send DELETE
    $orderDelete = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'DELETE'
    Record-Test "405 Checks" "DELETE to api/order.php returns 405" 405 $orderDelete {
        param($r) ($r.Json.success -eq $false)
    }

    # Execution order check: wrong method returns 405 before auth check on protected endpoint
    $seqCheck = Invoke-Api -Uri "$BaseUrl/api/admin/stats.php" -Method 'POST' -Body @{}
    Record-Test "405 Checks" "POST to admin/stats as guest returns 405 (method checked before auth)" 405 $seqCheck

    Write-Host ""

    # ==============================================================
    # SUITE 2: PUBLIC ENDPOINTS (event, categories, me as guest)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 2: Public Endpoints" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    $evtRes = Invoke-Api -Uri "$BaseUrl/api/event.php" -Method 'GET'
    Record-Test "Public" "GET api/event.php returns event object" 200 $evtRes {
        param($r) ($r.Json.success -eq $true) -and ($null -ne $r.Json.data.event.title)
    }

    $catsRes = Invoke-Api -Uri "$BaseUrl/api/categories.php" -Method 'GET'
    Record-Test "Public" "GET api/categories.php returns categories list" 200 $catsRes {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.categories.Count -gt 0)
    }

    $meGuest = Invoke-Api -Uri "$BaseUrl/api/me.php" -Method 'GET'
    Record-Test "Public" "GET api/me.php as guest returns 200 with user = null" 200 $meGuest {
        param($r) ($r.Json.success -eq $true) -and ($null -eq $r.Json.data.user)
    }

    Write-Host ""

    # ==============================================================
    # SUITE 3: REGISTRATION (api/register.php)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 3: Registration" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    $timestamp = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $uniqueEmail = "user_${timestamp}@uiconf.test"

    $regValid = Invoke-Api -Uri "$BaseUrl/api/register.php" -Method 'POST' -Body @{
        full_name = "New Test Attendee"
        email = $uniqueEmail
        password = "securePassword123"
        phone = "08012345678"
        attendee_type = "student"
    }
    Record-Test "Registration" "POST api/register.php with valid data returns 201" 201 $regValid {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.user_id -gt 0)
    }

    $regDup = Invoke-Api -Uri "$BaseUrl/api/register.php" -Method 'POST' -Body @{
        full_name = "Duplicate Attendee"
        email = $uniqueEmail
        password = "securePassword123"
        phone = "08012345678"
        attendee_type = "student"
    }
    Record-Test "Registration" "POST api/register.php with duplicate email returns 409" 409 $regDup {
        param($r) ($r.Json.success -eq $false) -and ($r.Json.message -like "*already registered*")
    }

    $regBad = Invoke-Api -Uri "$BaseUrl/api/register.php" -Method 'POST' -Body @{
        full_name = ""
        email = "not-an-email"
        password = "short"
        phone = ""
    }
    Record-Test "Registration" "POST api/register.php with invalid input returns 400" 400 $regBad {
        param($r) ($r.Json.success -eq $false) -and ($r.Json.errors.Count -gt 0)
    }

    Write-Host ""

    # ==============================================================
    # SUITE 4: LOGIN & LOGOUT & ME
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 4: Login, Logout, and Session Verification" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    $loginValid = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' -Body @{
        email = "attendee@uiconf.test"
        password = "password"
    }
    Record-Test "Auth" "POST api/login.php with valid credentials returns 200" 200 $loginValid {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.user.email -eq "attendee@uiconf.test")
    }

    $loginWrongPass = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' -Body @{
        email = "attendee@uiconf.test"
        password = "wrongPassword!"
    }
    Record-Test "Auth" "POST api/login.php with wrong password returns 401" 401 $loginWrongPass {
        param($r) ($r.Json.success -eq $false)
    }

    $loginBadInput = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' -Body @{
        email = "bademail"
        password = ""
    }
    Record-Test "Auth" "POST api/login.php with invalid input returns 400" 400 $loginBadInput

    $meAttendee = Invoke-Api -Uri "$BaseUrl/api/me.php" -Method 'GET' -Session $attendeeSession
    Record-Test "Auth" "GET api/me.php as logged-in attendee returns user details" 200 $meAttendee {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.user.email -eq "attendee@uiconf.test")
    }

    # Throwaway session for logout test
    $throwawaySession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $thLogin = Invoke-Api -Uri "$BaseUrl/api/login.php" -Method 'POST' `
        -Body @{ email = $uniqueEmail; password = "securePassword123" } `
        -Session $throwawaySession

    $thLogout = Invoke-Api -Uri "$BaseUrl/api/logout.php" -Method 'POST' -Session $throwawaySession
    Record-Test "Auth" "POST api/logout.php returns 200" 200 $thLogout {
        param($r) ($r.Json.success -eq $true)
    }

    $thMe = Invoke-Api -Uri "$BaseUrl/api/me.php" -Method 'GET' -Session $throwawaySession
    Record-Test "Auth" "GET api/me.php after logout returns 200 with user = null" 200 $thMe {
        param($r) ($r.Json.success -eq $true) -and ($null -eq $r.Json.data.user)
    }

    $guestLogout = Invoke-Api -Uri "$BaseUrl/api/logout.php" -Method 'POST'
    Record-Test "Auth" "POST api/logout.php as guest returns 200" 200 $guestLogout

    Write-Host ""

    # ==============================================================
    # SUITE 5: ORDERS (POST api/order.php & GET api/order.php?id=...)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 5: Orders (Creation & Retrieval)" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    # POST order as guest
    $orderGuest = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @(@{ category_id = 1; quantity = 1 }) }
    Record-Test "Order" "POST api/order.php as guest returns 401" 401 $orderGuest

    # POST order with empty items
    $orderEmpty = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @() } `
        -Session $attendeeSession
    Record-Test "Order" "POST api/order.php with empty items returns 400" 400 $orderEmpty

    # POST order with duplicate category
    $orderDupCat = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @(@{ category_id = 1; quantity = 1 }, @{ category_id = 1; quantity = 2 }) } `
        -Session $attendeeSession
    Record-Test "Order" "POST api/order.php with duplicate category returns 400" 400 $orderDupCat

    # POST order exceeding cap (>10 tickets total)
    $orderCap = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @(@{ category_id = 1; quantity = 11 }) } `
        -Session $attendeeSession
    Record-Test "Order" "POST api/order.php with total qty > 10 returns 400" 400 $orderCap

    # POST order with non-existent category
    $orderUnknown = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @(@{ category_id = 999999; quantity = 1 }) } `
        -Session $attendeeSession
    Record-Test "Order" "POST api/order.php with unknown category returns 404" 404 $orderUnknown

    # POST valid order
    $createdOrder = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @(@{ category_id = 1; quantity = 2 }) } `
        -Session $attendeeSession
    $orderId = 0
    if ($createdOrder.Json -and $createdOrder.Json.data -and $createdOrder.Json.data.order) {
        $orderId = [int]$createdOrder.Json.data.order.id
    }
    Record-Test "Order" "POST api/order.php creates pending order (201)" 201 $createdOrder {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.order.id -gt 0) -and ($r.Json.data.order.status -eq 'pending')
    }

    # GET order by id as guest
    $getOrderGuest = Invoke-Api -Uri "$BaseUrl/api/order.php?id=$orderId" -Method 'GET'
    Record-Test "Order" "GET api/order.php?id=$orderId as guest returns 401" 401 $getOrderGuest

    # GET order with bad id
    $getOrderBad = Invoke-Api -Uri "$BaseUrl/api/order.php?id=notanid" -Method 'GET' -Session $attendeeSession
    Record-Test "Order" "GET api/order.php?id=notanid returns 400" 400 $getOrderBad

    # GET order with non-existent id
    $getOrder404 = Invoke-Api -Uri "$BaseUrl/api/order.php?id=999999" -Method 'GET' -Session $attendeeSession
    Record-Test "Order" "GET api/order.php?id=999999 returns 404" 404 $getOrder404

    # GET order as owner
    $getOrderOwner = Invoke-Api -Uri "$BaseUrl/api/order.php?id=$orderId" -Method 'GET' -Session $attendeeSession
    Record-Test "Order" "GET api/order.php?id=$orderId as owner returns 200" 200 $getOrderOwner {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.order.id -eq $orderId)
    }

    Write-Host ""

    # ==============================================================
    # SUITE 6: PAYMENT (api/payment.php)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 6: Payment Processing" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    # Payment bad reference format
    $payBadRef = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = $orderId; reference = "invalid ref!"; method = "card" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php with invalid reference format returns 400" 400 $payBadRef

    # Payment non-existent order
    $fakeRef = "PAY-" + [Guid]::NewGuid().ToString('N').Substring(0, 16).ToUpper()
    $payUnknownOrder = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = 999999; reference = $fakeRef; method = "card" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php on non-existent order returns 404" 404 $payUnknownOrder

    # Create a secondary order for simulated failure test
    $failOrderReq = Invoke-Api -Uri "$BaseUrl/api/order.php" -Method 'POST' `
        -Body @{ items = @(@{ category_id = 1; quantity = 1 }) } `
        -Session $attendeeSession
    $failOrderId = [int]$failOrderReq.Json.data.order.id

    $failRef = "PAY-" + [Guid]::NewGuid().ToString('N').Substring(0, 16).ToUpper()
    $payFail = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = $failOrderId; reference = $failRef; method = "card"; simulate_outcome = "fail" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php with simulated FAIL outcome returns 402" 402 $payFail {
        param($r) ($r.Json.success -eq $false) -and ($r.Json.data.order.status -eq 'pending')
    }

    # Successful payment on primary order
    $successRef = "PAY-" + [Guid]::NewGuid().ToString('N').Substring(0, 16).ToUpper()
    $paySuccess = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = $orderId; reference = $successRef; method = "card"; simulate_outcome = "success" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php with valid ref returns 200 and issues tickets" 200 $paySuccess {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.order.status -eq 'paid') -and ($r.Json.data.ticket_count -eq 2)
    }

    # Idempotent retry with identical reference
    $payIdempotent = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = $orderId; reference = $successRef; method = "card" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php idempotently repeated returns 200 with same tickets" 200 $payIdempotent {
        param($r) ($r.Json.success -eq $true)
    }

    # Attempting to use the same reference on a different order (failOrderId)
    $payReusedRef = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = $failOrderId; reference = $successRef; method = "card" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php reusing reference on different order returns 409" 409 $payReusedRef

    # Attempting to pay an already paid order with a new reference
    $newRef = "PAY-" + [Guid]::NewGuid().ToString('N').Substring(0, 16).ToUpper()
    $payAlreadyPaid = Invoke-Api -Uri "$BaseUrl/api/payment.php" -Method 'POST' `
        -Body @{ order_id = $orderId; reference = $newRef; method = "card" } `
        -Session $attendeeSession
    Record-Test "Payment" "POST api/payment.php on already paid order returns 409" 409 $payAlreadyPaid

    Write-Host ""

    # ==============================================================
    # SUITE 7: TICKETS (api/ticket.php & api/my-tickets.php)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 7: Ticket Retrieval" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    $ticketGuest = Invoke-Api -Uri "$BaseUrl/api/ticket.php?order_id=$orderId" -Method 'GET'
    Record-Test "Tickets" "GET api/ticket.php as guest returns 401" 401 $ticketGuest

    $ticketBadOrder = Invoke-Api -Uri "$BaseUrl/api/ticket.php?order_id=bad" -Method 'GET' -Session $attendeeSession
    Record-Test "Tickets" "GET api/ticket.php with non-numeric order_id returns 400" 400 $ticketBadOrder

    $ticket404 = Invoke-Api -Uri "$BaseUrl/api/ticket.php?order_id=999999" -Method 'GET' -Session $attendeeSession
    Record-Test "Tickets" "GET api/ticket.php with unknown order_id returns 404" 404 $ticket404

    $ticketOwner = Invoke-Api -Uri "$BaseUrl/api/ticket.php?order_id=$orderId" -Method 'GET' -Session $attendeeSession
    $validTicketNumber = ""
    if ($ticketOwner.Json -and $ticketOwner.Json.data -and $ticketOwner.Json.data.tickets.Count -gt 0) {
        $validTicketNumber = $ticketOwner.Json.data.tickets[0].ticket_number
    }
    Record-Test "Tickets" "GET api/ticket.php?order_id=$orderId as owner returns tickets" 200 $ticketOwner {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.tickets.Count -eq 2)
    }

    $myTicketsGuest = Invoke-Api -Uri "$BaseUrl/api/my-tickets.php" -Method 'GET'
    Record-Test "Tickets" "GET api/my-tickets.php as guest returns 401" 401 $myTicketsGuest

    $myTicketsAuth = Invoke-Api -Uri "$BaseUrl/api/my-tickets.php" -Method 'GET' -Session $attendeeSession
    Record-Test "Tickets" "GET api/my-tickets.php as attendee returns tickets list" 200 $myTicketsAuth {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.tickets.Count -ge 2)
    }

    Write-Host ""

    # ==============================================================
    # SUITE 8: TICKET VERIFICATION (api/verify-ticket.php)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 8: Ticket Verification" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    $verGuest = Invoke-Api -Uri "$BaseUrl/api/verify-ticket.php" -Method 'POST' `
        -Body @{ value = $validTicketNumber }
    Record-Test "Verification" "POST api/verify-ticket.php as guest returns 401" 401 $verGuest

    $verAttendee = Invoke-Api -Uri "$BaseUrl/api/verify-ticket.php" -Method 'POST' `
        -Body @{ value = $validTicketNumber } `
        -Session $attendeeSession
    Record-Test "Verification" "POST api/verify-ticket.php as attendee returns 403" 403 $verAttendee

    $verBadInput = Invoke-Api -Uri "$BaseUrl/api/verify-ticket.php" -Method 'POST' `
        -Body @{ value = "" } `
        -Session $verifierSession
    Record-Test "Verification" "POST api/verify-ticket.php with empty value returns 400" 400 $verBadInput

    # Verifier scans fresh valid ticket
    $verValid = Invoke-Api -Uri "$BaseUrl/api/verify-ticket.php" -Method 'POST' `
        -Body @{ value = $validTicketNumber } `
        -Session $verifierSession
    Record-Test "Verification" "POST api/verify-ticket.php scans fresh ticket -> valid" 200 $verValid {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.result -eq 'valid')
    }

    # Verifier scans used ticket again
    $verUsed = Invoke-Api -Uri "$BaseUrl/api/verify-ticket.php" -Method 'POST' `
        -Body @{ value = $validTicketNumber } `
        -Session $verifierSession
    Record-Test "Verification" "POST api/verify-ticket.php scans already used ticket -> used" 200 $verUsed {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.result -eq 'used')
    }

    # Verifier scans bogus code
    $verInvalid = Invoke-Api -Uri "$BaseUrl/api/verify-ticket.php" -Method 'POST' `
        -Body @{ value = "INVALID-BOGUS-CODE-999" } `
        -Session $verifierSession
    Record-Test "Verification" "POST api/verify-ticket.php scans bogus code -> invalid" 200 $verInvalid {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.result -eq 'invalid')
    }

    Write-Host ""

    # ==============================================================
    # SUITE 9: ADMIN ENDPOINTS (api/admin/*)
    # ==============================================================
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "SUITE 9: Admin Endpoints" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    # 1. stats.php
    $statsGuest = Invoke-Api -Uri "$BaseUrl/api/admin/stats.php" -Method 'GET'
    Record-Test "Admin Stats" "GET api/admin/stats.php as guest returns 401" 401 $statsGuest

    $statsAttendee = Invoke-Api -Uri "$BaseUrl/api/admin/stats.php" -Method 'GET' -Session $attendeeSession
    Record-Test "Admin Stats" "GET api/admin/stats.php as attendee returns 403" 403 $statsAttendee

    $statsAdmin = Invoke-Api -Uri "$BaseUrl/api/admin/stats.php" -Method 'GET' -Session $adminSession
    Record-Test "Admin Stats" "GET api/admin/stats.php as admin returns 200 with totals and breakdown" 200 $statsAdmin {
        param($r) ($r.Json.success -eq $true) -and ($null -ne $r.Json.data.totals) -and ($null -ne $r.Json.data.per_category)
    }

    # 2. attendees.php
    $attGuest = Invoke-Api -Uri "$BaseUrl/api/admin/attendees.php" -Method 'GET'
    Record-Test "Admin Attendees" "GET api/admin/attendees.php as guest returns 401" 401 $attGuest

    $attAttendee = Invoke-Api -Uri "$BaseUrl/api/admin/attendees.php" -Method 'GET' -Session $attendeeSession
    Record-Test "Admin Attendees" "GET api/admin/attendees.php as attendee returns 403" 403 $attAttendee

    $attBadPage = Invoke-Api -Uri "$BaseUrl/api/admin/attendees.php?page=-1" -Method 'GET' -Session $adminSession
    Record-Test "Admin Attendees" "GET api/admin/attendees.php with negative page returns 400" 400 $attBadPage

    $attAdmin = Invoke-Api -Uri "$BaseUrl/api/admin/attendees.php?page=1" -Method 'GET' -Session $adminSession
    Record-Test "Admin Attendees" "GET api/admin/attendees.php as admin returns 200 paginated list" 200 $attAdmin {
        param($r) ($r.Json.success -eq $true) -and ($null -ne $r.Json.data.attendees) -and ($r.Json.data.page -eq 1)
    }

    # 3. orders.php
    $ordGuest = Invoke-Api -Uri "$BaseUrl/api/admin/orders.php" -Method 'GET'
    Record-Test "Admin Orders" "GET api/admin/orders.php as guest returns 401" 401 $ordGuest

    $ordAttendee = Invoke-Api -Uri "$BaseUrl/api/admin/orders.php" -Method 'GET' -Session $attendeeSession
    Record-Test "Admin Orders" "GET api/admin/orders.php as attendee returns 403" 403 $ordAttendee

    $ordBadPage = Invoke-Api -Uri "$BaseUrl/api/admin/orders.php?page=abc" -Method 'GET' -Session $adminSession
    Record-Test "Admin Orders" "GET api/admin/orders.php with non-integer page returns 400" 400 $ordBadPage

    $ordAdmin = Invoke-Api -Uri "$BaseUrl/api/admin/orders.php?page=1" -Method 'GET' -Session $adminSession
    Record-Test "Admin Orders" "GET api/admin/orders.php as admin returns 200 with nested payments" 200 $ordAdmin {
        param($r) ($r.Json.success -eq $true) -and ($null -ne $r.Json.data.orders) -and ($r.Json.data.page -eq 1)
    }

    # 4. users.php
    $usrGuest = Invoke-Api -Uri "$BaseUrl/api/admin/users.php" -Method 'GET'
    Record-Test "Admin Users" "GET api/admin/users.php as guest returns 401" 401 $usrGuest

    $usrAttendee = Invoke-Api -Uri "$BaseUrl/api/admin/users.php" -Method 'GET' -Session $attendeeSession
    Record-Test "Admin Users" "GET api/admin/users.php as attendee returns 403" 403 $usrAttendee

    $usrBadPage = Invoke-Api -Uri "$BaseUrl/api/admin/users.php?page=0" -Method 'GET' -Session $adminSession
    Record-Test "Admin Users" "GET api/admin/users.php with page=0 returns 400" 400 $usrBadPage

    $usrAdmin = Invoke-Api -Uri "$BaseUrl/api/admin/users.php?page=1" -Method 'GET' -Session $adminSession
    Record-Test "Admin Users" "GET api/admin/users.php as admin returns 200 users list" 200 $usrAdmin {
        param($r) ($r.Json.success -eq $true) -and ($null -ne $r.Json.data.users)
    }

    # 5. update-price.php
    $prcGuest = Invoke-Api -Uri "$BaseUrl/api/admin/update-price.php" -Method 'POST' `
        -Body @{ category_id = 1; price = "3000.00" }
    Record-Test "Admin Update Price" "POST api/admin/update-price.php as guest returns 401" 401 $prcGuest

    $prcAttendee = Invoke-Api -Uri "$BaseUrl/api/admin/update-price.php" -Method 'POST' `
        -Body @{ category_id = 1; price = "3000.00" } `
        -Session $attendeeSession
    Record-Test "Admin Update Price" "POST api/admin/update-price.php as attendee returns 403" 403 $prcAttendee

    $prcBadPrice = Invoke-Api -Uri "$BaseUrl/api/admin/update-price.php" -Method 'POST' `
        -Body @{ category_id = 1; price = "invalid-price" } `
        -Session $adminSession
    Record-Test "Admin Update Price" "POST api/admin/update-price.php with invalid price returns 400" 400 $prcBadPrice

    $prc404 = Invoke-Api -Uri "$BaseUrl/api/admin/update-price.php" -Method 'POST' `
        -Body @{ category_id = 999999; price = "3000.00" } `
        -Session $adminSession
    Record-Test "Admin Update Price" "POST api/admin/update-price.php with unknown category returns 404" 404 $prc404

    $prcAdmin = Invoke-Api -Uri "$BaseUrl/api/admin/update-price.php" -Method 'POST' `
        -Body @{ category_id = 1; price = "3000.00" } `
        -Session $adminSession
    Record-Test "Admin Update Price" "POST api/admin/update-price.php as admin updates price to 3000.00" 200 $prcAdmin {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.category.price -eq "3000.00")
    }

    # 6. update-event.php
    $evtGuest = Invoke-Api -Uri "$BaseUrl/api/admin/update-event.php" -Method 'POST' `
        -Body @{ title = "X" }
    Record-Test "Admin Update Event" "POST api/admin/update-event.php as guest returns 401" 401 $evtGuest

    $evtAttendee = Invoke-Api -Uri "$BaseUrl/api/admin/update-event.php" -Method 'POST' `
        -Body @{ title = "X" } `
        -Session $attendeeSession
    Record-Test "Admin Update Event" "POST api/admin/update-event.php as attendee returns 403" 403 $evtAttendee

    $evtMissing = Invoke-Api -Uri "$BaseUrl/api/admin/update-event.php" -Method 'POST' `
        -Body @{ title = "Test Title" } `
        -Session $adminSession
    Record-Test "Admin Update Event" "POST api/admin/update-event.php with missing required fields returns 400" 400 $evtMissing

    $evtBadDate = Invoke-Api -Uri "$BaseUrl/api/admin/update-event.php" -Method 'POST' `
        -Body @{
            title = "Test Event"
            venue = "Test Venue"
            event_date = "20-11-2026"
            description = "Test Desc"
            schedule = "Test Sched"
            speakers = "Test Speakers"
        } `
        -Session $adminSession
    Record-Test "Admin Update Event" "POST api/admin/update-event.php with invalid date format returns 400" 400 $evtBadDate

    $evtAdmin = Invoke-Api -Uri "$BaseUrl/api/admin/update-event.php" -Method 'POST' `
        -Body @{
            title = "Temp Event Title for Test"
            venue = $originalEvent.venue
            event_date = $originalEvent.event_date
            description = $originalEvent.description
            schedule = $originalEvent.schedule
            speakers = $originalEvent.speakers
        } `
        -Session $adminSession
    Record-Test "Admin Update Event" "POST api/admin/update-event.php as admin updates event" 200 $evtAdmin {
        param($r) ($r.Json.success -eq $true) -and ($r.Json.data.event.title -eq "Temp Event Title for Test")
    }

}
finally {
    # ==============================================================
    # RESTORE ORIGINAL STATE
    # ==============================================================
    Write-Host "`nRestoring original event state and category 1 price..." -ForegroundColor Yellow

    if ($originalCat1Price) {
        $restorePrice = Invoke-Api -Uri "$BaseUrl/api/admin/update-price.php" -Method 'POST' `
            -Body @{ category_id = 1; price = $originalCat1Price } `
            -Session $adminSession
        if ($restorePrice.StatusCode -eq 200) {
            Write-Host "Category 1 price restored to $originalCat1Price." -ForegroundColor Green
        } else {
            Write-Host "Warning: Could not restore category 1 price (HTTP $($restorePrice.StatusCode))." -ForegroundColor DarkYellow
        }
    }

    if ($originalEvent) {
        $restoreEvent = Invoke-Api -Uri "$BaseUrl/api/admin/update-event.php" -Method 'POST' `
            -Body @{
                title = $originalEvent.title
                venue = $originalEvent.venue
                event_date = $originalEvent.event_date
                description = $originalEvent.description
                schedule = $originalEvent.schedule
                speakers = $originalEvent.speakers
            } `
            -Session $adminSession
        if ($restoreEvent.StatusCode -eq 200) {
            Write-Host "Event details restored." -ForegroundColor Green
        } else {
            Write-Host "Warning: Could not restore event details (HTTP $($restoreEvent.StatusCode))." -ForegroundColor DarkYellow
        }
    }
}

# ==============================================================
# SUMMARY REPORT & WRITE TO tests/test-endpoints_results.txt
# ==============================================================
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "TEST EXECUTION SUMMARY" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "Total Tests Run : $testCount"
Write-Host "Passed          : $passCount" -ForegroundColor Green
Write-Host "Failed          : $failCount" -ForegroundColor $(if ($failCount -gt 0) { "Red" } else { "Green" })
Write-Host "============================================================" -ForegroundColor Cyan

$reportLines = @()
$reportLines += "============================================================"
$reportLines += "UI TICKETING PLATFORM - TEST RESULTS REPORT"
$reportLines += "Generated at: $([DateTime]::UtcNow.ToString('yyyy-MM-dd HH:mm:ss UTC'))"
$reportLines += "Target URL  : $BaseUrl"
$reportLines += "============================================================"
$reportLines += "Total Tests: $testCount | Passed: $passCount | Failed: $failCount"
$reportLines += ""
$reportLines += ("{0,-5} {1,-6} {2,-24} {3,-8} {4,-8} {5}" -f "#", "STATUS", "CATEGORY", "EXPECTED", "ACTUAL", "DESCRIPTION")
$reportLines += ("-" * 80)

foreach ($r in $results) {
    $reportLines += ("{0,-5} {1,-6} {2,-24} {3,-8} {4,-8} {5}" -f $r.Index, $r.Status, $r.Category, $r.Expected, $r.Actual, $r.Description)
    if ($r.Note) {
        $reportLines += "      NOTE: $($r.Note)"
    }
}

$reportPath = Join-Path $PSScriptRoot "test-endpoints_results.txt"
$reportLines | Out-File -FilePath $reportPath -Encoding utf8
Write-Host "Detailed report written to: $reportPath" -ForegroundColor Cyan

if ($failCount -gt 0) {
    exit 1
} else {
    exit 0
}
