/**
 * Campus Canteen Pre-Order & Token System
 * Student Portal Logic: Cart, Live Bill Calculator, Time Validation, Dynamic UPI QR, and Token Board
 */

'use strict';

// =============================================================================
// 1. Application State
// =============================================================================
const state = {
    menuItems: [],          // Raw items fetched from API
    categories: [],         // Categories fetched from API
    activeCategory: 'all',  // 'all' or category slug/id
    searchQuery: '',        // Current search text
    maxPrice: 250,          // Max price filter limit
    cart: {},               // { [itemId]: { id, name, price, stock_qty, quantity, prep_time } }
    selectedPayment: 'UPI', // 'UPI' or 'COUNTER'
    csrfToken: '',          // Fetched from DOM meta tag or input
    isStoreOnline: true     // Live Store Status from kitchen
};

// =============================================================================
// 2. Initialization on DOM Content Loaded
// =============================================================================
document.addEventListener('DOMContentLoaded', () => {
    // 1. Read CSRF token from DOM
    const metaCsrf = document.querySelector('meta[name="csrf-token"]');
    if (metaCsrf) {
        state.csrfToken = metaCsrf.getAttribute('content') || '';
    }

    // 2. Setup DOM event listeners
    initSearchAndFilters();
    initCartDrawer();
    initPaymentSelectors();
    initUpiModal();

    // 3. Load Menu Items & Start Live Auto-Refresh (every 10s for on-the-go price updates)
    fetchMenuItems();
    setInterval(fetchMenuItems, 10000);
});

