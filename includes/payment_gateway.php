<?php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/config.php';

final class PaymentGateway {
    public function process(array $order, array $input): array {
        $mode = defined('PAYMENT_MODE') ? PAYMENT_MODE : 'simulated';

        if ($mode === 'simulated' || isset($input['simulate_outcome'])) {
            $outcomes = ['success', 'fail'];
            $simulatedOut = $input['simulate_outcome'] ?? 'success';

            if (!in_array($simulatedOut, $outcomes, true)) {
                fail('Invalid outcome.', 400, [
                    ['field' => 'simulate_outcome', 'message' => 'Outcome must be success or fail']
                ]);
            }

            if ($simulatedOut === 'success') {
                return ['status' => 'success', 'provider_response' => 'Payment successful'];
            }
            return ['status' => 'failed', 'provider_response' => 'Payment failed'];
        }

        if ($mode === 'paystack') {
            $reference = $input['reference'] ?? '';
            if (empty($reference)) {
                return ['status' => 'failed', 'provider_response' => 'Missing transaction reference'];
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : ''),
                'Cache-Control: no-cache'
            ]);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return ['status' => 'failed', 'provider_response' => 'Curl Error: ' . $err];
            }

            $result = json_decode($response, true);

            if (isset($result['data']) && $result['data']['status'] === 'success') {
                $paidAmount = $result['data']['amount'] / 100;
                if ($paidAmount < (float)$order['total_amount']) {
                    return ['status' => 'failed', 'provider_response' => 'Amount mismatch. Paid: ' . $paidAmount];
                }

                return [
                    'status' => 'success',
                    'provider_response' => json_encode($result['data'])
                ];
            }

            return ['status' => 'failed', 'provider_response' => (string)$response];
        }

        fail('Gateway not configured correctly', 500);
    }
}

function process_payment(array $order, array $input): array {
    return (new PaymentGateway())->process($order, $input);
}