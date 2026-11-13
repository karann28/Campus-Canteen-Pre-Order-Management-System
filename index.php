<?php
/**
 * Campus Canteen Pre-Order & Token System
 * Public Student Portal (STRICT CUSTOMER-FACING ONLY)
 * Zero links, references, or admin navigation present on this page.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

// Generate session-bound cryptographic CSRF token
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title>Campus Bistro &bull; Student Pre-Order & Token Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="site-wrapper">

    <!-- Top Navigation Header -->
    <header class="site-header">
        <div class="header-container">
            <div class="brand-group">
                <div class="brand-icon">🍽️</div>
                <div class="brand-titles">
                    <h1>Campus Bistro</h1>
                    <div class="tagline">Smart Canteen Pre-Orders & Digital Tokens</div>
                </div>
            </div>

            <div class="header-actions">
                <div class="store-status-badge online" id="customerStoreStatusBadge">
                    <span class="status-indicator-dot"></span>
                    <span id="customerStoreStatusText">Store is Online &bull; Accepting Orders</span>
                </div>
                <div class="operating-hours-badge">
                    <span>🕒</span>
                    <span>Kitchen Hours: 08:30 AM &ndash; 05:30 PM</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Search & Filter Section -->
    <section class="filter-section">
        <div class="filter-container">
            <div class="search-row">
                <!-- Keyword Search -->
                <div class="search-input-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" 
                           id="menuSearchInput" 
                           class="search-input" 
                           placeholder="Search delicious snacks, meals, beverages..." 
                           autocomplete="off">
                </div>

                <!-- Category Filter Pills -->
                <div class="category-pills">
                    <button class="cat-pill active" data-category="all">All Items</button>
                    <button class="cat-pill" data-category="snacks">Snacks</button>
                    <button class="cat-pill" data-category="beverages">Beverages</button>
                    <button class="cat-pill" data-category="meals">Meals</button>
                </div>

                <!-- Max Price Slider -->
                <div class="price-slider-wrap">
                    <label for="priceRangeSlider">Max Price:</label>
                    <input type="range" 
                           id="priceRangeSlider" 
                           min="20" 
                           max="250" 
                           step="5" 
                           value="250">
                    <span class="price-val-badge" id="priceRangeDisplay">₹250</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Menu Workspace -->
    <main class="main-container">
        <!-- Live Store Offline Alert Banner -->
        <div class="store-offline-banner" id="storeOfflineBanner" style="display: none;">
            <div class="banner-icon">🔴</div>
            <div>
                <strong>Canteen Store is Currently Offline</strong>
                <p>The kitchen has temporarily paused online pre-orders. You can browse the menu, but ordering is disabled until the store comes back online.</p>
            </div>
        </div>

        <div class="section-title-wrap">
            <h2 class="section-title">
                <span>Today's Fresh Canteen Menu</span>
            </h2>
            <span class="items-count-badge" id="itemsCountBadge">Loading menu...</span>
        </div>

        <!-- Dynamic Menu Items Grid -->
        <div class="menu-grid" id="menuGrid">
            <!-- Rendered dynamically by assets/js/app.js -->
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-muted);">
                Loading freshly prepared menu items...
            </div>
        </div>
    </main>

    <!-- Floating Sticky Cart Launcher Pill -->
    <button class="floating-cart-pill" id="floatingCartPill" style="display: none;" title="View Pre-Order Cart">
        <span>🛒 Cart</span>
        <span class="cart-count-badge" id="floatingCartCount">0 items</span>
        <span id="floatingCartTotal">₹0.00</span>
    </button>

    <!-- Cart Slide-Over Drawer -->
    <div class="cart-drawer-overlay" id="cartDrawerOverlay"></div>
    <aside class="cart-drawer" id="cartDrawer" aria-label="Shopping Cart">
        <div class="cart-drawer-header">
            <h2>Your Pre-Order Cart</h2>
            <button class="btn-close-drawer" id="btnCloseDrawer" title="Close Cart">&times;</button>
        </div>

        <div class="cart-items-list" id="cartDrawerItemsList">
            <!-- Dynamically populated via assets/js/app.js -->
        </div>

        <div class="cart-drawer-checkout">
            <!-- Bill Calculation Preview -->
            <div class="bill-summary">
                <div class="bill-row">
                    <span>Items Subtotal:</span>
                    <strong id="billSubtotal">₹0.00</strong>
                </div>
                <div class="bill-row">
                    <span>Convenience / Packaging Fee:</span>
                    <span style="color: var(--color-green); font-weight: 700;">FREE (₹0.00)</span>
                </div>
                <div class="bill-row total">
                    <span>Grand Total:</span>
                    <span id="billGrandTotal" style="color: var(--color-orange);">₹0.00</span>
                </div>
            </div>

            <!-- Student Order Form -->
            <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-top: 0.5rem;">
                <div>
                    <label for="studentNameInput" style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.2rem;">
                        Student Full Name *
                    </label>
                    <input type="text" 
                           id="studentNameInput" 
                           class="search-input" 
                           style="border-radius: var(--radius-md); padding: 0.55rem 0.75rem; font-size: 0.9rem;" 
                           placeholder="e.g. Rahul Sharma" 
                           required>
                </div>

                <div>
                    <label for="rollNumberInput" style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.2rem;">
                        Roll Number / Student ID *
                    </label>
                    <input type="text" 
                           id="rollNumberInput" 
                           class="search-input" 
                           style="border-radius: var(--radius-md); padding: 0.55rem 0.75rem; font-size: 0.9rem;" 
                           placeholder="e.g. CS2026-042" 
                           required>
                </div>

                <!-- Payment Method Selectors -->
                <div>
                    <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 0.4rem;">
                        Payment Method *
                    </span>
                    <div class="payment-selector">
                        <div class="payment-option-card selected" data-method="UPI">
                            <div class="opt-title">⚡ UPI QR Pre-Pay</div>
                            <div class="opt-desc">Instant queue skip (GPay, PhonePe, Paytm)</div>
                        </div>
                        <div class="payment-option-card" data-method="COUNTER">
                            <div class="opt-title">💵 Pay at Counter</div>
                            <div class="opt-desc">Pay cash or card when collecting token</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit CTA -->
            <button class="btn-primary" id="btnSubmitOrder" style="margin-top: 0.5rem;" disabled>
                Proceed to UPI Pre-Pay ➔
            </button>
        </div>
    </aside>

    <!-- =================================================================== -->
    <!-- UPI QR PAYMENT MODAL -->
    <!-- =================================================================== -->
    <div class="modal-overlay" id="upiModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>⚡ Scan to Pre-Pay with UPI</h3>
                <button class="btn-close-drawer" id="btnCloseUpiModal">&times;</button>
            </div>

            <div class="modal-body">
                <div class="qr-card-container">
                    <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                        Exact Locked Payable Amount
                    </span>
                    <div class="qr-amount-banner" id="upiModalAmount">₹0.00</div>

                    <!-- Official PhonePe UPI QR Code -->
                    <div class="qr-canvas-wrap" style="padding: 0.5rem; background: #FFF; border-radius: var(--radius-md); box-shadow: 0 4px 10px rgba(0,0,0,0.06);">
                        <img src="assets/images/phonepe-qr.jpg" alt="Scan to Pay via PhonePe UPI" id="upiQrImage" style="width: 220px; height: 220px; object-fit: contain; border-radius: 8px; display: block;">
                    </div>

                    <div class="upi-id-badge" id="btnCopyUpiId" title="Scan to pay via PhonePe / BHIM UPI">
                        <span>PhonePe / BHIM UPI</span>
                        <span style="font-size: 0.8rem;">📱 Scan to Pay</span>
                    </div>

                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0;">
                        Scan with PhonePe, GPay, Paytm, or any UPI App & enter the 12-digit UTR below
                    </p>
                </div>

                <!-- Mandatory 12-digit UPI UTR Verification -->
                <div class="form-group">
                    <label for="upiUtrInput" style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.3rem;">
                        12-Digit UPI Reference / UTR Number *
                    </label>
                    <input type="text" 
                           id="upiUtrInput" 
                           class="form-control" 
                           placeholder="Enter 12-digit UTR from payment receipt" 
                           maxlength="12" 
                           pattern="[0-9]{12}" 
                           required>
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.3rem;">
                        ℹ️ Open your UPI app payment receipt and locate the 12-digit "UPI Ref No" or "UTR".
                    </span>
                </div>

                <button type="button" class="btn-primary" id="btnConfirmUpiOrder">
                    Verify & Confirm Order ➔
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- BIG TOKEN ORDER CONFIRMATION MODAL -->
    <!-- =================================================================== -->
    <div class="modal-overlay" id="tokenSuccessModal">
        <div class="modal-box" style="text-align: center;">
            <div class="modal-header">
                <h3 style="width: 100%; text-align: center;">🎉 Order Confirmed!</h3>
            </div>
            <div class="modal-body token-reveal-box">
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">
                    Your Pre-Order Token Number
                </span>
                <div class="token-huge" id="successTokenNumber">TK-101</div>

                <div style="font-size: 0.95rem; margin-bottom: 0.5rem;">
                    Estimated Ready Time: <strong id="successPickupTime">--:--</strong>
                </div>

                <div style="font-size: 0.95rem; margin-bottom: 1rem;">
                    Total Bill: <strong id="successTotalAmount" style="color: var(--color-orange);">₹0.00</strong>
                </div>

                <div class="token-notice" id="successPaymentNote">
                    Please show your token number at the canteen counter for collection.
                </div>

                <button type="button" class="btn-primary" onclick="document.getElementById('tokenSuccessModal').classList.remove('open')">
                    Got it! Thank you ➔
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> Campus Bistro. Fresh food pre-ordered for busy students and faculty.</p>
        <p style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.25rem;">
            Operating Daily: 08:30 AM &ndash; 05:30 PM &bull; Closed on Institute Holidays
        </p>
    </footer>

    <!-- Client-side Scripts -->
    <script src="assets/js/app.js"></script>
</body>
</html>
