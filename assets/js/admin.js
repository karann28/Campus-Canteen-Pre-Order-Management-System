/**
 * Campus Canteen Pre-Order & Token System
 * Kitchen Dashboard & Inventory Manager Logic
 */

'use strict';

const adminState = {
    csrfToken: '',
    currentTab: 'queue',
    statusFilter: 'ALL',
    orders: [],
    stats: {},
    menuItems: [],
    pollTimer: null,
    countdownSeconds: 10,
    isStoreOnline: true
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Read CSRF Token from meta tag
    const metaCsrf = document.querySelector('meta[name="csrf-token"]');
    if (metaCsrf) {
        adminState.csrfToken = metaCsrf.getAttribute('content') || '';
    }

    // 2. Setup Tab Switching
    initTabs();

    // 3. Setup Queue Filter Buttons & Auto Refresh
    initQueueControls();

    // 4. Setup Add Menu Form
    initMenuForm();

    // 5. Initial Data Load
    loadOrderQueue();
    loadInventory();

    // 6. Start Polling Loop
    startQueuePolling();
});

// =============================================================================
// 1. Tab Navigation
// =============================================================================
function initTabs() {
    const tabQueueBtn = document.getElementById('tabQueueBtn');
    const tabInventoryBtn = document.getElementById('tabInventoryBtn');
    const viewQueue = document.getElementById('viewQueue');
    const viewInventory = document.getElementById('viewInventory');

    if (tabQueueBtn && tabInventoryBtn) {
        tabQueueBtn.addEventListener('click', () => {
            tabQueueBtn.classList.add('active');
            tabInventoryBtn.classList.remove('active');
            if (viewQueue) viewQueue.style.display = 'block';
            if (viewInventory) viewInventory.style.display = 'none';
            adminState.currentTab = 'queue';
            loadOrderQueue();
        });

        tabInventoryBtn.addEventListener('click', () => {
            tabInventoryBtn.classList.add('active');
            tabQueueBtn.classList.remove('active');
            if (viewQueue) viewQueue.style.display = 'none';
            if (viewInventory) viewInventory.style.display = 'block';
            adminState.currentTab = 'inventory';
            loadInventory();
        });
    }
}

// =============================================================================
// 2. Queue Controls & Polling Loop
// =============================================================================
function initQueueControls() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            adminState.statusFilter = btn.getAttribute('data-filter') || 'ALL';
            renderOrders();
        });
    });

    const refreshBtn = document.getElementById('btnManualRefresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            adminState.countdownSeconds = 10;
            loadOrderQueue();
        });
    }
}

function startQueuePolling() {
    // Countdown display ticker
    setInterval(() => {
        adminState.countdownSeconds--;
        const countdownEl = document.getElementById('autoRefreshCountdown');
        if (countdownEl) {
            countdownEl.textContent = `${adminState.countdownSeconds}s`;
        }

        if (adminState.countdownSeconds <= 0) {
            adminState.countdownSeconds = 10;
            if (adminState.currentTab === 'queue') {
                loadOrderQueue(true); // silent refresh
            }
        }
    }, 1000);
}

// =============================================================================
// 3. Fetch & Render Live Order Queue
// =============================================================================
async function loadOrderQueue(silent = false) {
    const container = document.getElementById('ordersContainer');
    if (!silent && container && adminState.orders.length === 0) {
        container.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--text-muted);">Loading active orders...</div>`;
    }

    try {
        const res = await fetch('api/orders.php?action=admin_queue');
        const data = await res.json();

        if (data.success) {
            adminState.orders = data.orders || [];
            adminState.stats = data.stats || {};
            updateStatsUI();
            renderOrders();
        } else {
            console.error('Queue load failed:', data.error);
        }
    } catch (err) {
        console.error('Queue fetch error:', err);
    }
}

function updateStatsUI() {
    const sPrep = document.getElementById('statPrep');
    const sReady = document.getElementById('statReady');
    const sComp = document.getElementById('statCompleted');
    const sRev = document.getElementById('statRevenue');
    const qBadge = document.getElementById('queueBadge');

    const stats = adminState.stats;
    if (sPrep) sPrep.textContent = stats.preparing || 0;
    if (sReady) sReady.textContent = stats.ready || 0;
    if (sComp) sComp.textContent = stats.completed_today || 0;
    if (sRev) sRev.textContent = `₹${(stats.revenue_today || 0).toFixed(2)}`;

    // Queue tab active badge
    if (qBadge) {
        const activeCount = (stats.preparing || 0) + (stats.ready || 0);
        qBadge.textContent = activeCount;
    }
}