// =============================================================================
// 3. Fetch & Render Menu Items
// =============================================================================
async function fetchMenuItems() {
    const grid = document.getElementById('menuGrid');
    if (!grid) return;

    try {
        const res = await fetch('api/menu.php');
        const data = await res.json();

        if (data.success && Array.isArray(data.items)) {
            state.menuItems = data.items;
            state.categories = data.categories || [];

            if (data.is_store_online !== undefined) {
                state.isStoreOnline = data.is_store_online;
                updateCustomerStoreStatusUI(state.isStoreOnline);
            }

            // Sync on-the-go price changes from kitchen to student cart
            let priceChanged = false;
            for (const cId in state.cart) {
                const updatedItem = state.menuItems.find(m => m.id === parseInt(cId, 10));
                if (updatedItem) {
                    if (state.cart[cId].price !== updatedItem.price) {
                        state.cart[cId].price = updatedItem.price;
                        priceChanged = true;
                    }
                    state.cart[cId].stock_qty = updatedItem.stock_qty;
                }
            }
            if (priceChanged) {
                renderCartDrawerList();
                renderBillSummary();
                showToast('Menu prices were updated by the kitchen.', 'normal');
            }

            renderFilteredMenu();
        } else {
            grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; color: var(--color-red); padding: 2rem;">
                Failed to load menu items. Please refresh the page.
            </div>`;
        }
    } catch (err) {
        console.error('Error fetching menu items:', err);
    }
}

function updateCustomerStoreStatusUI(isOnline) {
    const badge = document.getElementById('customerStoreStatusBadge');
    const text = document.getElementById('customerStoreStatusText');
    const banner = document.getElementById('storeOfflineBanner');

    if (isOnline) {
        if (badge) {
            badge.className = 'store-status-badge online';
            if (text) text.innerHTML = 'Store is Online &bull; Accepting Orders';
        }
        if (banner) banner.style.display = 'none';
    } else {
        if (badge) {
            badge.className = 'store-status-badge offline';
            if (text) text.innerHTML = 'Store is Offline &bull; Orders Paused';
        }
        if (banner) banner.style.display = 'flex';
    }
}

function renderFilteredMenu() {
    const grid = document.getElementById('menuGrid');
    const countBadge = document.getElementById('itemsCountBadge');
    if (!grid) return;

    // Filter items based on activeCategory, searchQuery, and maxPrice
    const filtered = state.menuItems.filter(item => {
        // Category check
        if (state.activeCategory !== 'all') {
            const matchesCategory = item.category_slug === state.activeCategory || 
                                    String(item.category_id) === String(state.activeCategory);
            if (!matchesCategory) return false;
        }

        // Search check
        if (state.searchQuery.trim() !== '') {
            const query = state.searchQuery.toLowerCase();
            const inName = item.name.toLowerCase().includes(query);
            const inDesc = (item.description || '').toLowerCase().includes(query);
            if (!inName && !inDesc) return false;
        }

        // Price check
        if (item.price > state.maxPrice) {
            return false;
        }

        return true;
    });

    if (countBadge) {
        countBadge.textContent = `Showing ${filtered.length} item(s)`;
    }

    if (filtered.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1.5rem; background: #FFF; border-radius: var(--radius-lg); border: 1.5px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
                <h3 style="color: var(--text-primary); margin-bottom: 0.25rem;">No items found</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Try clearing your search query or adjusting the max price slider.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = filtered.map(item => {
        const cartItem = state.cart[item.id];
        const inCartQty = cartItem ? cartItem.quantity : 0;
        const isSoldOut = item.stock_qty <= 0;

        let stockBadgeHtml = '';
        if (isSoldOut) {
            stockBadgeHtml = `<span class="item-stock-badge stock-out">Sold Out</span>`;
        } else if (item.stock_qty <= 5) {
            stockBadgeHtml = `<span class="item-stock-badge stock-low">Only ${item.stock_qty} left</span>`;
        } else {
            stockBadgeHtml = `<span class="item-stock-badge stock-in">In Stock (${item.stock_qty})</span>`;
        }

        // CTA: Either "Add" button or Stepper if already in cart
        let ctaHtml = '';
        if (!state.isStoreOnline) {
            ctaHtml = `<button class="btn-add-item" disabled style="background: #94A3B8; cursor: not-allowed;">Store Offline</button>`;
        } else if (isSoldOut) {
            ctaHtml = `<button class="btn-add-item" disabled>Sold Out</button>`;
        } else if (inCartQty > 0) {
            ctaHtml = `
                <div class="qty-stepper">
                    <button class="stepper-btn" onclick="updateCartItemQty(${item.id}, -1)" title="Reduce quantity">&minus;</button>
                    <span class="stepper-qty">${inCartQty}</span>
                    <button class="stepper-btn" onclick="updateCartItemQty(${item.id}, 1)" ${inCartQty >= item.stock_qty ? 'disabled' : ''} title="Increase quantity">&plus;</button>
                </div>
            `;
        } else {
            ctaHtml = `<button class="btn-add-item" onclick="addToCart(${item.id})">+ Add</button>`;
        }

        return `
            <article class="menu-card ${isSoldOut ? 'sold-out' : ''}" data-id="${item.id}">
                ${item.image_url ? `
                    <div class="menu-card-img-wrap">
                        <img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}" class="menu-card-img" loading="lazy">
                        <span class="img-category-pill">${escapeHtml(item.category_name)}</span>
                    </div>
                ` : ''}
                <div class="menu-card-body">
                    <div class="menu-card-top">
                        <span class="item-prep-tag">⏱️ ${item.prep_time_minutes} min prep</span>
                    </div>

                    <h3 class="item-name">${escapeHtml(item.name)}</h3>
                    <p class="item-desc">${escapeHtml(item.description || 'Prepared fresh on order at the campus bistro.')}</p>

                    <div class="menu-card-footer">
                        <div class="price-stock-box">
                            <span class="item-price">₹${item.price.toFixed(2)}</span>
                            ${stockBadgeHtml}
                        </div>
                        <div class="card-cta-wrap">
                            ${ctaHtml}
                        </div>
                    </div>
                </div>
            </article>
        `;
    }).join('');
}

// =============================================================================
// 4. Search & Filter Interactions
// =============================================================================
function initSearchAndFilters() {
    const searchInput = document.getElementById('menuSearchInput');
    const priceRange = document.getElementById('priceRangeSlider');
    const priceDisplay = document.getElementById('priceRangeDisplay');
    const categoryPills = document.querySelectorAll('.cat-pill');

    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                state.searchQuery = e.target.value.trim();
                renderFilteredMenu();
            }, 180);
        });
    }

    if (priceRange && priceDisplay) {
        priceRange.addEventListener('input', (e) => {
            state.maxPrice = parseFloat(e.target.value);
            priceDisplay.textContent = `₹${state.maxPrice}`;
            renderFilteredMenu();
        });
    }

    categoryPills.forEach(pill => {
        pill.addEventListener('click', () => {
            categoryPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            state.activeCategory = pill.getAttribute('data-category') || 'all';
            renderFilteredMenu();
        });
    });
}

// =============================================================================
// 5. Cart Management & Live Bill Calculation
// =============================================================================
function addToCart(itemId) {
    const item = state.menuItems.find(m => m.id === itemId);
    if (!item) return;

    if (item.stock_qty <= 0) {
        showToast('Sorry, this item is sold out!', 'error');
        return;
    }

    state.cart[itemId] = {
        id: item.id,
        name: item.name,
        price: item.price,
        stock_qty: item.stock_qty,
        quantity: 1,
        prep_time: item.prep_time_minutes
    };

    updateCartUI();
    showToast(`Added "${item.name}" to cart`);
}

function updateCartItemQty(itemId, delta) {
    const item = state.cart[itemId];
    if (!item) return;

    const newQty = item.quantity + delta;

    if (newQty <= 0) {
        delete state.cart[itemId];
        showToast(`Removed "${item.name}" from cart`);
    } else {
        // Enforce stock_qty cap
        if (newQty > item.stock_qty) {
            showToast(`Cannot add more. Only ${item.stock_qty} available in stock!`, 'error');
            return;
        }
        item.quantity = newQty;
    }

    updateCartUI();
}

function updateCartUI() {
    renderFilteredMenu(); // Synchronize stepper buttons on menu cards
    renderCartDrawerList();
    renderBillSummary();

    // Update floating launcher pill
    const countBadge = document.getElementById('floatingCartCount');
    const totalBadge = document.getElementById('floatingCartTotal');
    const floatingPill = document.getElementById('floatingCartPill');

    const totalItems = Object.values(state.cart).reduce((sum, it) => sum + it.quantity, 0);
    const grandTotal = Object.values(state.cart).reduce((sum, it) => sum + (it.price * it.quantity), 0);

    if (countBadge) countBadge.textContent = `${totalItems} items`;
    if (totalBadge) totalBadge.textContent = `₹${grandTotal.toFixed(2)}`;

    if (floatingPill) {
        if (totalItems > 0) {
            floatingPill.style.display = 'flex';
        } else {
            floatingPill.style.display = 'none';
        }
    }
}

function renderCartDrawerList() {
    const listEl = document.getElementById('cartDrawerItemsList');
    if (!listEl) return;

    const cartEntries = Object.values(state.cart);

    if (cartEntries.length === 0) {
        listEl.innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛒</div>
                <p style="font-weight: 700; color: var(--text-primary);">Your pre-order cart is empty</p>
                <p style="font-size: 0.85rem;">Add delicious dishes from the menu to generate your pickup token.</p>
            </div>
        `;
        return;
    }

    listEl.innerHTML = cartEntries.map(item => {
        const itemSubtotal = item.price * item.quantity;
        return `
            <div class="cart-item-row">
                <div class="cart-item-info">
                    <div class="cart-item-title">${escapeHtml(item.name)}</div>
                    <div class="cart-item-subtext">₹${item.price.toFixed(2)} each</div>
                </div>

                <div class="qty-stepper" style="margin: 0 0.5rem;">
                    <button class="stepper-btn" onclick="updateCartItemQty(${item.id}, -1)">&minus;</button>
                    <span class="stepper-qty">${item.quantity}</span>
                    <button class="stepper-btn" onclick="updateCartItemQty(${item.id}, 1)" ${item.quantity >= item.stock_qty ? 'disabled' : ''}>&plus;</button>
                </div>

                <div class="cart-item-subtotal">₹${itemSubtotal.toFixed(2)}</div>
            </div>
        `;
    }).join('');
}

function renderBillSummary() {
    const subtotalEl = document.getElementById('billSubtotal');
    const grandTotalEl = document.getElementById('billGrandTotal');
    const submitBtn = document.getElementById('btnSubmitOrder');

    const totalItems = Object.values(state.cart).reduce((sum, it) => sum + it.quantity, 0);
    const grandTotal = Object.values(state.cart).reduce((sum, it) => sum + (it.price * it.quantity), 0);

    if (subtotalEl) subtotalEl.textContent = `₹${grandTotal.toFixed(2)}`;
    if (grandTotalEl) grandTotalEl.textContent = `₹${grandTotal.toFixed(2)}`;

    if (submitBtn) {
        if (!state.isStoreOnline) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Store is Currently Offline';
        } else {
            submitBtn.disabled = totalItems === 0;
            submitBtn.textContent = state.selectedPayment === 'UPI' ? 'Proceed to UPI Pre-Pay ➔' : 'Confirm Pre-Order (Pay at Counter) ➔';
        }
    }
}

