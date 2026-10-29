<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';

final class PricingCalculator {
    public function __construct(private PDO $pdo) {}

    public function nairaToKobo(string $naira): int {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $naira)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $naira, 2), 2, '0');
        $fraction = str_pad($fraction, 2, '0');

        return ((int)$whole * 100) + (int)$fraction;
    }

    public function koboToNaira(int $kobo): string {
        $naira = intdiv($kobo, 100);
        $remainingKobo = $kobo % 100;

        return $naira . '.' . str_pad((string)$remainingKobo, 2, '0', STR_PAD_LEFT);
    }

    public function buildOrderLines(array $items): array {
        if (empty($items)) {
            fail('There must be at least one item in the order.', 400, [
                ['field' => 'items', 'message' => 'Select at least one ticket category.']
            ]);
        }

        $lines = [];
        $total = 0;
        $totalQuantity = 0;
        $procCat = [];

        $stmt = $this->pdo->prepare(
            'SELECT id, name, price, quota, is_active FROM ticket_categories WHERE id = :id'
        );
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tickets WHERE category_id = :category_id AND status IN ('issued', 'used')"
        );

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['category_id'], $item['quantity'])) {
                fail('Invalid order item.', 400, [
                    ['field' => 'category_id', 'message' => 'Each item needs a category_id.'],
                    ['field' => 'quantity', 'message' => 'Each item needs a quantity.']
                ]);
            }

            $categoryId = filter_var($item['category_id'], FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);

            if ($categoryId === false || $categoryId < 1) {
                fail('Invalid category ID.', 400, [
                    ['field' => 'category_id', 'message' => 'Category ID must be a positive whole number']
                ]);
            }

            if ($quantity === false || $quantity > MAX_TICKETS_PER_ORDER || $quantity < 1) {
                fail('Invalid quantity.', 400, [
                    ['field' => 'quantity', 'message' => 'Quantity must be a whole number from 1 to ' . MAX_TICKETS_PER_ORDER . '.']
                ]);
            }

            if (isset($procCat[$categoryId])) {
                fail('A category can only appear once per order.', 400, [
                    ['field' => 'category_id', 'message' => 'Category ' . $categoryId . ' appears more than once.']
                ]);
            }

            $procCat[$categoryId] = $item;
            $totalQuantity += $quantity;

            if ($totalQuantity > MAX_TICKETS_PER_ORDER) {
                fail('There are too many items in this order.', 400, [
                    ['field' => 'quantity', 'message' => 'Total quantity of tickets in order must be at most ' . MAX_TICKETS_PER_ORDER . '.']
                ]);
            }

            $stmt->execute([':id' => $categoryId]);
            $line = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$line) {
                fail('Invalid category ID.', 404, [
                    ['field' => 'category_id', 'message' => 'Category ' . $categoryId . ' does not exist.']
                ]);
            }

            if ((int)$line['is_active'] !== 1) {
                fail('This category is inactive.', 409, [
                    ['field' => 'category_id', 'message' => $line['name'] . ' is not available.']
                ]);
            }

            $countStmt->execute([':category_id' => $categoryId]);
            $counted = (int)$countStmt->fetchColumn();
            $quota = (int)$line['quota'];

            if ($quantity > $quota - $counted) {
                fail('The ' . $line['name'] . ' category is sold out', 409);
            }

            $price = $this->nairaToKobo((string)$line['price']);
            $lineTotal = $price * $quantity;
            $total += $lineTotal;

            $lines[] = [
                'category_id'   => (int)$categoryId,
                'category_name' => $line['name'],
                'quantity'      => $quantity,
                'unit_price'    => $this->koboToNaira($price),
                'line_total'    => $this->koboToNaira($lineTotal)
            ];
        }

        return [
            'lines' => $lines,
            'total' => $this->koboToNaira($total)
        ];
    }
}

function naira_to_kobo(string $naira): int {
    return (new PricingCalculator(db()))->nairaToKobo($naira);
}

function kobo_to_naira(int $kobo): string {
    return (new PricingCalculator(db()))->koboToNaira($kobo);
}

function build_order_lines(PDO $pdo, array $items): array {
    return (new PricingCalculator($pdo))->buildOrderLines($items);
}