function renderOrders() {
    const container = document.getElementById('ordersContainer');
    if (!container) return;

    // Filter by active status
    const filtered = adminState.orders.filter(ord => {
        if (adminState.statusFilter === 'ALL') return true;
        return ord.status === adminState.statusFilter;
    });

    if (filtered.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1.5rem; background: #FFF; border-radius: var(--radius-lg); border: 1.5px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
                <h3 style="color: var(--text-primary); margin-bottom: 0.25rem;">No orders in this view</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Select "All Orders" or wait for new student pre-orders to appear.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = filtered.map(order => {
        // Items list
        const itemsHtml = (order.items || []).map(item => {
            const isCancelled = item.status === 'CANCELLED';
            return `
                <tr class="${isCancelled ? 'item-cancelled' : ''}">
                    <td>
                        <strong>${escapeHtml(item.item_name)}</strong>
                        ${isCancelled ? '<span style="color: #DC2626; font-size: 0.7rem; font-weight: 700; margin-left: 0.25rem;">[CANCELLED]</span>' : ''}
                    </td>
                    <td style="text-align: center;">${item.quantity}x</td>
                    <td style="text-align: right;">₹${item.subtotal.toFixed(2)}</td>
                    <td style="text-align: right;">
                        ${!isCancelled && order.status !== 'COMPLETED' && order.status !== 'CANCELLED' ? `
                            <button class="btn-cancel-item" onclick="cancelOrderItem(${order.id}, ${item.id}, '${escapeHtml(item.item_name)}')" title="Cancel item & restock">❌</button>
                        ` : ''}
                    </td>
                </tr>
            `;
        }).join('');

        // Payment status box
        let paymentBoxHtml = '';
        if (order.payment_method === 'UPI') {
            const isPaid = order.payment_status === 'PAID';
            paymentBoxHtml = `
                <div class="order-payment-box ${isPaid ? 'paid' : ''}">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700;">💳 UPI Pre-Pay</span>
                        <span style="font-weight: 800; color: ${isPaid ? '#047857' : '#B45309'};">${isPaid ? 'PAID & VERIFIED' : 'PENDING VERIFICATION'}</span>
                    </div>
                    <div class="utr-display">
                        <span>UTR: <strong>${escapeHtml(order.utr_number || 'N/A')}</strong></span>
                        <button type="button" style="background:none;border:none;cursor:pointer;font-size:0.8rem;" onclick="copyToClipboard('${order.utr_number}')" title="Copy UTR">📋</button>
                    </div>
                    ${!isPaid && order.status !== 'CANCELLED' ? `
                        <button type="button" style="margin-top: 0.5rem; width: 100%; padding: 0.35rem; background: var(--color-green); color: #FFF; border: none; border-radius: 4px; font-size: 0.8rem; font-weight: 700; cursor: pointer;" onclick="verifyPayment(${order.id}, 'PAID')">
                            Verify UPI & Mark Paid
                        </button>
                    ` : ''}
                </div>
            `;
        } else {
            const isPaid = order.payment_status === 'PAID';
            paymentBoxHtml = `
                <div class="order-payment-box ${isPaid ? 'paid' : ''}">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700;">💵 Pay at Counter</span>
                        <span style="font-weight: 800; color: ${isPaid ? '#047857' : '#B45309'};">${isPaid ? 'COLLECTED & PAID' : 'DUE ON PICKUP'}</span>
                    </div>
                    ${!isPaid && order.status !== 'CANCELLED' ? `
                        <button type="button" style="margin-top: 0.5rem; width: 100%; padding: 0.35rem; background: #3B82F6; color: #FFF; border: none; border-radius: 4px; font-size: 0.8rem; font-weight: 700; cursor: pointer;" onclick="verifyPayment(${order.id}, 'PAID')">
                            Confirm Counter Cash Payment
                        </button>
                    ` : ''}
                </div>
            `;
        }

        // Action Workflow buttons
        let actionButtonsHtml = '';
        if (order.status === 'PREPARING') {
            actionButtonsHtml = `
                <button class="btn-advance ready" onclick="advanceOrderStatus(${order.id}, 'READY')">
                    🔔 Mark Order Ready for Pickup
                </button>
                <button class="btn-cancel-order" onclick="cancelEntireOrder(${order.id}, '${order.token_number}')">
                    Cancel Order & Restock Inventory
                </button>
            `;
        } else if (order.status === 'READY') {
            actionButtonsHtml = `
                <button class="btn-advance complete" onclick="advanceOrderStatus(${order.id}, 'COMPLETED')">
                    ✅ Complete / Handed Over
                </button>
                <button class="btn-cancel-order" onclick="cancelEntireOrder(${order.id}, '${order.token_number}')">
                    Cancel Order & Restock Inventory
                </button>
            `;
        } else if (order.status === 'COMPLETED') {
            actionButtonsHtml = `
                <div style="text-align: center; font-size: 0.8rem; color: #64748B; font-weight: 600;">
                    Order successfully picked up.
                </div>
            `;
        } else if (order.status === 'CANCELLED') {
            actionButtonsHtml = `
                <div style="text-align: center; font-size: 0.8rem; color: #DC2626; font-weight: 700;">
                    Order Cancelled & Restocked.
                </div>
            `;
        }

        return `
            <div class="order-card status-${order.status}" id="order-card-${order.id}">
                <div class="order-card-header">
                    <span class="token-pill">${escapeHtml(order.token_number)}</span>
                    <span class="status-badge ${order.status}">${order.status}</span>
                </div>

                <div class="student-details">
                    <div class="name">👤 ${escapeHtml(order.student_name)}</div>
                    <div class="sub">
                        <span>Roll: <strong>${escapeHtml(order.roll_number)}</strong></span>
                        <span>Target: <strong>⏱️ ${order.pickup_time ? order.pickup_time.slice(0, 5) : '--:--'}</strong></span>
                    </div>
                </div>

                ${paymentBoxHtml}

                <div>
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="text-align: center;">Qty</th>
                                <th style="text-align: right;">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1.5px solid var(--border-color); font-weight: 800; font-size: 1rem;">
                        <span>Grand Total:</span>
                        <span style="color: var(--color-orange);">₹${order.total_amount.toFixed(2)}</span>
                    </div>
                </div>

                <div class="order-actions">
                    ${actionButtonsHtml}
                </div>
            </div>
        `;
    }).join('');
}

// =============================================================================
// 4. Order Action Handlers (Status Advance, Payment, Cancellation)
// =============================================================================
async function advanceOrderStatus(orderId, nextStatus) {
    try {
        const res = await fetch('api/orders.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                action: 'advance_status',
                order_id: orderId,
                new_status: nextStatus
            })
        });

        const data = await res.json();
        if (data.success) {
            loadOrderQueue(true);
        } else {
            alert('Failed: ' + (data.error || 'Could not update status.'));
        }
    } catch (e) {
        console.error('Status update error:', e);
    }
}

