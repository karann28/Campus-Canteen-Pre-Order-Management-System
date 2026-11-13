<?php
/**
 * Protected Kitchen & Staff Administration Portal
 * Live Order Dispatch Queue, UPI Verification, Stock Inventory & Menu Manager
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Server-side authentication guard: Redirects to /admin-login.php if unauthenticated
requireAdmin();

$adminName = $_SESSION['admin_name'] ?? 'Kitchen Staff';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';
$adminRole = $_SESSION['admin_role'] ?? 'staff';
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title>Kitchen Management & Order Queue | Campus Bistro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Kitchen Portal Dedicated Styles */
        .kitchen-app {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
        }
        .kitchen-header {
            background: #1E293B;
            color: #FFF;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: var(--shadow-md);
        }
        .kitchen-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .kitchen-brand .logo-icon {
            font-size: 1.8rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.35rem 0.6rem;
            border-radius: var(--radius-md);
        }
        .kitchen-brand h1 {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0;
            color: #FFF;
            letter-spacing: -0.02em;
        }
        .kitchen-brand span {
            font-size: 0.75rem;
            background: var(--color-orange);
            color: #FFF;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .kitchen-user-info {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .clock-display {
            font-family: monospace;
            background: rgba(0, 0, 0, 0.3);
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            color: #F8FAFC;
        }
        .staff-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #CBD5E1;
        }
        .staff-badge strong {
            color: #FFF;
        }
        .btn-logout {
            background: rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 0.45rem 0.9rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-logout:hover {
            background: #EF4444;
            color: #FFF;
            border-color: #EF4444;
        }

        /* Stats Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-card .label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-card .value {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-top: 0.25rem;
        }
        .stat-card.prep { border-left: 4px solid var(--color-amber); }
        .stat-card.ready { border-left: 4px solid var(--color-green); }
        .stat-card.completed { border-left: 4px solid #3B82F6; }
        .stat-card.revenue { border-left: 4px solid var(--color-orange); }

        /* Main Workspace Container */
        .kitchen-content {
            flex: 1;
            padding: 0 1.5rem 2rem 1.5rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        /* Tabs Nav */
        .kitchen-tabs {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 0.85rem 1.25rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .tab-btn:hover {
            color: var(--text-primary);
        }
        .tab-btn.active {
            color: var(--color-orange);
            border-bottom-color: var(--color-orange);
        }
        .tab-btn .badge {
            background: #E2E8F0;
            color: var(--text-primary);
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
        }
        .tab-btn.active .badge {
            background: var(--color-orange);
            color: #FFF;
        }

        /* Filter Controls */
        .queue-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .status-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 0.45rem 0.85rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .filter-btn:hover {
            border-color: var(--text-muted);
            color: var(--text-primary);
        }
        .filter-btn.active {
            background: var(--text-primary);
            color: #FFF;
            border-color: var(--text-primary);
        }
        .refresh-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 0.45rem 0.85rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .refresh-btn:hover {
            background: #F1F5F9;
        }

        /* Order Cards Grid */
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.25rem;
        }
        .order-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .order-card:hover {
            box-shadow: var(--shadow-md);
        }
        .order-card.status-PREPARING {
            border-top: 5px solid var(--color-amber);
        }
        .order-card.status-READY {
            border-top: 5px solid var(--color-green);
        }
        .order-card.status-COMPLETED {
            border-top: 5px solid #94A3B8;
            opacity: 0.85;
        }
        .order-card.status-CANCELLED {
            border-top: 5px solid var(--color-red);
            opacity: 0.7;
        }

        .order-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .token-pill {
            font-size: 1.2rem;
            font-weight: 800;
            font-family: monospace;
            background: #1E293B;
            color: #FFF;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius-md);
            letter-spacing: 0.05em;
        }
        .status-badge {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
        }
        .status-badge.PREPARING {
            background: #FEF3C7;
            color: #92400E;
        }
        .status-badge.READY {
            background: #D1FAE5;
            color: #065F46;
        }
        .status-badge.COMPLETED {
            background: #E2E8F0;
            color: #475569;
        }
        .status-badge.CANCELLED {
            background: #FEE2E2;
            color: #991B1B;
        }

        .student-details {
            background: #F8FAFC;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .student-details .name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .student-details .sub {
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        /* Items breakdown list */
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .order-items-table th {
            text-align: left;
            padding: 0.4rem 0.2rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        .order-items-table td {
            padding: 0.5rem 0.2rem;
            border-bottom: 1px dashed #F1F5F9;
        }
        .order-items-table tr.item-cancelled td {
            text-decoration: line-through;
            color: #94A3B8;
        }
        .btn-cancel-item {
            background: none;
            border: none;
            color: #EF4444;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0.2rem;
            border-radius: 4px;
        }
        .btn-cancel-item:hover {
            background: #FEE2E2;
        }

        .order-payment-box {
            background: #FFFBEB;
            border: 1px solid #FCD34D;
            border-radius: var(--radius-md);
            padding: 0.75rem;
            font-size: 0.825rem;
        }
        .order-payment-box.paid {
            background: #ECFDF5;
            border-color: #A7F3D0;
        }
        .utr-display {
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 700;
            color: #1E293B;
            background: #FFF;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }

        .order-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: auto;
        }
        .btn-advance {
            width: 100%;
            padding: 0.65rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-advance.ready {
            background: var(--color-green);
            color: #FFF;
        }
        .btn-advance.ready:hover {
            background: #059669;
        }
        .btn-advance.complete {
            background: #3B82F6;
            color: #FFF;
        }
        .btn-advance.complete:hover {
            background: #2563EB;
        }
        .btn-cancel-order {
            width: 100%;
            padding: 0.5rem;
            background: none;
            border: 1px solid #FCA5A5;
            color: #DC2626;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-cancel-order:hover {
            background: #FEE2E2;
        }

        /* Inventory Manager Styles */
        .inventory-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .add-item-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .add-item-card h3 {
            margin: 0 0 1rem 0;
            font-size: 1.15rem;
            color: var(--text-primary);
        }
        .add-item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .inventory-table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
        }
        .inventory-table th {
            background: #F8FAFC;
            padding: 0.85rem 1rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
        }
        .inventory-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .stock-control {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .stock-btn {
            background: #F1F5F9;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .stock-btn:hover {
            background: #E2E8F0;
        }
        .stock-input {
            width: 55px;
            text-align: center;
            padding: 0.25rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-weight: 700;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #CBD5E1;
            transition: .3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: var(--color-green);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
    </style>
</head>
<body class="kitchen-app">

    <!-- Kitchen Navigation Header -->
    <header class="kitchen-header">
        <div class="kitchen-brand">
            <span class="logo-icon">🍳</span>
            <div>
                <h1>Campus Bistro &bull; Kitchen Console</h1>
            </div>
            <span>Staff Portal</span>
        </div>

        <div class="kitchen-user-info">
            <!-- Store Online/Offline Quick Toggle -->
            <button type="button" class="btn-store-status online" id="btnToggleStoreStatus" onclick="toggleStoreOnlineStatus()" title="Toggle Canteen Online/Offline for customer pre-orders">
                <span class="status-indicator-dot"></span>
                <span id="storeStatusLabel">Store: ONLINE</span>
            </button>

            <div class="clock-display" id="kitchenClock">--:--:--</div>
            <div class="staff-badge">
                <span>Staff:</span>
                <strong><?= e($adminName) ?></strong> (<?= e($adminRole) ?>)
            </div>
            <a href="logout.php" class="btn-logout" title="Secure Logout">Sign Out ➔</a>
        </div>
    </header>

    <!-- Quick Metrics Bar -->
    <section class="stats-bar">
        <div class="stat-card prep">
            <div>
                <div class="label">In Preparation</div>
                <div class="value" id="statPrep">0</div>
            </div>
            <span style="font-size: 2rem;">👨‍🍳</span>
        </div>
        <div class="stat-card ready">
            <div>
                <div class="label">Ready for Pickup</div>
                <div class="value" id="statReady">0</div>
            </div>
            <span style="font-size: 2rem;">🔔</span>
        </div>
        <div class="stat-card completed">
            <div>
                <div class="label">Completed Today</div>
                <div class="value" id="statCompleted">0</div>
            </div>
            <span style="font-size: 2rem;">✅</span>
        </div>
        <div class="stat-card revenue">
            <div>
                <div class="label">Today's Revenue</div>
                <div class="value" id="statRevenue">₹0.00</div>
            </div>
            <span style="font-size: 2rem;">💳</span>
        </div>
    </section>

    <!-- Main Tabs & Views -->
    <main class="kitchen-content">
        <nav class="kitchen-tabs">
            <button class="tab-btn active" data-tab="queue" id="tabQueueBtn">
                📋 Live Order Queue <span class="badge" id="queueBadge">0</span>
            </button>
            <button class="tab-btn" data-tab="inventory" id="tabInventoryBtn">
                📦 Stock & Menu Manager
            </button>
        </nav>

        <!-- =================================================================== -->
        <!-- TAB 1: LIVE ORDER QUEUE VIEW -->
        <!-- =================================================================== -->
        <section id="viewQueue">
            <div class="queue-controls">
                <div class="status-filters">
                    <button class="filter-btn active" data-filter="ALL">All Orders</button>
                    <button class="filter-btn" data-filter="PREPARING">Preparing</button>
                    <button class="filter-btn" data-filter="READY">Ready</button>
                    <button class="filter-btn" data-filter="COMPLETED">Completed</button>
                    <button class="filter-btn" data-filter="CANCELLED">Cancelled</button>
                </div>
                <button class="refresh-btn" id="btnManualRefresh" title="Refresh Live Queue">
                    🔄 Refresh Queue (<span id="autoRefreshCountdown">10s</span>)
                </button>
            </div>

            <div class="orders-grid" id="ordersContainer">
                <!-- Dynamically rendered order cards via assets/js/admin.js -->
                <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-muted);">
                    Loading active order queue...
                </div>
            </div>
        </section>

        <!-- =================================================================== -->
        <!-- TAB 2: INVENTORY & MENU MANAGER VIEW -->
        <!-- =================================================================== -->
        <section id="viewInventory" style="display: none;">
            <div class="inventory-panel">
                <!-- Add New Daily Item Form -->
                <div class="add-item-card">
                    <h3>➕ Add New Daily Menu Item</h3>
                    <form id="addMenuForm" autocomplete="off">
                        <div class="add-item-grid">
                            <div class="form-group">
                                <label for="mName">Item Name *</label>
                                <input type="text" id="mName" class="form-control" placeholder="e.g. Paneer Roll" required>
                            </div>
                            <div class="form-group">
                                <label for="mCategory">Category *</label>
                                <select id="mCategory" class="form-control" required>
                                    <option value="1">Snacks</option>
                                    <option value="2">Beverages</option>
                                    <option value="3">Meals</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="mPrice">Price (₹) *</label>
                                <input type="number" id="mPrice" step="0.50" min="1" class="form-control" placeholder="50.00" required>
                            </div>
                            <div class="form-group">
                                <label for="mPrepTime">Prep Time (mins) *</label>
                                <input type="number" id="mPrepTime" min="1" max="180" value="10" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="mStock">Initial Stock Qty *</label>
                                <input type="number" id="mStock" min="0" value="20" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 0.75rem;">
                            <label for="mDescription">Item Description (Optional)</label>
                            <input type="text" id="mDescription" class="form-control" placeholder="Fresh whole wheat roll with grilled paneer and mint mayo">
                        </div>

                        <button type="submit" class="btn-primary" style="max-width: 240px; margin-top: 1rem;">
                            Add Item to Active Menu
                        </button>
                    </form>
                </div>

                <!-- Live Inventory Items Table -->
                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th>Category</th>
                                <th>Price (₹)</th>
                                <th>Prep Time</th>
                                <th>Stock Inventory</th>
                                <th>Live Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <!-- Populated dynamically via assets/js/admin.js -->
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    Loading menu inventory...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Admin Script -->
    <script src="assets/js/admin.js"></script>
    <script>
        // Real-time clock display
        function updateClock() {
            const now = new Date();
            const clockEl = document.getElementById('kitchenClock');
            if (clockEl) {
                clockEl.textContent = now.toLocaleTimeString('en-US', { hour12: false });
            }
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
