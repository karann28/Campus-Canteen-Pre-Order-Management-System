<?php
/**
 * Orders & Token API Endpoint
 * Public: 
 *   - GET (Public Live Token Board ONLY - strictly non-sensitive)
 *   - POST (Create Pre-Order with server-calculated pricing & atomic inventory locks)
 * Admin-Protected:
 *   - GET (Kitchen Live Queue & Summary)
 *   - PUT (Advance status, Verify UPI payment, Cancel order / items with stock restoration)
 *   - DELETE (Archive / Delete Order)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && !empty($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

try {
    switch ($method) {
        // =========================================================================
        // GET: Token Board (Public) OR Kitchen Live Queue (Admin Only)
        // =========================================================================
        case 'GET':
            $action = $_GET['action'] ?? 'token_board';

            // ---------------------------------------------------------------------
            // 1. ADMIN QUEUE (Kitchen Dashboard) - Protected
            // ---------------------------------------------------------------------
            if ($action === 'admin_queue') {
                requireAdminApi();

                $statusFilter = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
                $todayOnly = isset($_GET['today_only']) ? (bool)$_GET['today_only'] : false;

                $sql = 'SELECT id, token_number, student_name, roll_number, pickup_time, pickup_date,
                               payment_method, payment_status, utr_number, total_amount, status, created_at, updated_at
                        FROM orders
                        WHERE 1=1';
                $params = [];

                if ($todayOnly) {
                    $sql .= ' AND pickup_date = CURDATE()';
                }

                if ($statusFilter !== 'ALL' && in_array($statusFilter, ['PREPARING', 'READY', 'COMPLETED', 'CANCELLED'], true)) {
                    $sql .= ' AND status = ?';
                    $params[] = $statusFilter;
                }

                // Order by active first, then closest pickup time
                $sql .= " ORDER BY 
                            CASE status 
                              WHEN 'PREPARING' THEN 1 
                              WHEN 'READY' THEN 2 
                              WHEN 'COMPLETED' THEN 3 
                              WHEN 'CANCELLED' THEN 4 
                            END, 
                            pickup_time ASC, id DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $orders = $stmt->fetchAll();

                // Fetch itemized breakdown for each order
                if (!empty($orders)) {
                    $orderIds = array_column($orders, 'id');
                    $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));

                    $itemStmt = $pdo->prepare(
                        "SELECT id, order_id, menu_item_id, item_name, unit_price, quantity, subtotal, status
                         FROM order_items
                         WHERE order_id IN ($inPlaceholders)
                         ORDER BY id ASC"
                    );
                    $itemStmt->execute($orderIds);
                    $allItems = $itemStmt->fetchAll();

                    $itemsByOrder = [];
                    foreach ($allItems as $oi) {
                        $itemsByOrder[(int)$oi['order_id']][] = [
                            'id'           => (int)$oi['id'],
                            'menu_item_id' => (int)$oi['menu_item_id'],
                            'item_name'    => $oi['item_name'],
                            'unit_price'   => (float)$oi['unit_price'],
                            'quantity'     => (int)$oi['quantity'],
                            'subtotal'     => (float)$oi['subtotal'],
                            'status'       => $oi['status']
                        ];
                    }

                    foreach ($orders as &$ord) {
                        $ord['id'] = (int)$ord['id'];
                        $ord['total_amount'] = (float)$ord['total_amount'];
                        $ord['items'] = $itemsByOrder[$ord['id']] ?? [];
                    }
                    unset($ord);
                }

                // Calculate summary dashboard statistics for today
                $statsStmt = $pdo->query(
                    "SELECT 
                        SUM(CASE WHEN status = 'PREPARING' THEN 1 ELSE 0 END) AS prep_count,
                        SUM(CASE WHEN status = 'READY' THEN 1 ELSE 0 END) AS ready_count,
                        SUM(CASE WHEN status = 'COMPLETED' AND pickup_date = CURDATE() THEN 1 ELSE 0 END) AS completed_today,
                        SUM(CASE WHEN payment_status = 'PAID' AND pickup_date = CURDATE() THEN total_amount ELSE 0 END) AS revenue_today
                     FROM orders"
                );
                $stats = $statsStmt->fetch();

                echo json_encode([
                    'success' => true,
                    'orders'  => $orders,
                    'stats'   => [
                        'preparing'       => (int)($stats['prep_count'] ?? 0),
                        'ready'           => (int)($stats['ready_count'] ?? 0),
                        'completed_today' => (int)($stats['completed_today'] ?? 0),
                        'revenue_today'   => (float)($stats['revenue_today'] ?? 0.0)
                    ]
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }

            // ---------------------------------------------------------------------
            // 2. PUBLIC LIVE TOKEN BOARD (Strictly Non-Sensitive Data Only)
            // ---------------------------------------------------------------------
            // Select ONLY token_number, status, pickup_time
            // Filter strictly for active orders (PREPARING, READY) today
            $stmt = $pdo->query(
                "SELECT token_number, status, pickup_time, created_at
                 FROM orders
                 WHERE status IN ('PREPARING', 'READY')
                   AND (pickup_date = CURDATE() OR created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR))
                 ORDER BY pickup_time ASC, id ASC"
            );
            $activeOrders = $stmt->fetchAll();

            $preparing = [];
            $ready = [];

            foreach ($activeOrders as $row) {
                $tokenInfo = [
                    'token_number' => $row['token_number'],
                    'status'       => $row['status'],
                    'pickup_time'  => substr($row['pickup_time'], 0, 5) // HH:MM
                ];
                if ($row['status'] === 'READY') {
                    $ready[] = $tokenInfo;
                } else {
                    $preparing[] = $tokenInfo;
                }
            }

            echo json_encode([
                'success'   => true,
                'preparing' => $preparing,
                'ready'     => $ready,
                'server_time' => date('H:i:s')
            ], JSON_UNESCAPED_SLASHES);
            break;

        // =========================================================================
        // PUBLIC POST: Create Order (Zero Client-Side Price Trust + Atomic Lock)
        // =========================================================================
        case 'POST':
            // Verify CSRF Protection
            requireCsrfToken();

            // Check if store is online
            try {
                $settingStmt = $pdo->query("SELECT key_value FROM store_settings WHERE key_name = 'is_store_online'");
                $sRow = $settingStmt->fetch();
                if ($sRow && $sRow['key_value'] === '0') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Sorry, the canteen store is currently offline and not accepting pre-orders.']);
                    exit;
                }
            } catch (Throwable $e) {}

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            if (!is_array($data)) {
                $data = $_POST;
            }

            // 1. Sanitize & Validate Student Details
            $studentName = trim((string)($data['student_name'] ?? ''));
            $rollNumber  = trim((string)($data['roll_number'] ?? ''));
            $pickupTime  = trim((string)($data['pickup_time'] ?? ''));
            $paymentMethod = strtoupper(trim((string)($data['payment_method'] ?? 'COUNTER')));
            $utrNumber   = trim((string)($data['utr_number'] ?? ''));
            $items       = $data['items'] ?? [];

            if (mb_strlen($studentName) < 2 || mb_strlen($studentName) > 100) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Please provide a valid Student Name (2 to 100 characters).']);
                exit;
            }

            if (mb_strlen($rollNumber) < 2 || mb_strlen($rollNumber) > 50) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Please provide a valid Student Roll Number / ID.']);
                exit;
            }

            if (!in_array($paymentMethod, ['UPI', 'COUNTER'], true)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Invalid payment method selected.']);
                exit;
            }

            // 2. Validate UPI UTR if UPI Payment Selected
            if ($paymentMethod === 'UPI') {
                if (!preg_match('/^[0-9]{12}$/', $utrNumber)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Valid 12-digit UPI UTR / Transaction Reference Number is mandatory.']);
                    exit;
                }

                // Check for duplicate UTR reuse across orders
                $utrCheck = $pdo->prepare('SELECT id FROM orders WHERE utr_number = ? LIMIT 1');
                $utrCheck->execute([$utrNumber]);
                if ($utrCheck->fetch()) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'This UPI UTR number has already been submitted for a previous order.']);
                    exit;
                }
            } else {
                $utrNumber = null;
            }

            // 3. Automatically Generate or Validate Estimated Pickup Time
            if ($pickupTime === '' || !preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $pickupTime)) {
                $pickupTime = date('H:i', time() + (15 * 60));
            }

            $todayDate = date('Y-m-d');

            // 4. Validate Cart Items Array
            if (!is_array($items) || empty($items)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Your cart is empty. Please select at least one menu item.']);
                exit;
            }

            // Consolidate duplicate menu item IDs in submission
            $normalizedItems = [];
            foreach ($items as $item) {
                $mId = (int)($item['menu_item_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);
                if ($mId <= 0 || $qty <= 0) {
                    continue;
                }
                $normalizedItems[$mId] = ($normalizedItems[$mId] ?? 0) + $qty;
            }

            if (empty($normalizedItems)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'No valid item quantities found in request.']);
                exit;
            }

            // =====================================================================
            // ATOMIC TRANSACTION: Zero Price Trust + Inventory Row Locks (FOR UPDATE)
            // =====================================================================
            $pdo->beginTransaction();

            try {
                $computedTotal = 0.00;
                $processedItems = [];

                $lockStmt = $pdo->prepare(
                    'SELECT id, name, price, stock_qty, is_available 
                     FROM menu_items 
                     WHERE id = ? 
                     FOR UPDATE'
                );

                $stockUpdateStmt = $pdo->prepare(
                    'UPDATE menu_items 
                     SET stock_qty = stock_qty - ? 
                     WHERE id = ?'
                );

                foreach ($normalizedItems as $menuId => $quantity) {
                    $lockStmt->execute([$menuId]);
                    $menuRow = $lockStmt->fetch();

                    if (!$menuRow) {
                        throw new RuntimeException("Item ID #$menuId was not found on the menu.");
                    }

                    if (empty($menuRow['is_available'])) {
                        throw new RuntimeException("Sorry, '{$menuRow['name']}' is currently marked unavailable.");
                    }

                    $currentStock = (int)$menuRow['stock_qty'];
                    if ($currentStock < $quantity) {
                        throw new RuntimeException(
                            "Insufficient stock for '{$menuRow['name']}'. Only {$currentStock} remaining, but {$quantity} requested."
                        );
                    }

                    // Strict server-side price computation
                    $unitPrice = (float)$menuRow['price'];
                    $itemSubtotal = round($unitPrice * $quantity, 2);
                    $computedTotal += $itemSubtotal;

                    // Decrement stock in MySQL
                    $stockUpdateStmt->execute([$quantity, $menuId]);

                    $processedItems[] = [
                        'menu_item_id' => $menuId,
                        'item_name'    => $menuRow['name'],
                        'unit_price'   => $unitPrice,
                        'quantity'     => $quantity,
                        'subtotal'     => $itemSubtotal
                    ];
                }

                // Generate Sequential Token Number for today
                $tokenCountStmt = $pdo->prepare(
                    'SELECT COUNT(*) AS today_orders FROM orders WHERE pickup_date = ?'
                );
                $tokenCountStmt->execute([$todayDate]);
                $countRow = $tokenCountStmt->fetch();
                $seqNumber = (int)($countRow['today_orders'] ?? 0) + 101;
                $tokenNumber = 'TK-' . $seqNumber;

                // Handle token uniqueness collision edge case
                $uniqueCheck = $pdo->prepare('SELECT id FROM orders WHERE token_number = ?');
                $uniqueCheck->execute([$tokenNumber]);
                if ($uniqueCheck->fetch()) {
                    $tokenNumber = 'TK-' . $seqNumber . '-' . strtoupper(bin2hex(random_bytes(1)));
                }

                // Initial payment status
                $paymentStatus = ($paymentMethod === 'UPI') ? 'PENDING_VERIFICATION' : 'PAY_ON_PICKUP';

                // Insert Master Order
                $orderStmt = $pdo->prepare(
                    'INSERT INTO orders (
                        token_number, student_name, roll_number, pickup_time, pickup_date,
                        payment_method, payment_status, utr_number, total_amount, status
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $orderStmt->execute([
                    $tokenNumber,
                    $studentName,
                    $rollNumber,
                    $pickupTime . ':00',
                    $todayDate,
                    $paymentMethod,
                    $paymentStatus,
                    $utrNumber,
                    $computedTotal,
                    'PREPARING'
                ]);

                $orderId = (int)$pdo->lastInsertId();

                // Insert Itemized Breakdown
                $itemInsertStmt = $pdo->prepare(
                    'INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity, subtotal, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($processedItems as $pItem) {
                    $itemInsertStmt->execute([
                        $orderId,
                        $pItem['menu_item_id'],
                        $pItem['item_name'],
                        $pItem['unit_price'],
                        $pItem['quantity'],
                        $pItem['subtotal'],
                        'ACTIVE'
                    ]);
                }

                // Commit the atomic transaction
                $pdo->commit();

                http_response_code(201);
                echo json_encode([
                    'success'        => true,
                    'message'        => 'Order placed successfully! Keep your token number handy.',
                    'order_id'       => $orderId,
                    'token_number'   => $tokenNumber,
                    'total_amount'   => $computedTotal,
                    'pickup_time'    => $pickupTime,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus
                ], JSON_UNESCAPED_SLASHES);

            } catch (Throwable $txEx) {
                // Roll back atomic inventory transaction
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => $txEx->getMessage()
                ]);
            }
            break;

        // =========================================================================
        // ADMIN PROTECTED PUT: Order & Item Status Workflow + Stock Restoration
        // =========================================================================
        case 'PUT':
            requireAdminApi();
            requireCsrfToken();

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON input received.']);
                exit;
            }

            $action = $data['action'] ?? 'update_status';
            $orderId = (int)($data['order_id'] ?? 0);

            if ($orderId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Valid order ID is required.']);
                exit;
            }

            // Verify order exists
            $ordStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $ordStmt->execute([$orderId]);
            $order = $ordStmt->fetch();
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found.']);
                exit;
            }

            // ---------------------------------------------------------------------
            // Action: Advance Order Status (PREPARING -> READY -> COMPLETED)
            // ---------------------------------------------------------------------
            if ($action === 'advance_status') {
                $newStatus = strtoupper(trim((string)($data['new_status'] ?? '')));
                if (!in_array($newStatus, ['PREPARING', 'READY', 'COMPLETED', 'CANCELLED'], true)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Invalid order status.']);
                    exit;
                }

                $update = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
                $update->execute([$newStatus, $orderId]);

                echo json_encode([
                    'success'    => true,
                    'message'    => "Order {$order['token_number']} marked as {$newStatus}.",
                    'new_status' => $newStatus
                ]);
                exit;
            }

            // ---------------------------------------------------------------------
            // Action: Verify Payment (Mark UPI / Counter as PAID)
            // ---------------------------------------------------------------------
            if ($action === 'verify_payment') {
                $newPayStatus = strtoupper(trim((string)($data['payment_status'] ?? 'PAID')));
                $update = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
                $update->execute([$newPayStatus, $orderId]);

                echo json_encode([
                    'success'        => true,
                    'message'        => "Payment for {$order['token_number']} updated to {$newPayStatus}.",
                    'payment_status' => $newPayStatus
                ]);
                exit;
            }

            // ---------------------------------------------------------------------
            // Action: Cancel Entire Order & Atomically Restore Inventory Stock
            // ---------------------------------------------------------------------
            if ($action === 'cancel_order') {
                if ($order['status'] === 'CANCELLED') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'This order is already cancelled.']);
                    exit;
                }

                $pdo->beginTransaction();
                try {
                    // 1. Fetch all ACTIVE items belonging to this order
                    $itemsStmt = $pdo->prepare(
                        "SELECT id, menu_item_id, quantity 
                         FROM order_items 
                         WHERE order_id = ? AND status = 'ACTIVE' 
                         FOR UPDATE"
                    );
                    $itemsStmt->execute([$orderId]);
                    $activeItems = $itemsStmt->fetchAll();

                    // 2. Restore stock for each item
                    $restockStmt = $pdo->prepare(
                        'UPDATE menu_items SET stock_qty = stock_qty + ? WHERE id = ?'
                    );
                    foreach ($activeItems as $ai) {
                        $restockStmt->execute([(int)$ai['quantity'], (int)$ai['menu_item_id']]);
                    }

                    // 3. Mark items as CANCELLED
                    $markItems = $pdo->prepare("UPDATE order_items SET status = 'CANCELLED' WHERE order_id = ?");
                    $markItems->execute([$orderId]);

                    // 4. Mark order as CANCELLED and payment status as CANCELLED
                    $markOrder = $pdo->prepare("UPDATE orders SET status = 'CANCELLED', payment_status = 'CANCELLED' WHERE id = ?");
                    $markOrder->execute([$orderId]);

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => "Order {$order['token_number']} has been cancelled and stock quantities were successfully restored."
                    ]);
                    exit;
                } catch (Throwable $cEx) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $cEx;
                }
            }

            // ---------------------------------------------------------------------
            // Action: Cancel Individual Item from Order & Restock
            // ---------------------------------------------------------------------
            if ($action === 'cancel_item') {
                $itemId = (int)($data['order_item_id'] ?? 0);
                if ($itemId <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Valid order item ID is required.']);
                    exit;
                }

                $pdo->beginTransaction();
                try {
                    $itemStmt = $pdo->prepare(
                        'SELECT * FROM order_items WHERE id = ? AND order_id = ? FOR UPDATE'
                    );
                    $itemStmt->execute([$itemId, $orderId]);
                    $orderItem = $itemStmt->fetch();

                    if (!$orderItem) {
                        throw new RuntimeException('Item not found in specified order.');
                    }
                    if ($orderItem['status'] === 'CANCELLED') {
                        throw new RuntimeException('This item has already been cancelled.');
                    }

                    $qty = (int)$orderItem['quantity'];
                    $subtotal = (float)$orderItem['subtotal'];
                    $menuId = (int)$orderItem['menu_item_id'];

                    // 1. Restore stock to menu item
                    $restock = $pdo->prepare('UPDATE menu_items SET stock_qty = stock_qty + ? WHERE id = ?');
                    $restock->execute([$qty, $menuId]);

                    // 2. Mark this order item as CANCELLED
                    $markItem = $pdo->prepare("UPDATE order_items SET status = 'CANCELLED' WHERE id = ?");
                    $markItem->execute([$itemId]);

                    // 3. Deduct from master order total_amount
                    $newTotal = max(0.00, (float)$order['total_amount'] - $subtotal);
                    $updateTotal = $pdo->prepare('UPDATE orders SET total_amount = ? WHERE id = ?');
                    $updateTotal->execute([$newTotal, $orderId]);

                    $pdo->commit();

                    echo json_encode([
                        'success'   => true,
                        'message'   => "Item '{$orderItem['item_name']}' cancelled, refunded ₹{$subtotal}, and {$qty} restored to inventory.",
                        'new_total' => $newTotal
                    ]);
                    exit;
                } catch (Throwable $iEx) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $iEx->getMessage()]);
                    exit;
                }
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unsupported action requested.']);
            break;

        // =========================================================================
        // ADMIN PROTECTED DELETE: Hard remove completed / old cancelled orders
        // =========================================================================
        case 'DELETE':
            requireAdminApi();
            requireCsrfToken();

            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = (int)($data['order_id'] ?? ($_GET['id'] ?? 0));

            if ($orderId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Valid order ID required.']);
                exit;
            }

            $delStmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
            $delStmt->execute([$orderId]);

            echo json_encode([
                'success' => true,
                'message' => 'Order record deleted.'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            break;
    }
} catch (Throwable $e) {
    error_log('[ORDERS API EXCEPTION] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server encountered an unexpected error processing order request.'
    ]);
}

