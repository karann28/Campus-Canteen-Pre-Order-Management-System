# Campus Canteen Pre-Order & Token System
A complete, secure, production-ready web application engineered using **HTML5, Modern CSS3, Vanilla JavaScript, PHP 8+ (PDO), and MySQL**.

Designed with a **"Modern Bistro"** aesthetic to eliminate cafeteria rush-hour bottlenecks through scheduled pre-orders, real-time token tracking, dynamic UPI QR payments, and atomic inventory management.

---

## 🏛️ System Architecture & File Structure

```
d:\web tech Project\
├── index.php                 # Customer Portal: Menu, Filters, Sticky Cart, UPI Checkout & Public Token Board
├── admin-login.php           # Dedicated Staff/Kitchen Login (Rate-limited, CSRF-protected, Hidden)
├── kitchen.php               # Protected Staff Dashboard: Order Queue, UPI Verification, Stock & Menu CRUD
├── logout.php                # Secure Admin Session Termination
├── database.sql              # MySQL DDL/DML: Tables, Indexes, Constraints, 12 Seed Items & Admin Hash
├── config/
│   ├── db.php                # Secure PDO connection (ATTR_EMULATE_PREPARES => false, strict error handling)
│   └── auth.php              # Session hardening, CSRF tokens, Admin guards, IP rate limiter, XSS escaping
├── api/
│   ├── menu.php              # Public GET (Filter/Search) | Admin POST/PUT/DELETE (Inventory & Pricing)
│   └── orders.php            # Public POST (Atomic Order Placement) | Public GET (Token Board) | Admin PUT/DELETE
└── assets/
    ├── css/
    │   └── style.css         # Modern Bistro CSS Grid/Flexbox styling with CSS Variables & animations
    └── js/
        ├── app.js            # Student Cart, live bill preview, operating hours validation, dynamic UPI QR
        └── admin.js          # Kitchen live queue polling, status workflow, inventory inline steppers
```

---

## 🔒 Security & Vulnerability Protections (Mandatory Controls)

1. **Zero Client-Side Price Trust (Anti-Price Tampering):**
   - The frontend transmits ONLY `[{ "menu_item_id": X, "quantity": Y }]`.
   - `api/orders.php` ignores any submitted price or total. The server queries the genuine price from MySQL within a database lock and calculates subtotals and Grand Totals strictly in PHP.
2. **Atomic Inventory & Transaction Integrity:**
   - Order creation runs inside `$pdo->beginTransaction()`.
   - Inventory rows are locked using `SELECT price, stock_qty, is_available FROM menu_items WHERE id = ? FOR UPDATE` to prevent race conditions during high-demand rushes.
   - If `stock_qty < quantity`, the transaction rolls back cleanly with a descriptive error.
   - Stock quantities are decremented upon order confirmation; if an item or order is cancelled in `kitchen.php`, stock is atomically refunded to `menu_items`.
3. **Authentication & Hidden Access:**
   - Admin passwords are encrypted using `password_hash(..., PASSWORD_BCRYPT)`.
   - `kitchen.php` and write endpoints in `/api/menu.php` and `/api/orders.php` enforce session authentication (`isAdminLoggedIn()`), terminating unauthorized access with `403 Forbidden`.
   - The student portal (`index.php`) has **zero** links, buttons, or references to the staff panel.
4. **Brute-Force Rate Limiting:**
   - `/admin-login.php` monitors failed attempts per IP address using `login_attempts` table.
   - Enforces a 15-minute lockout if 5 consecutive failed attempts occur.
5. **CSRF & Input Validation:**
   - Every state-changing form and Fetch API request validates a cryptographic session token (`$_SESSION['csrf_token']` via `X-CSRF-Token` header or POST field).
   - UPI UTR numbers are verified on the server using regex `/^[0-9]{12}$/` and checked for duplicate reuse.
6. **SQL Injection & XSS Protection:**
   - 100% of queries use PDO prepared statements with bound parameters (`ATTR_EMULATE_PREPARES => false`).
   - All string outputs are sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

## 🚀 Local Setup Instructions (XAMPP / WAMP / LAMP)

### Prerequisites
- **Web Server:** Apache 2.4+ (or Nginx)
- **PHP:** PHP 8.0 or newer with `pdo_mysql`, `session`, `json` extensions enabled
- **Database:** MySQL 5.7+ or MariaDB 10.3+

### Step 1: Place Project in Web Root
- **XAMPP (Windows):** Move or clone this project folder into `C:\xampp\htdocs\canteen` (or alias it).
- **WAMP (Windows):** Move into `C:\wamp64\www\canteen`.
- **Linux / LAMP:** Move into `/var/www/html/canteen`.

### Step 2: Import Database Schema & Seed Data
1. Start **Apache** and **MySQL** in your XAMPP/WAMP Control Panel.
2. Open **phpMyAdmin** in your browser: `http://localhost/phpmyadmin/`.
3. Click on the **Import** tab.
4. Choose the file `database.sql` from the project directory.
5. Click **Import** (or **Go**).
   *(Alternatively, run via MySQL CLI: `mysql -u root -p < database.sql`)*.
6. The database `canteen_db` will be created with all 6 tables, indexes, constraints, 12 authentic Indian canteen seed items, and the hashed admin account.

### Step 3: Configure Database Credentials (If Needed)
Default connection settings in `config/db.php`:
- **Host:** `127.0.0.1`
- **Port:** `3306`
- **Database:** `canteen_db`
- **User:** `root`
- **Password:** `""` (empty for standard local XAMPP/WAMP)

*(If your MySQL root user has a password, set environment variables `DB_PASS=your_password` or update `config/db.php`).*

---

## 🔑 Accessing the Portals & Default Credentials

### 1. Public Student Portal (Customer Facing Only)
- **URL:** `http://localhost/index.php` (or `http://localhost/canteen/index.php`)
- **Features:**
  - Live search and filter by category (`All`, `Snacks`, `Beverages`, `Meals`)
  - Max price slider (`₹20` &ndash; `₹250`)
  - Live stock indicators (`In Stock`, `Only 4 left`, `Sold Out`)
  - Stepper controls capped at available stock
  - Sticky cart drawer with live bill calculation
  - Pickup time validator enforcing operating hours (**08:30 to 17:30**) and minimum 15-minute advance notice
  - Dynamic UPI QR generator (`upi://pay?pa=canteen@upi&pn=CampusCanteen&am={TOTAL}&cu=INR`) with 12-digit UTR validation
  - Big Token reveal modal (`TK-101`)
  - Live Public Token Board (strictly non-sensitive, polling every 10s)

### 2. Hidden Staff & Kitchen Admin Portal
- **Login URL:** `http://localhost/admin-login.php` (or `http://localhost/canteen/admin-login.php`)
- **Dashboard URL:** `http://localhost/kitchen.php` (Protected; redirects if not logged in)
- **Default Seed Credentials:**
  - **Username:** `admin`
  - **Password:** `Admin@Campus2026!`
- **Kitchen Dashboard Features:**
  - Real-time order cards sorted by priority and pickup schedule
  - UPI payment verification with 12-digit UTR inspection and one-click copy
  - Workflow status progression: `Preparing` ➔ `Ready` ➔ `Completed`
  - Cancellation of individual items with automatic stock refund and bill deduction
  - Full order cancellation with bulk inventory restoration
  - Stock Inventory Manager: inline steppers (`-1`, `+1`, `+5`), direct value edit, availability toggles
  - Daily menu item addition form