async function verifyPayment(orderId, payStatus) {
    try {
        const res = await fetch('api/orders.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                action: 'verify_payment',
                order_id: orderId,
                payment_status: payStatus
            })
        });

        const data = await res.json();
        if (data.success) {
            loadOrderQueue(true);
        } else {
            alert('Failed to verify payment: ' + data.error);
        }
    } catch (e) {
        console.error('Payment verification error:', e);
    }
}

async function cancelEntireOrder(orderId, tokenNum) {
    const confirmed = confirm(`Are you sure you want to CANCEL order ${tokenNum}?\n\nThis will automatically restock all items back into available inventory.`);
    if (!confirmed) return;

    try {
        const res = await fetch('api/orders.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                action: 'cancel_order',
                order_id: orderId
            })
        });

        const data = await res.json();
        if (data.success) {
            alert(data.message);
            loadOrderQueue(true);
            loadInventory(); // Update inventory table
        } else {
            alert('Cancellation failed: ' + data.error);
        }
    } catch (e) {
        console.error('Cancel order error:', e);
    }
}

async function cancelOrderItem(orderId, orderItemId, itemName) {
    const confirmed = confirm(`Cancel "${itemName}" from this order?\n\nThis will restore the item quantity to stock and adjust the order bill.`);
    if (!confirmed) return;

    try {
        const res = await fetch('api/orders.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                action: 'cancel_item',
                order_id: orderId,
                order_item_id: orderItemId
            })
        });

        const data = await res.json();
        if (data.success) {
            loadOrderQueue(true);
            loadInventory();
        } else {
            alert('Error cancelling item: ' + data.error);
        }
    } catch (e) {
        console.error('Cancel item error:', e);
    }
}

