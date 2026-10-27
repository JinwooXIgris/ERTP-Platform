<?php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/config.php';

function process_payment(array $order, array $input): array {
    $mode = defined('PAYMENT_MODE') ? PAYMENT_MODE : 'simulated';

    // 1. Handle Simulated Prototype Mode
    if ($mode === 'simulated') {
        $outcomes = ["success", "fail"];
        $simulatedOut = $input['simulate_outcome'] ?? 'success';
        
        if (!in_array($simulatedOut, $outcomes, true)) {
            fail('Invalid outcome.', 400, [['field'=>'simulate_outcome', 'message'=>'Outcome must be success or fail']]);
        }
        
        if ($simulatedOut === 'success') {
            return ['status' => 'success', 'provider_response' => 'Payment successful'];
        }
        return ['status' => 'failed', 'provider_response' => 'Payment failed'];
    }

    // 2. Handle Live / Test Paystack Mode
    if ($mode === 'paystack') {
        $reference = $input['reference'] ?? '';
        if (empty($reference)) {
            return ['status' => 'failed', 'provider_response' => 'Missing transaction reference'];
        }

        // Call Paystack API to verify the transaction reference
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        
        // CRITICAL FOR LOCALHOST: Disable SSL verification so it doesn't get blocked
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Cache-Control: no-cache"
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['status' => 'failed', 'provider_response' => 'Curl Error: ' . $err];
        }

        $result = json_decode($response, true);

        // Check if Paystack confirms the payment was a success
        if (isset($result['data']) && $result['data']['status'] === 'success') {
            
            // Security check: Ensure they paid the correct amount (Paystack amount is in Kobo)
            $paidAmount = $result['data']['amount'] / 100;
            if ($paidAmount < (float)$order['total_amount']) {
                return ['status' => 'failed', 'provider_response' => 'Amount mismatch. Paid: ' . $paidAmount];
            }

            // Success! Return positive status back to api/payment.php so it can update the DB
            return [
                'status' => 'success',
                'provider_response' => json_encode($result['data'])
            ];
        }

        // Transaction failed or was abandoned
        return ['status' => 'failed', 'provider_response' => $response];
    }

    fail('Gateway not configured correctly', 500);
}
?>