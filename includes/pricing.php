<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';


function naira_to_kobo(string $naira): int
{
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $naira)) {
        throw new InvalidArgumentException(
            'Invalid monetary amount.'
        );
    }

    [$whole, $fraction] = array_pad(
        explode('.', $naira, 2),
        2,
        '0'
    );

    $fraction = str_pad($fraction, 2, '0');

    return ((int) $whole * 100) + (int) $fraction;
}


function kobo_to_naira(int $kobo): string
{
    $naira = intdiv($kobo, 100);
    $remainingKobo = $kobo % 100;

    return $naira . '.' .
        str_pad(
            (string) $remainingKobo,
            2,
            '0',
            STR_PAD_LEFT
        );
}

function build_order_lines(PDO $pdo, array $items): array{
	//Check if items exist as an array
	//Validate ids and quantities
	//Reject duplicate categories
	if(empty($items)){
		fail('There must be at least one item in the order.', 400, [['field'=>'items', 'message'=>'Select at least one ticket category.' ]]);
	}
	$lines=[];
	$total=0;
	$totalQuantity=0;
	$procCat=[];

	$stmt=$pdo->prepare(
		"SELECT id, name, price, quota, is_active FROM ticket_categories WHERE id= :id"
	);
	$countStmt=$pdo->prepare(
		"SELECT COUNT(*) FROM tickets WHERE category_id=:category_id AND status IN('issued', 'used')"
	);
	foreach($items as $item){
		if(!is_array($item) || !isset($item['category_id'], $item['quantity'])){
			fail('Invalid order item.', 400,  [['field'=>'category_id', 'message'=>'Each item needs a category_id.' ], ['field'=>'quantity', 'message'=>'Each item needs a quantity.' ]]);
		}
		$category_id=filter_var($item['category_id'], FILTER_VALIDATE_INT);
		$quantity=filter_var($item['quantity'], FILTER_VALIDATE_INT);
		if($category_id===false || $category_id<1){
			fail('Invalid category ID.', 400, [['field'=>'category_id', 'message'=>'Category ID must be a positive whole number' ]]);
		}
		if($quantity===false || $quantity>MAX_TICKETS_PER_ORDER  || $quantity<1){
			fail('Invalid quantity.', 400, [['field'=>'quantity', 'message'=>'Quantity must be a whole number from 1 to '.MAX_TICKETS_PER_ORDER.'.' ]]);
		}
		if(isset($procCat[$category_id])){
			fail('A category can only appear once per order.', 400, [['field'=>'category_id', 'message'=>'Category '.$category_id.' appears more than once.' ]]);
		}
		$procCat[$category_id]=$item;
		$totalQuantity+=$quantity;
		if($totalQuantity>MAX_TICKETS_PER_ORDER){
			fail('There are too many items in this order.', 400, [['field'=>'quantity', 'message'=>'Total quantity of tickets in order must be at most '.MAX_TICKETS_PER_ORDER.'.' ]]);
		}
		$stmt->execute([':id' => $category_id]);
		$line=$stmt->fetch(PDO::FETCH_ASSOC);
		if(!$line){
			fail('Invalid category ID.', 404, [['field'=>'category_id', 'message'=>'Category '.$category_id.' does not exist.' ]]);
		}
		if((int)$line['is_active']!==1){
			fail('This category is inactive.', 409, [['field'=>'category_id', 'message'=>$line['name'].' is not available.' ]]);
		}
		$countStmt->execute([':category_id'=>$category_id]);
		$counted=(int)$countStmt->fetchColumn();
		$quota=(int)$line['quota'];
		if($quantity>$quota-$counted){
			fail("The ".$line['name']." category is sold out", 409);
		}
		$price=naira_to_kobo((string)$line['price']);
		$line_total=$price*$quantity;
		$total+=$line_total;
		$lines[]=[
			'category_id'=>(int)$category_id,
			'category_name'=>$line['name'],
			'quantity'=> $quantity,
			'unit_price'=>kobo_to_naira($price),
			'line_total'=>kobo_to_naira($line_total)
		];
	}
	return [
		'lines'=>$lines,
		'total'=>kobo_to_naira($total)
		];
}
?>