// =============================================================================
// 5. Inventory & Menu Manager Logic
// =============================================================================
async function loadInventory() {
    const tbody = document.getElementById('inventoryTableBody');
    if (!tbody) return;

    try {
        const res = await fetch('api/menu.php?admin_view=1');
        const data = await res.json();

        if (data.success) {
            adminState.menuItems = data.items || [];
            if (data.is_store_online !== undefined) {
                adminState.isStoreOnline = data.is_store_online;
                updateStoreStatusUI(adminState.isStoreOnline);
            }
            renderInventoryTable();
        }
    } catch (e) {
        console.error('Failed to load menu inventory:', e);
    }
}

function updateStoreStatusUI(isOnline) {
    const btn = document.getElementById('btnToggleStoreStatus');
    const label = document.getElementById('storeStatusLabel');
    if (!btn || !label) return;

    if (isOnline) {
        btn.className = 'btn-store-status online';
        label.textContent = 'Store: ONLINE';
        btn.title = 'Store is currently Online. Click to turn Offline (pause customer pre-orders).';
    } else {
        btn.className = 'btn-store-status offline';
        label.textContent = 'Store: OFFLINE';
        btn.title = 'Store is currently Offline. Click to turn Online (accept customer pre-orders).';
    }
}

async function toggleStoreOnlineStatus() {
    try {
        const res = await fetch('api/menu.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                action: 'toggle_store_status'
            })
        });
        const data = await res.json();
        if (data.success) {
            adminState.isStoreOnline = data.is_store_online;
            updateStoreStatusUI(adminState.isStoreOnline);
            alert(data.message || (adminState.isStoreOnline ? 'Canteen store is now ONLINE!' : 'Canteen store is now OFFLINE!'));
        } else {
            alert('Error toggling store status: ' + data.error);
        }
    } catch (e) {
        console.error('Toggle store status error:', e);
    }
}

