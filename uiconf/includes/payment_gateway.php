<?php
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/config.php';

function process_payment(array $order, array $input): array{
	$simulatedOut=null;
	if(PAYMENT_MODE=="simulated"){
		$outcomes=["success", "fail"];
		$simulatedOut=$input['simulate_outcome'] ?? null;
		if(!is_string($simulatedOut) || !in_array($simulatedOut, $outcomes, true)){
			fail('Invalid outcome.', 400, [['field'=>'simulate_outcome', 'message'=>'Outcome must be success or fail']]);
		}
	}
	else{
		fail('Gateway not implemented', 500);
	}
	$method=$input['method'] ?? null;
	if(!is_string($method) || $method!="simulated"){
		fail('Invalid payment method.', 400, [['field'=>'method', 'message'=>'Payment method must be simulated']]);
	}
	if($simulatedOut==='success'){
		return[
			'status'=>'success',
			'provider_response'=>'Payment successful'
		];
	}
	return[
		'status'=>'failed',
		'provider_response'=>'Payment failed'
	];
}
?>