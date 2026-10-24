<?php
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';


if($_SERVER['REQUEST_METHOD']=='GET'){
	$pdo=db();
	require_method('GET');
	$user=require_login();
	$user_id=(int)$user['id'];
	$orderId=filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
	if($orderId===false || $orderId===null || $orderId<1){
		fail('A valid order ID is needed.', 400, [['field'=>'id', 'message'=>'Invalid ID' ]]);
	}
	$stmt=$pdo->prepare("SELECT id, user_id, status, total_amount, created_at FROM orders WHERE id=:id AND user_id=:user_id");
	$stmt->execute([':id'=>$orderId, ':user_id'=>$user_id]);
	$base_order=$stmt->fetch(PDO::FETCH_ASSOC);
	if(!$base_order){
		fail("Order not found.", 404);
	}
	$stmt=$pdo->prepare("SELECT oi.category_id, tc.name AS category_name, oi.quantity, oi.unit_price, oi.line_total FROM order_items AS oi JOIN ticket_categories AS tc ON tc.id=oi.category_id WHERE oi.order_id=:order_id ORDER BY category_id");
	$stmt->execute([':order_id'=>$orderId]);
	$line_items=$stmt->fetchAll(PDO::FETCH_ASSOC);
	$order=['id'=>$orderId, 'status'=>$base_order['status'], 'total_amount'=>$base_order['total_amount'], 'lines'=>$line_items];
	ok("Order retrieved successfully.", ['order'=>$order], 200);
	
	
}



elseif($_SERVER['REQUEST_METHOD']=='POST'){
	require_method('POST');
	$user=require_login();
	$user_id=(int)$user['id'];
	$input=read_json();
	
	if(!array_key_exists('items', $input) || !is_array($input['items'])){
		fail('Invalid order request.', 400,[['field'=>'items', 'message'=>'The items must exist and be an array.']]);
	}
	$items=$input['items'];

	$pdo=db();
	$pricing=build_order_lines($pdo, $items);
	try{
		$pdo->beginTransaction();
		$stmt=$pdo->prepare(
			"INSERT INTO orders(user_id, total_amount, status) VALUES(:user_id, :total_amount, 'pending')"
		);
		$stmt->execute([':user_id'=>$user_id, ':total_amount'=>$pricing['total']]);
		$orderId=(int)$pdo->lastInsertId();
	
		$stmt=$pdo->prepare(
			"INSERT INTO order_items(order_id, category_id, quantity, unit_price, line_total) VALUES(:order_id, :category_id, :quantity, :unit_price, :line_total)"
		);
		foreach($pricing['lines'] as $item){
			$cat_id=$item['category_id'];
			$quant=$item['quantity'];
			$unit_price=$item['unit_price'];
			$line_tot=$item['line_total'];
			$stmt->execute([':order_id'=>$orderId, ':category_id'=>$cat_id, ':quantity'=>$quant, ':unit_price'=>$unit_price, ':line_total'=>$line_tot]);
		}
		$pdo->commit();
	}
	catch(Throwable $e){
		if($pdo->inTransaction()){
			$pdo->rollBack();
		}
		error_log($e->getMessage());
		fail('Unable to create order.', 500);
	}

	ok("Order created successfully.", ['order'=>['id'=>$orderId, 'status'=>'pending', 'total_amount'=>$pricing['total'], 'lines'=>$pricing['lines'] ]], 201);
	}
else{
	fail("Unsupported Method", 405);
}
?>