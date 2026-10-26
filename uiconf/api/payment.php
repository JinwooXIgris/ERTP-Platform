<?php
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ticketing.php';
require_once __DIR__ . '/../includes/payment_gateway.php';


require_method('POST');
$user=require_login();
$pdo=db();
$user_id=(int)$user['id'];
$input=read_json();

$orderId=filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT);
$reference=$input['reference'] ?? null;
$method=$input['method'] ?? null;	
if(!is_string($method)){
	fail('Invalid payment method.', 400, [['field'=>'method', 'message'=>'The payment method  must be a string.']]);
}
if($orderId===false || $orderId<1){
	fail('A valid order ID is needed.', 400, [['field'=>'order_id', 'message'=>'Invalid ID' ]]);
}
if(!is_string($reference) || !preg_match('/^PAY-[A-Fa-f0-9]{16,32}$/D', $reference)){
	fail('Invalid payment reference.', 400, [['field'=>'reference', 'message'=>'Reference must begin with PAY- followed by 16-32 HEX characters.' ]]);
}

$stmt=$pdo->prepare("SELECT id, user_id, status, total_amount FROM orders WHERE id=:id AND user_id=:user_id");
$stmt->execute([':id'=>$orderId, ':user_id'=>$user_id]);
$order=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$order){
	fail('Order not found.', 404);
}



