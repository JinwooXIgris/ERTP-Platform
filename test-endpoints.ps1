# test-endpoints.ps1
# Run with: .\test-endpoints.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "TESTING ALL 6 ENDPOINTS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Create a session and log in
Write-Host "Step 1: Logging in as admin..." -ForegroundColor Yellow
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$loginBody = @{
    email = "admin@uiconf.test"
    password = "password"
} | ConvertTo-Json

$loginResponse = Invoke-WebRequest -Uri "http://localhost/uiconf/api/login.php" -Method POST `
  -ContentType "application/json" `
  -Body $loginBody `
  -WebSession $session

Write-Host "Login successful. Session created." -ForegroundColor Green
Write-Host ""

# Test 1: event.php
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test 1: GET /api/event.php" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/event.php" -Method GET
Write-Host $response.Content
Write-Host ""

# Test 2: categories.php
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test 2: GET /api/categories.php" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/categories.php" -Method GET
Write-Host $response.Content
Write-Host ""

# Test 3: admin/stats.php
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test 3: GET /api/admin/stats.php (admin only)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/admin/stats.php" -Method GET `
  -WebSession $session
Write-Host $response.Content
Write-Host ""

# Test 4: admin/attendees.php
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test 4: GET /api/admin/attendees.php (admin only)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/admin/attendees.php" -Method GET `
  -WebSession $session
Write-Host $response.Content
Write-Host ""

# Test 5: admin/update-price.php
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test 5: POST /api/admin/update-price.php (admin only)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
$updatePriceBody = @{
    category_id = 1
    price = "2500.00"
} | ConvertTo-Json

$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/admin/update-price.php" -Method POST `
  -ContentType "application/json" `
  -Body $updatePriceBody `
  -WebSession $session

Write-Host $response.Content
Write-Host ""

# Verify price changed
Write-Host "Verifying price changed in categories.php..." -ForegroundColor Yellow
$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/categories.php" -Method GET
$categoriesData = $response.Content | ConvertFrom-Json
$student = $categoriesData.data.categories | Where-Object {$_.id -eq 1}
Write-Host "Student category price is now: $($student.price)" -ForegroundColor Green
Write-Host ""

# Test 6: admin/update-event.php
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test 6: POST /api/admin/update-event.php (admin only)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
$updateEventBody = @{
    title = "Updated: University of Ibadan Technology Conference 2026"
    event_date = "2026-11-20 09:00:00"
    venue = "Kunle Torimiro Centre, University of Ibadan"
    description = "Updated description: A premier technology conference bringing together students, industry professionals, and academic leaders."
    schedule = "Updated schedule: 09:00 Registration | 10:00 Keynote | 11:00 Parallel Tracks | 12:00 Lunch | 13:00 Panel Discussion"
    speakers = "Updated speakers: Dr. Adebayo Okafor | Chinedu Okoro | Zainab Al-Rashid | Prof. Olayinka Abayomi | Yusuf Mohammed"
} | ConvertTo-Json

$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/admin/update-event.php" -Method POST `
  -ContentType "application/json" `
  -Body $updateEventBody `
  -WebSession $session

Write-Host $response.Content
Write-Host ""

# Verify event changed
Write-Host "Verifying event changed in event.php..." -ForegroundColor Yellow
$response = Invoke-WebRequest -Uri "http://localhost/uiconf/api/event.php" -Method GET
$eventData = $response.Content | ConvertFrom-Json
Write-Host "Event title is now: $($eventData.data.event.title)" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Green
Write-Host "ALL TESTS COMPLETED" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green