// =============================================================================
// 6. Cart Drawer Toggle Controls
// =============================================================================
function initCartDrawer() {
    const floatingPill = document.getElementById('floatingCartPill');
    const drawerOverlay = document.getElementById('cartDrawerOverlay');
    const drawer = document.getElementById('cartDrawer');
    const closeBtn = document.getElementById('btnCloseDrawer');

    function openDrawer() {
        if (drawerOverlay && drawer) {
            drawerOverlay.classList.add('open');
            drawer.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeDrawer() {
        if (drawerOverlay && drawer) {
            drawerOverlay.classList.remove('open');
            drawer.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    if (floatingPill) floatingPill.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', (e) => {
            if (e.target === drawerOverlay) closeDrawer();
        });
    }

    // Attach to checkout button
    const submitBtn = document.getElementById('btnSubmitOrder');
    if (submitBtn) {
        submitBtn.addEventListener('click', handleOrderInitiation);
    }
}

// =============================================================================
// 7. Auto-Estimated Ready Time Calculator
// =============================================================================
function getEstimatedPickupTime() {
    const items = Object.values(state.cart);
    const maxPrep = items.length > 0 ? Math.max(...items.map(it => it.prep_time || 10), 10) : 15;
    const estDate = new Date(Date.now() + maxPrep * 60000);
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(estDate.getHours())}:${pad(estDate.getMinutes())}`;
}

// =============================================================================
// 8. Payment Method Selection (UPI Pre-Pay vs Counter)
// =============================================================================
function initPaymentSelectors() {
    const cards = document.querySelectorAll('.payment-option-card');
    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            state.selectedPayment = card.getAttribute('data-method') || 'UPI';

            const submitBtn = document.getElementById('btnSubmitOrder');
            if (submitBtn) {
                if (state.selectedPayment === 'UPI') {
                    submitBtn.textContent = 'Proceed to UPI Pre-Pay ➔';
                } else {
                    submitBtn.textContent = 'Confirm Pre-Order (Pay at Counter) ➔';
                }
            }
        });
    });
}

// =============================================================================
// 9. Order Initiation & UPI Pre-Pay Workflow
// =============================================================================
function handleOrderInitiation() {
    if (!state.isStoreOnline) {
        showToast('Sorry, the canteen store is currently offline and not accepting pre-orders.', 'error');
        return;
    }

    const studentNameInput = document.getElementById('studentNameInput');
    const rollNumberInput = document.getElementById('rollNumberInput');

    const name = (studentNameInput ? studentNameInput.value.trim() : '');
    const roll = (rollNumberInput ? rollNumberInput.value.trim() : '');

    if (name.length < 2) {
        showToast('Please enter your full student name.', 'error');
        if (studentNameInput) studentNameInput.focus();
        return;
    }

    if (roll.length < 2) {
        showToast('Please enter your Roll Number or Student ID.', 'error');
        if (rollNumberInput) rollNumberInput.focus();
        return;
    }

    const items = Object.values(state.cart);
    if (items.length === 0) {
        showToast('Your cart is empty!', 'error');
        return;
    }

    const autoPickupTime = getEstimatedPickupTime();

    if (state.selectedPayment === 'UPI') {
        openUpiModal();
    } else {
        // Counter Payment: Submit order directly
        submitOrderToServer({
            student_name: name,
            roll_number: roll,
            pickup_time: autoPickupTime,
            payment_method: 'COUNTER',
            utr_number: null,
            items: items.map(it => ({ menu_item_id: it.id, quantity: it.quantity }))
        });
    }
}

// =============================================================================
// 10. Dynamic UPI QR Generator & Modal Controls
// =============================================================================
function initUpiModal() {
    const modal = document.getElementById('upiModal');
    const closeBtn = document.getElementById('btnCloseUpiModal');
    const copyBtn = document.getElementById('btnCopyUpiId');
    const confirmBtn = document.getElementById('btnConfirmUpiOrder');
    const utrInput = document.getElementById('upiUtrInput');

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            if (modal) modal.classList.remove('open');
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText('canteen@upi').then(() => {
                showToast('UPI ID copied to clipboard: canteen@upi');
            }).catch(() => {
                showToast('canteen@upi');
            });
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            const utr = utrInput ? utrInput.value.trim() : '';
            if (!/^[0-9]{12}$/.test(utr)) {
                showToast('Please enter a valid 12-digit UPI UTR number.', 'error');
                if (utrInput) utrInput.focus();
                return;
            }

            const studentNameInput = document.getElementById('studentNameInput');
            const rollNumberInput = document.getElementById('rollNumberInput');

            submitOrderToServer({
                student_name: studentNameInput ? studentNameInput.value.trim() : '',
                roll_number: rollNumberInput ? rollNumberInput.value.trim() : '',
                pickup_time: getEstimatedPickupTime(),
                payment_method: 'UPI',
                utr_number: utr,
                items: Object.values(state.cart).map(it => ({ menu_item_id: it.id, quantity: it.quantity }))
            });
        });
    }
}

function openUpiModal() {
    const modal = document.getElementById('upiModal');
    const amountEl = document.getElementById('upiModalAmount');
    const utrInput = document.getElementById('upiUtrInput');
    if (!modal) return;

    const grandTotal = Object.values(state.cart).reduce((sum, it) => sum + (it.price * it.quantity), 0);
    const formattedTotal = grandTotal.toFixed(2);

    if (amountEl) amountEl.textContent = `₹${formattedTotal}`;
    if (utrInput) utrInput.value = '';

    modal.classList.add('open');
}

// =============================================================================
// 11. Submit Order to Server (Zero Price Trust Enforcement)
// =============================================================================
async function submitOrderToServer(payload) {
    const submitBtn = document.getElementById('btnSubmitOrder');
    const confirmUpiBtn = document.getElementById('btnConfirmUpiOrder');

    if (submitBtn) submitBtn.disabled = true;
    if (confirmUpiBtn) confirmUpiBtn.disabled = true;

    showToast('Submitting your pre-order to kitchen...');

    try {
        const res = await fetch('api/orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': state.csrfToken
            },
            body: JSON.stringify({
                ...payload,
                csrf_token: state.csrfToken
            })
        });

        const data = await res.json();

        if (res.ok && data.success) {
            // Close drawers and modals
            const cartOverlay = document.getElementById('cartDrawerOverlay');
            const cartDrawer = document.getElementById('cartDrawer');
            const upiModal = document.getElementById('upiModal');
            if (cartOverlay) cartOverlay.classList.remove('open');
            if (cartDrawer) cartDrawer.classList.remove('open');
            if (upiModal) upiModal.classList.remove('open');
            document.body.style.overflow = '';

            // Reset cart state
            state.cart = {};
            updateCartUI();

            // Show Token Success Modal
            showOrderSuccessModal(data.token_number, data.pickup_time, data.total_amount, payload.payment_method);

            // Immediately refresh menu item inventory counts
            fetchMenuItems();
        } else {
            showToast(data.error || 'Failed to place order. Please try again.', 'error');
            // If inventory race occurred, refresh menu items to display fresh stock
            fetchMenuItems();
        }
    } catch (err) {
        console.error('Submission error:', err);
        showToast('Network error submitting order. Please check your connection.', 'error');
    } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (confirmUpiBtn) confirmUpiBtn.disabled = false;
    }
}

function showOrderSuccessModal(tokenNumber, pickupTime, totalAmount, paymentMethod) {
    const modal = document.getElementById('tokenSuccessModal');
    const tokenEl = document.getElementById('successTokenNumber');
    const timeEl = document.getElementById('successPickupTime');
    const amountEl = document.getElementById('successTotalAmount');
    const payNoteEl = document.getElementById('successPaymentNote');
    if (!modal) return;

    if (tokenEl) tokenEl.textContent = tokenNumber;
    if (timeEl) timeEl.textContent = pickupTime;
    if (amountEl) amountEl.textContent = `₹${parseFloat(totalAmount).toFixed(2)}`;

    if (payNoteEl) {
        if (paymentMethod === 'UPI') {
            payNoteEl.textContent = 'UPI UTR submitted. Kitchen staff will verify payment upon token call.';
        } else {
            payNoteEl.textContent = 'Please pay ₹' + parseFloat(totalAmount).toFixed(2) + ' at the counter during token pickup.';
        }
    }

    modal.classList.add('open');
}

// =============================================================================
// 12. UI Notification Toasts & Helpers
// =============================================================================
function showToast(message, type = 'normal') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type === 'error' ? 'toast-error' : (type === 'success' ? 'toast-success' : '')}`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