$stmt=$pdo->prepare("SELECT id, order_id, reference, amount, method, status, created_at FROM payments WHERE reference=:reference");
$stmt->execute([':reference'=>$reference]);
$payment=$stmt->fetch(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare("SELECT COUNT(*) FROM tickets WHERE order_id=:order_id");
$stmt->execute([':order_id'=>$orderId]);
$ticketCount=(int)$stmt->fetchColumn();
if($payment){
	if($orderId!==(int)$payment['order_id']){
		fail('This reference has already been used for another order', 409, [['field'=>'reference', 'message'=>'Use a new payment reference.' ]]);
	}
	$recPayment=[
		'reference'=>$payment['reference'],
		'status'=>$payment['status'],
		'amount'=>$payment['amount']
	];
	$recOrder=[
		'id'=>$order['id'],
		'status'=>$order['status']
	];
	$return_data=[
		'payment'=>$recPayment,
		'order'=>$recOrder,
		'ticket_count'=>$ticketCount
	];
	if($payment['status']==="success"){
		$return_data['payment']['paid_at']=$payment['created_at'];
		ok('Payment already successfully processed', $return_data, 200);
	}
	fail( "Payment failed. You can try again.", 402, [], $return_data);
}
if($order['status']!=="pending"){
	fail('Order already paid.', 409);
}
$stmt=$pdo->prepare("SELECT id, order_id, category_id, quantity FROM order_items WHERE order_id=:order_id");
$stmt->execute([':order_id'=>$orderId]);
$items=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare("SELECT id, name, price, quota, is_active FROM ticket_categories WHERE id= :id");
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM tickets WHERE category_id=:category_id AND status IN('issued', 'used')");

foreach($items as $item){
	$stmt->execute([':id'=>$item['category_id']]);
	$line=$stmt->fetch(PDO::FETCH_ASSOC);
	$countStmt->execute([':category_id'=>$item['category_id']]);
	$counted=(int)$countStmt->fetchColumn();
	$quota=(int)$line['quota'];
	if($item['quantity']>$quota-$counted){
		fail('There are not enough tickets left in the quota for this category to satisfy this order.', 409);
	}
}
$result=process_payment($order, $input);
if($result['status']==='failed'){
	$stmt=$pdo->prepare("INSERT INTO payments(order_id, reference, amount, method, status, provider_response) VALUES(:order_id, :reference, :amount, :method, :status, :provider_response)");
	$stmt->execute([':order_id'=>$orderId, ':reference'=>$reference, ':amount'=>$order['total_amount'], ':method'=>$method, ':status'=>$result['status'], ':provider_response'=>$result['provider_response']]);
	$failedPayment=[
		'reference'=>$reference,
		'status'=>$result['status'],
		'amount'=>$order['total_amount']
	];
	$failedOrder=[
		'id'=>$order['id'],
		'status'=>$order['status']
	];	
	$fail_data=[
		'payment'=>$failedPayment,
		'order'=>$failedOrder,
		'ticket_count'=>0
	];
	fail( "Payment failed. You can try again.", 402, [], $fail_data);
}
elseif($result['status']==='success'){
	try{
		$pdo->beginTransaction();
		$stmt=$pdo->prepare("SELECT id, user_id, status, total_amount FROM orders WHERE id=:id AND user_id=:user_id FOR UPDATE");
		$stmt->execute([':id'=>$orderId, ':user_id'=>$user_id]);
		$new_order=$stmt->fetch(PDO::FETCH_ASSOC);
		if(!$new_order){
			$pdo->rollBack();
			fail('Order not found.', 404);
		}
		if($new_order['status']!=="pending"){
			$pdo->rollBack();
			fail('Order already paid.', 409);
		}
		$stmt=$pdo->prepare("SELECT id, order_id, category_id, quantity FROM order_items WHERE order_id=:order_id");
		$stmt->execute([':order_id'=>$orderId]);
		$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
		usort($items, function($a, $b){
			return $a['category_id']<=> $b['category_id'];
		});
		$stmt=$pdo->prepare("SELECT id, name, price, quota, is_active FROM ticket_categories WHERE id= :id FOR UPDATE");
		$countStmt=$pdo->prepare("SELECT COUNT(*) FROM tickets WHERE category_id=:category_id AND status IN('issued', 'used') FOR UPDATE");
		foreach($items as $item){
			$stmt->execute([':id'=>$item['category_id']]);
			$line=$stmt->fetch(PDO::FETCH_ASSOC);
			if(!$line){
				$pdo->rollBack();
				fail('Invalid category', 409);
			}	
			$countStmt->execute([':category_id'=>$item['category_id']]);
			$counted=(int)$countStmt->fetchColumn();
			$quota=(int)$line['quota'];
			if((int)$item['quantity']>$quota-$counted){
				$pdo->rollBack();
				fail('There are not enough tickets left in the quota for this category to satisfy this order.', 409);
			}
		}
		$stmt=$pdo->prepare("INSERT INTO payments(order_id, reference, amount, method, status, provider_response) VALUES(:order_id, :reference, :amount, :method, :status, :provider_response)");
		$stmt->execute([':order_id'=>$orderId, ':reference'=>$reference, ':amount'=>$new_order['total_amount'], ':method'=>$method, ':status'=>$result['status'], ':provider_response'=>$result['provider_response']]);
		$payId=$pdo->lastInsertId();
		$stmt=$pdo->prepare("UPDATE orders SET status= :status WHERE id=:order_id");
		$stmt->execute([':status'=>'paid', ':order_id'=>$new_order['id']]);	
		issue_tickets($pdo, (int)$new_order['id']);
		
		
		$stmt=$pdo->prepare("SELECT created_at FROM payments WHERE :id=id");
		$stmt->execute([':id'=>$payId]);
		$payDate=$stmt->fetchColumn();
		$stmt=$pdo->prepare("SELECT COUNT(*) FROM tickets WHERE order_id= :order_id");
		$stmt->execute([':order_id'=>$new_order['id']]);
		$totalTickets=(int)$stmt->fetchColumn();
		$successfulPayment=[
			'reference'=>$reference,
			'status'=>$result['status'],
			'amount'=>$new_order['total_amount'],
			'paid_at'=>$payDate
		];
		$successfulOrder=[
			'id'=>$new_order['id'],
			'status'=>'paid'
		];	
		$success_data=[
			'payment'=>$successfulPayment,
			'order'=>$successfulOrder,
			'ticket_count'=>$totalTickets
		];
		
		$pdo->commit();
		
		ok('Payment successfully processed', $success_data, 200);
	}
	catch(Throwable $e){
		if($pdo->inTransaction()){
			$pdo->rollBack();
		}
		error_log($e->getMessage());
		try{
			$stmt=$pdo->prepare("INSERT INTO payments(order_id, reference, amount, method, status, provider_response) VALUES(:order_id, :reference, :amount, :method, :status, :provider_response)");
			$stmt->execute([':order_id'=>$orderId, ':reference'=>$reference, ':amount'=>$order['total_amount'], ':method'=>$method, ':status'=>'failed', ':provider_response'=>'An error occurred while processing payment']);
		}
		catch(Throwable $e2){
			error_log($e2->getMessage());
		}
		$failedPayment=[
			'reference'=>$reference,
			'status'=>'failed',
			'amount'=>$order['total_amount']
		];
		$failedOrder=[
			'id'=>$order['id'],
			'status'=>'pending'
		];	
		$fail_data=[
			'payment'=>$failedPayment,
			'order'=>$failedOrder,
			'ticket_count'=>0
		];
		//fail( "Payment failed. You can try again.", 402, [], $fail_data);
		
		fail('An error occurred while processing payment', 500, [], $fail_data);
	}	

}
?>