function renderInventoryTable() {
    const tbody = document.getElementById('inventoryTableBody');
    if (!tbody) return;

    if (adminState.menuItems.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:2rem;">No menu items found.</td></tr>`;
        return;
    }

    tbody.innerHTML = adminState.menuItems.map(item => {
        const isLow = item.stock_qty <= 5 && item.stock_qty > 0;
        const isOut = item.stock_qty === 0;

        let stockIndicator = '';
        if (isOut) {
            stockIndicator = `<span style="color:#DC2626; font-size:0.75rem; font-weight:700;">(Out of Stock)</span>`;
        } else if (isLow) {
            stockIndicator = `<span style="color:#B45309; font-size:0.75rem; font-weight:700;">(Low)</span>`;
        }

        return `
            <tr id="inv-row-${item.id}">
                <td>
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">${escapeHtml(item.name)}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(item.description || 'No description')}</div>
                </td>
                <td>
                    <span class="item-category-tag">${escapeHtml(item.category_name)}</span>
                </td>
                <td>
                    <div class="price-edit-box">
                        <span class="currency-prefix">₹</span>
                        <input type="number" 
                               class="price-edit-input" 
                               id="price-input-${item.id}" 
                               value="${item.price.toFixed(2)}" 
                               step="0.50" 
                               min="1"
                               onkeydown="if(event.key==='Enter') quickUpdateItemPrice(${item.id})"
                               title="Edit price on the go">
                        <button type="button" class="btn-save-price" onclick="quickUpdateItemPrice(${item.id})" title="Save price">✓</button>
                    </div>
                </td>
                <td>
                    ${item.prep_time_minutes} mins
                </td>
                <td>
                    <div class="stock-control">
                        <button class="stock-btn" onclick="adjustItemStock(${item.id}, -1)">-1</button>
                        <input type="number" 
                               class="stock-input" 
                               id="stock-input-${item.id}" 
                               value="${item.stock_qty}" 
                               min="0"
                               onchange="setItemStock(${item.id}, this.value)">
                        <button class="stock-btn" onclick="adjustItemStock(${item.id}, 1)">+1</button>
                        <button class="stock-btn" onclick="adjustItemStock(${item.id}, 5)">+5</button>
                        ${stockIndicator}
                    </div>
                </td>
                <td>
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               ${item.is_available ? 'checked' : ''} 
                               onchange="toggleItemAvailability(${item.id})">
                        <span class="toggle-slider"></span>
                    </label>
                </td>
                <td>
                    <button type="button" 
                            style="background:none; border:1px solid #FCA5A5; color:#DC2626; padding:0.3rem 0.6rem; border-radius:4px; font-size:0.8rem; cursor:pointer;" 
                            onclick="deleteMenuItem(${item.id}, '${escapeHtml(item.name)}')">
                        Remove
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function quickUpdateItemPrice(itemId) {
    const input = document.getElementById(`price-input-${itemId}`);
    if (!input) return;
    const newPrice = parseFloat(input.value);
    if (isNaN(newPrice) || newPrice <= 0) {
        alert('Please enter a valid price greater than ₹0.00');
        return;
    }

    try {
        const res = await fetch('api/menu.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                id: itemId,
                action: 'set_price',
                price: newPrice
            })
        });
        const data = await res.json();
        if (data.success) {
            input.style.borderColor = 'var(--color-green)';
            input.style.backgroundColor = '#ECFDF5';
            setTimeout(() => {
                input.style.borderColor = '';
                input.style.backgroundColor = '';
            }, 1200);
            loadInventory();
        } else {
            alert('Failed to update price: ' + data.error);
        }
    } catch (e) {
        console.error('Price update error:', e);
    }
}

async function adjustItemStock(itemId, delta) {
    try {
        const res = await fetch('api/menu.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                id: itemId,
                action: 'adjust_stock',
                stock_delta: delta
            })
        });
        const data = await res.json();
        if (data.success) {
            loadInventory();
        } else {
            alert(data.error);
        }
    } catch (e) {
        console.error('Stock adjust error:', e);
    }
}

async function setItemStock(itemId, newValue) {
    const qty = parseInt(newValue, 10);
    if (isNaN(qty) || qty < 0) return;

    try {
        const res = await fetch('api/menu.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                id: itemId,
                action: 'set_stock',
                stock_qty: qty
            })
        });
        const data = await res.json();
        if (data.success) {
            loadInventory();
        } else {
            alert(data.error);
        }
    } catch (e) {
        console.error('Stock set error:', e);
    }
}

async function toggleItemAvailability(itemId) {
    try {
        const res = await fetch('api/menu.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                id: itemId,
                action: 'toggle_availability'
            })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.error);
            loadInventory();
        }
    } catch (e) {
        console.error('Availability toggle error:', e);
    }
}

async function deleteMenuItem(itemId, itemName) {
    const confirmed = confirm(`Are you sure you want to remove "${itemName}" from the canteen menu?`);
    if (!confirmed) return;

    try {
        const res = await fetch('api/menu.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminState.csrfToken
            },
            body: JSON.stringify({
                id: itemId
            })
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            loadInventory();
        } else {
            alert(data.error);
        }
    } catch (e) {
        console.error('Delete item error:', e);
    }
}

function initMenuForm() {
    const form = document.getElementById('addMenuForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const name = document.getElementById('mName').value.trim();
        const catId = parseInt(document.getElementById('mCategory').value, 10);
        const price = parseFloat(document.getElementById('mPrice').value);
        const prepTime = parseInt(document.getElementById('mPrepTime').value, 10);
        const stock = parseInt(document.getElementById('mStock').value, 10);
        const desc = document.getElementById('mDescription').value.trim();

        if (!name || isNaN(price) || price <= 0) {
            alert('Please fill out all required fields with valid values.');
            return;
        }

        try {
            const res = await fetch('api/menu.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': adminState.csrfToken
                },
                body: JSON.stringify({
                    name: name,
                    category_id: catId,
                    price: price,
                    prep_time_minutes: prepTime,
                    stock_qty: stock,
                    description: desc,
                    is_available: 1
                })
            });

            const data = await res.json();
            if (data.success) {
                alert(data.message);
                form.reset();
                loadInventory();
            } else {
                alert('Error: ' + data.error);
            }
        } catch (err) {
            console.error('Add menu item error:', err);
            alert('Network error adding menu item.');
        }
    });
}

// =============================================================================
// Helper Utilities
// =============================================================================
function copyToClipboard(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied UTR: ' + text);
    }).catch(() => {
        prompt('Copy UTR manually:', text);
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

