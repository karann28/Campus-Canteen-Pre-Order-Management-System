<?php
/**
 * Menu API Endpoint
 * Public: GET (Search, Filter, List available items)
 * Admin-Protected: POST, PUT, DELETE (Menu & Inventory Management)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Handle HTTP method spoofing if needed (e.g. via _method)
if ($method === 'POST' && !empty($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

try {
    switch ($method) {
        // =========================================================================
        // PUBLIC GET: Fetch categories and filtered menu items
        // =========================================================================
        case 'GET':
            // 1. Fetch all categories
            $catStmt = $pdo->query('SELECT id, name, slug, display_order FROM categories ORDER BY display_order ASC, id ASC');
            $categories = $catStmt->fetchAll();

            // 2. Build filtered menu query
            $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
            $category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
            $maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : 0.0;
            $adminView = isset($_GET['admin_view']) && $_GET['admin_view'] === '1' && isAdminLoggedIn();

            $sql = 'SELECT m.id, m.category_id, c.name AS category_name, c.slug AS category_slug,
                           m.name, m.description, m.price, m.prep_time_minutes, 
                           m.stock_qty, m.is_available, m.image_url, m.created_at, m.updated_at
                    FROM menu_items m
                    JOIN categories c ON m.category_id = c.id
                    WHERE 1=1';

            $params = [];

            // If not admin view, hide completely disabled items (is_available = 0)
            if (!$adminView) {
                $sql .= ' AND m.is_available = 1';
            }

            // Category filter (by id or slug)
            if ($category !== '' && $category !== 'all') {
                if (ctype_digit($category)) {
                    $sql .= ' AND m.category_id = ?';
                    $params[] = (int)$category;
                } else {
                    $sql .= ' AND (c.slug = ? OR c.name = ?)';
                    $params[] = $category;
                    $params[] = $category;
                }
            }

            // Search filter (keyword in name or description)
            if ($search !== '') {
                $sql .= ' AND (m.name LIKE ? OR m.description LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Max Price filter
            if ($maxPrice > 0) {
                $sql .= ' AND m.price <= ?';
                $params[] = $maxPrice;
            }

            $sql .= ' ORDER BY c.display_order ASC, m.name ASC';

            $itemStmt = $pdo->prepare($sql);
            $itemStmt->execute($params);
            $items = $itemStmt->fetchAll();

            // Normalize numeric types for clean JSON consumption
            foreach ($items as &$item) {
                $item['id'] = (int)$item['id'];
                $item['category_id'] = (int)$item['category_id'];
                $item['price'] = (float)$item['price'];
                $item['prep_time_minutes'] = (int)$item['prep_time_minutes'];
                $item['stock_qty'] = (int)$item['stock_qty'];
                $item['is_available'] = (bool)$item['is_available'];
            }
            unset($item);

            // Check store online/offline status
            $isStoreOnline = true;
            try {
                $settingStmt = $pdo->query("SELECT key_value FROM store_settings WHERE key_name = 'is_store_online'");
                $sRow = $settingStmt->fetch();
                if ($sRow && $sRow['key_value'] === '0') {
                    $isStoreOnline = false;
                }
            } catch (Throwable $e) {}

            echo json_encode([
                'success'         => true,
                'categories'      => $categories,
                'items'           => $items,
                'count'           => count($items),
                'is_store_online' => $isStoreOnline
            ], JSON_UNESCAPED_SLASHES);
            break;

        // =========================================================================
        // ADMIN PROTECTED POST: Add new menu item
        // =========================================================================
        case 'POST':
            requireAdminApi();
            requireCsrfToken();

            // Parse JSON body or regular POST
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                $data = $_POST;
            }

            $name = trim((string)($data['name'] ?? ''));
            $categoryId = (int)($data['category_id'] ?? 0);
            $description = trim((string)($data['description'] ?? ''));
            $price = isset($data['price']) ? (float)$data['price'] : 0.0;
            $prepTime = isset($data['prep_time_minutes']) ? (int)$data['prep_time_minutes'] : 10;
            $stockQty = isset($data['stock_qty']) ? (int)$data['stock_qty'] : 0;
            $isAvailable = isset($data['is_available']) ? (int)(bool)$data['is_available'] : 1;
            $imageUrl = trim((string)($data['image_url'] ?? ''));

            // Validation
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Item name must be between 2 and 100 characters.']);
                exit;
            }
            if ($categoryId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Valid category must be selected.']);
                exit;
            }
            if ($price <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Price must be greater than ₹0.00.']);
                exit;
            }
            if ($prepTime < 1 || $prepTime > 180) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Prep time must be between 1 and 180 minutes.']);
                exit;
            }
            if ($stockQty < 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Stock quantity cannot be negative.']);
                exit;
            }

            // Verify category exists
            $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
            $catCheck->execute([$categoryId]);
            if (!$catCheck->fetch()) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Selected category does not exist.']);
                exit;
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO menu_items (category_id, name, description, price, prep_time_minutes, stock_qty, is_available, image_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertStmt->execute([
                $categoryId,
                $name,
                $description !== '' ? $description : null,
                $price,
                $prepTime,
                $stockQty,
                $isAvailable,
                $imageUrl !== '' ? $imageUrl : null
            ]);

            $newId = (int)$pdo->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'New menu item added successfully!',
                'item_id' => $newId
            ]);
            break;

        // =========================================================================
        // ADMIN PROTECTED PUT: Update menu item (stock, price, availability, details)
        // =========================================================================
        case 'PUT':
            requireAdminApi();
            requireCsrfToken();

            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON payload received.']);
                exit;
            }

            $action = $data['action'] ?? 'full_update';

            // Global Store Online/Offline Toggle Action
            if ($action === 'toggle_store_status') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS store_settings (key_name VARCHAR(50) PRIMARY KEY, key_value VARCHAR(255) NOT NULL)");
                $current = $pdo->query("SELECT key_value FROM store_settings WHERE key_name = 'is_store_online'")->fetch();
                $newStatus = ($current && $current['key_value'] === '1') ? '0' : '1';
                $stmt = $pdo->prepare("INSERT INTO store_settings (key_name, key_value) VALUES ('is_store_online', ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                $stmt->execute([$newStatus]);
                echo json_encode([
                    'success'         => true,
                    'is_store_online' => ($newStatus === '1'),
                    'message'         => ($newStatus === '1') ? 'Store is now ONLINE (Accepting Orders).' : 'Store is now OFFLINE (Closed for Orders).'
                ]);
                exit;
            }

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Valid item ID is required.']);
                exit;
            }

            // Check if item exists
            $checkStmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = ?');
            $checkStmt->execute([$id]);
            $existing = $checkStmt->fetch();
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Item not found.']);
                exit;
            }

            // Quick On-The-Go Price Update Action
            if ($action === 'set_price') {
                $newPrice = (float)($data['price'] ?? 0);
                if ($newPrice <= 0) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Price must be greater than ₹0.00.']);
                    exit;
                }
                $update = $pdo->prepare('UPDATE menu_items SET price = ? WHERE id = ?');
                $update->execute([$newPrice, $id]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Price updated successfully.',
                    'price'   => $newPrice
                ]);
                exit;
            }

            // Support quick partial actions (e.g., toggle availability or stock increment)
            $action = $data['action'] ?? 'full_update';

            if ($action === 'toggle_availability') {
                $newStatus = empty($existing['is_available']) ? 1 : 0;
                $update = $pdo->prepare('UPDATE menu_items SET is_available = ? WHERE id = ?');
                $update->execute([$newStatus, $id]);
                echo json_encode([
                    'success'      => true,
                    'message'      => 'Item availability updated.',
                    'is_available' => (bool)$newStatus
                ]);
                exit;
            }

            if ($action === 'adjust_stock') {
                $delta = (int)($data['stock_delta'] ?? 0);
                $newStock = max(0, (int)$existing['stock_qty'] + $delta);
                $update = $pdo->prepare('UPDATE menu_items SET stock_qty = ? WHERE id = ?');
                $update->execute([$newStock, $id]);
                echo json_encode([
                    'success'   => true,
                    'message'   => 'Stock updated successfully.',
                    'stock_qty' => $newStock
                ]);
                exit;
            }

            if ($action === 'set_stock') {
                $newStock = max(0, (int)($data['stock_qty'] ?? 0));
                $update = $pdo->prepare('UPDATE menu_items SET stock_qty = ? WHERE id = ?');
                $update->execute([$newStock, $id]);
                echo json_encode([
                    'success'   => true,
                    'message'   => 'Stock quantity set to ' . $newStock,
                    'stock_qty' => $newStock
                ]);
                exit;
            }

            // Standard full update
            $name = isset($data['name']) ? trim((string)$data['name']) : $existing['name'];
            $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : (int)$existing['category_id'];
            $description = isset($data['description']) ? trim((string)$data['description']) : $existing['description'];
            $price = isset($data['price']) ? (float)$data['price'] : (float)$existing['price'];
            $prepTime = isset($data['prep_time_minutes']) ? (int)$data['prep_time_minutes'] : (int)$existing['prep_time_minutes'];
            $stockQty = isset($data['stock_qty']) ? max(0, (int)$data['stock_qty']) : (int)$existing['stock_qty'];
            $isAvailable = isset($data['is_available']) ? (int)(bool)$data['is_available'] : (int)$existing['is_available'];
            $imageUrl = isset($data['image_url']) ? trim((string)$data['image_url']) : $existing['image_url'];

            if ($price <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Price must be greater than zero.']);
                exit;
            }

            $updateStmt = $pdo->prepare(
                'UPDATE menu_items 
                 SET name = ?, category_id = ?, description = ?, price = ?, prep_time_minutes = ?, stock_qty = ?, is_available = ?, image_url = ?
                 WHERE id = ?'
            );
            $updateStmt->execute([
                $name,
                $categoryId,
                $description !== '' ? $description : null,
                $price,
                $prepTime,
                $stockQty,
                $isAvailable,
                $imageUrl !== '' ? $imageUrl : null,
                $id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Menu item updated successfully!'
            ]);
            break;

        // =========================================================================
        // ADMIN PROTECTED DELETE: Remove or soft-retire menu item
        // =========================================================================
        case 'DELETE':
            requireAdminApi();
            requireCsrfToken();

            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));

            if ($id <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Valid item ID required for deletion.']);
                exit;
            }

            try {
                // Try hard delete first
                $delStmt = $pdo->prepare('DELETE FROM menu_items WHERE id = ?');
                $delStmt->execute([$id]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Menu item permanently removed from database.'
                ]);
            } catch (PDOException $ex) {
                // If foreign key constraint blocks deletion due to historical orders, soft-retire
                if ($ex->getCode() === '23000') {
                    $softStmt = $pdo->prepare('UPDATE menu_items SET is_available = 0, stock_qty = 0 WHERE id = ?');
                    $softStmt->execute([$id]);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Item has past order history: disabled and marked unavailable instead of deleting.'
                    ]);
                } else {
                    throw $ex;
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            break;
    }
} catch (Throwable $e) {
    error_log('[MENU API EXCEPTION] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'An unexpected server error occurred. Please try again.'
    ]);
}

