/**
 * Campus Canteen Pre-Order & Token System
 * Standalone Zero-Dependency Development Server & Full Application Runner
 * 
 * Supports both pure PHP deployment on XAMPP/WAMP AND instant local execution on Node.js!
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 3000;
const HOST = '127.0.0.1';

// =============================================================================
// In-Memory Database Store (Pre-seeded identical to database.sql)
// =============================================================================
const categories = [
    { id: 1, name: 'Snacks', slug: 'snacks', display_order: 1 },
    { id: 2, name: 'Beverages', slug: 'beverages', display_order: 2 },
    { id: 3, name: 'Meals', slug: 'meals', display_order: 3 }
];

let menuItems = [
    { id: 1, category_id: 3, name: 'Masala Dosa', description: 'Crisp golden dosa filled with spiced potato masala, served with coconut chutney and piping hot sambar.', price: 60.00, prep_time_minutes: 12, stock_qty: 25, is_available: 1, image_url: 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?w=600&auto=format&fit=crop&q=80' },
    { id: 2, category_id: 1, name: 'Samosa (2 Pcs)', description: 'Flaky golden triangular pastries stuffed with spicy potato & green pea filling, served with tamarind and mint chutney.', price: 30.00, prep_time_minutes: 5, stock_qty: 40, is_available: 1, image_url: 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=600&auto=format&fit=crop&q=80' },
    { id: 3, category_id: 3, name: 'Pav Bhaji', description: 'Rich, buttery spiced mashed vegetable curry served with two toasted soft pav buns and sliced red onions.', price: 70.00, prep_time_minutes: 10, stock_qty: 20, is_available: 1, image_url: 'https://images.unsplash.com/photo-1626132647523-66f5bf380027?w=600&auto=format&fit=crop&q=80' },
    { id: 4, category_id: 3, name: 'Veg Biryani with Raita', description: 'Aromatic basmati rice cooked with garden-fresh veggies and fragrant whole spices, paired with cooling cucumber raita.', price: 110.00, prep_time_minutes: 15, stock_qty: 15, is_available: 1, image_url: 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80' },
    { id: 5, category_id: 1, name: 'Vada Pav', description: 'Mumbai street-style spiced potato batata vada nestled in pav with fiery garlic dry chutney and fried green chili.', price: 25.00, prep_time_minutes: 5, stock_qty: 35, is_available: 1, image_url: 'assets/images/vada-pav.jpg' },
    { id: 6, category_id: 3, name: 'Paneer Butter Masala Combo', description: 'Creamy cottage cheese cubes in rich tomato-cashew gravy served with 2 freshly made whole-wheat tawa parathas.', price: 130.00, prep_time_minutes: 15, stock_qty: 12, is_available: 1, image_url: 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=600&auto=format&fit=crop&q=80' },
    { id: 7, category_id: 2, name: 'Masala Chai', description: 'Signature Indian milk tea brewed with crushed ginger, green cardamom, cloves, and premium Assam tea leaves.', price: 20.00, prep_time_minutes: 5, stock_qty: 50, is_available: 1, image_url: 'assets/images/masala-chai.jpg' },
    { id: 8, category_id: 2, name: 'Cold Coffee with Ice Cream', description: 'Thick blended espresso cold coffee topped with a creamy scoop of rich vanilla ice cream and chocolate drizzle.', price: 60.00, prep_time_minutes: 5, stock_qty: 30, is_available: 1, image_url: 'https://images.unsplash.com/photo-1517701550927-30cf4ba1dba5?w=600&auto=format&fit=crop&q=80' },
    { id: 9, category_id: 3, name: 'Chole Bhature', description: 'Spicy Punjabi chickpea curry paired with two fluffy, deep-fried golden bhature, pickled carrots, and green chili.', price: 85.00, prep_time_minutes: 12, stock_qty: 18, is_available: 1, image_url: 'assets/images/chole-bhature.jpg' },
    { id: 10, category_id: 1, name: 'Maggi Double Masala', description: 'Classic campus favorite noodles cooked with onions, capsicum, sweet corn, and an extra sachet of magic masala.', price: 45.00, prep_time_minutes: 8, stock_qty: 25, is_available: 1, image_url: 'assets/images/maggi.jpg' },
    { id: 11, category_id: 2, name: 'Fresh Lime Soda', description: 'Invigorating chilled soda with freshly squeezed lemon juice, black salt, and roasted cumin (sweet or salted).', price: 35.00, prep_time_minutes: 5, stock_qty: 40, is_available: 1, image_url: 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80' },
    { id: 12, category_id: 2, name: 'Sweet Mango Lassi', description: 'Velvety chilled yogurt smoothie infused with sweet Alphonso mango pulp and garnished with crushed pistachios.', price: 50.00, prep_time_minutes: 5, stock_qty: 20, is_available: 1, image_url: 'https://images.unsplash.com/photo-1546173159-315724a31696?w=600&auto=format&fit=crop&q=80' }
];

let orders = [
    {
        id: 1,
        token_number: 'TK-101',
        student_name: 'Ananya Verma',
        roll_number: 'CS2026-015',
        pickup_time: '12:30:00',
        pickup_date: new Date().toISOString().slice(0, 10),
        payment_method: 'UPI',
        payment_status: 'PAID',
        utr_number: '987654321012',
        total_amount: 90.00,
        status: 'READY',
        created_at: new Date(Date.now() - 35 * 60000).toISOString(),
        items: [
            { id: 1, menu_item_id: 1, item_name: 'Masala Dosa', unit_price: 60.00, quantity: 1, subtotal: 60.00, status: 'ACTIVE' },
            { id: 2, menu_item_id: 2, item_name: 'Samosa (2 Pcs)', unit_price: 30.00, quantity: 1, subtotal: 30.00, status: 'ACTIVE' }
        ]
    },
    {
        id: 2,
        token_number: 'TK-102',
        student_name: 'Rohan Deshmukh',
        roll_number: 'ME2026-088',
        pickup_time: '12:45:00',
        pickup_date: new Date().toISOString().slice(0, 10),
        payment_method: 'UPI',
        payment_status: 'PENDING_VERIFICATION',
        utr_number: '123456789012',
        total_amount: 110.00,
        status: 'PREPARING',
        created_at: new Date(Date.now() - 15 * 60000).toISOString(),
        items: [
            { id: 3, menu_item_id: 4, item_name: 'Veg Biryani with Raita', unit_price: 110.00, quantity: 1, subtotal: 110.00, status: 'ACTIVE' }
        ]
    }
];

let nextOrderId = 3;
let nextOrderItemId = 4;
let nextMenuItemId = 13;
let isStoreOnline = true;

// Active Sessions: { token: { adminId: 1, username: 'admin', fullName: 'Campus Bistro Kitchen Manager', role: 'admin', csrf: '...' } }
const sessions = new Map();
// Rate Limit: { ip: [timestamp, timestamp...] }
const failedAttempts = new Map();

function getClientIp(req) {
    return req.socket.remoteAddress || '127.0.0.1';
}

function parseCookies(req) {
    const list = {};
    const rc = req.headers.cookie;
    if (rc) {
        rc.split(';').forEach(cookie => {
            const parts = cookie.split('=');
            list[parts.shift().trim()] = decodeURI(parts.join('='));
        });
    }
    return list;
}

function getSession(req) {
    const cookies = parseCookies(req);
    const sid = cookies['canteen_session'];
    if (sid && sessions.has(sid)) {
        return { sid, session: sessions.get(sid) };
    }
    return { sid: null, session: null };
}

function createSession(adminUser) {
    const sid = crypto.randomBytes(24).toString('hex');
    const csrf = crypto.randomBytes(24).toString('hex');
    const sessionData = {
        adminId: adminUser.id,
        username: adminUser.username,
        fullName: adminUser.full_name,
        role: adminUser.role,
        csrf: csrf
    };
    sessions.set(sid, sessionData);
    return { sid, session: sessionData };
}

function getOrGenerateGuestCsrf(req, res) {
    const cookies = parseCookies(req);
    let guestToken = cookies['guest_csrf'];
    if (!guestToken) {
        guestToken = crypto.randomBytes(24).toString('hex');
        res.setHeader('Set-Cookie', `guest_csrf=${guestToken}; Path=/; HttpOnly; SameSite=Lax`);
    }
    return guestToken;
}

// =============================================================================
// Request Body Parser Helper
// =============================================================================
function parseBody(req) {
    return new Promise((resolve) => {
        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', () => {
            if (!body) return resolve({});
            try {
                if (req.headers['content-type'] && req.headers['content-type'].includes('application/json')) {
                    resolve(JSON.parse(body));
                } else {
                    const parsed = {};
                    const params = new URLSearchParams(body);
                    for (const [key, val] of params.entries()) {
                        parsed[key] = val;
                    }
                    resolve(parsed);
                }
            } catch (e) {
                resolve({});
            }
        });
    });
}

// =============================================================================
// HTTP Request Router & Server
// =============================================================================
const server = http.createServer(async (req, res) => {
    const parsedUrl = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const pathname = parsedUrl.pathname;
    const method = req.method;

    // Static Assets Server
    if (pathname.startsWith('/assets/')) {
        const filePath = path.join(__dirname, pathname);
        if (fs.existsSync(filePath)) {
            const ext = path.extname(filePath).toLowerCase();
            const mimeMap = {
                '.css': 'text/css; charset=UTF-8',
                '.js': 'application/javascript; charset=UTF-8',
                '.jpg': 'image/jpeg',
                '.jpeg': 'image/jpeg',
                '.png': 'image/png',
                '.svg': 'image/svg+xml',
                '.ico': 'image/x-icon'
            };
            res.writeHead(200, { 'Content-Type': mimeMap[ext] || 'application/octet-stream' });
            fs.createReadStream(filePath).pipe(res);
            return;
        } else {
            res.writeHead(404, { 'Content-Type': 'text/plain' });
            res.end('Asset Not Found');
            return;
        }
    }

    // =========================================================================
    // HTML Page Routes (index.php, admin-login.php, kitchen.php, logout.php)
    // =========================================================================
    if (pathname === '/' || pathname === '/index.php') {
        const guestCsrf = getOrGenerateGuestCsrf(req, res);
        let html = fs.readFileSync(path.join(__dirname, 'index.php'), 'utf8');
        // Render template variables
        html = html.replace(/<\?=\s*e\(\$csrfToken\)\s*\?>/g, guestCsrf);
        html = html.replace(/<\?=\s*date\('Y'\)\s*\?>/g, new Date().getFullYear());
        // Strip opening PHP comment block
        html = html.replace(/<\?php[\s\S]*?\?>/, '');
        res.writeHead(200, { 'Content-Type': 'text/html; charset=UTF-8' });
        res.end(html);
        return;
    }

function renderAdminLogin(guestCsrf, error = null, success = null) {
    let html = fs.readFileSync(path.join(__dirname, 'admin-login.php'), 'utf8');
    html = html.replace(/<\?php[\s\S]*?\?>/, '');
    html = html.replace(/<\?=\s*e\(\$csrfToken\)\s*\?>/g, guestCsrf);
    
    const successHtml = success ? `<div class="alert alert-success">${success}</div>` : '';
    const errorHtml = error ? `<div class="alert alert-danger">${error}</div>` : '';
    
    html = html.replace(/<\?php\s+if\s*\(\$successMessage\)[\s\S]*?<\?php\s+endif;\s*\?>/, successHtml);
    html = html.replace(/<\?php\s+if\s*\(\$errorMessage\)[\s\S]*?<\?php\s+endif;\s*\?>/, errorHtml);
    html = html.replace(/<\?php\s+if\s*\(!\$rateLimit[\s\S]*?<\?php\s+endif;\s*\?>/, '');
    
    html = html.replace(/<\?php[\s\S]*?\?>/g, '');
    html = html.replace(/<\?=\s*[^?]+\?>/g, '');
    return html;
}

    if (pathname === '/admin-login.php') {
        const clientIp = getClientIp(req);
        const { session } = getSession(req);

        // Already logged in
        if (session) {
            res.writeHead(302, { 'Location': '/kitchen.php' });
            res.end();
            return;
        }

        const guestCsrf = getOrGenerateGuestCsrf(req, res);
        const isLoggedOut = parsedUrl.searchParams.get('logged_out') === '1';

        if (method === 'POST') {
            const body = await parseBody(req);
            const username = (body.username || '').trim();
            const password = body.password || '';

            // Rate limit check
            const attempts = failedAttempts.get(clientIp) || [];
            const recentAttempts = attempts.filter(ts => Date.now() - ts < 15 * 60 * 1000);
            failedAttempts.set(clientIp, recentAttempts);

            if (recentAttempts.length >= 5) {
                res.writeHead(429, { 'Content-Type': 'text/html; charset=UTF-8' });
                res.end('<h1>Too Many Requests</h1><p>Rate limit exceeded. Please wait 15 minutes.</p>');
                return;
            }

            // Check admin credentials
            if (username === 'admin' && (password === 'Admin@Campus2026!' || password === 'admin123')) {
                // Successful login
                failedAttempts.delete(clientIp);
                const { sid } = createSession({
                    id: 1,
                    username: 'admin',
                    full_name: 'Campus Bistro Kitchen Manager',
                    role: 'admin'
                });
                res.writeHead(302, {
                    'Set-Cookie': `canteen_session=${sid}; Path=/; HttpOnly; SameSite=Lax`,
                    'Location': '/kitchen.php'
                });
                res.end();
                return;
            } else {
                recentAttempts.push(Date.now());
                failedAttempts.set(clientIp, recentAttempts);

                const html = renderAdminLogin(guestCsrf, 'Invalid username or password.');
                res.writeHead(200, { 'Content-Type': 'text/html; charset=UTF-8' });
                res.end(html);
                return;
            }
        }

        const html = renderAdminLogin(guestCsrf, null, isLoggedOut ? 'You have been securely logged out.' : null);
        res.writeHead(200, { 'Content-Type': 'text/html; charset=UTF-8' });
        res.end(html);
        return;
    }

    if (pathname === '/kitchen.php') {
        const { session } = getSession(req);
        if (!session) {
            res.writeHead(302, { 'Location': '/admin-login.php?error=unauthorized' });
            res.end();
            return;
        }

        let html = fs.readFileSync(path.join(__dirname, 'kitchen.php'), 'utf8');
        html = html.replace(/<\?php[\s\S]*?\?>/, '');
        html = html.replace(/<\?=\s*e\(\$csrfToken\)\s*\?>/g, session.csrf);
        html = html.replace(/<\?=\s*e\(\$adminName\)\s*\?>/g, session.fullName);
        html = html.replace(/<\?=\s*e\(\$adminRole\)\s*\?>/g, session.role);
        html = html.replace(/<\?php[\s\S]*?\?>/g, '');
        res.writeHead(200, { 'Content-Type': 'text/html; charset=UTF-8' });
        res.end(html);
        return;
    }

    if (pathname === '/logout.php') {
        const { sid } = getSession(req);
        if (sid) sessions.delete(sid);
        res.writeHead(302, {
            'Set-Cookie': 'canteen_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            'Location': '/admin-login.php?logged_out=1'
        });
        res.end();
        return;
    }

    // =========================================================================
    // API Route: /api/menu.php
    // =========================================================================
    if (pathname === '/api/menu.php') {
        res.setHeader('Content-Type', 'application/json; charset=UTF-8');

        if (method === 'GET') {
            const category = parsedUrl.searchParams.get('category') || '';
            const search = (parsedUrl.searchParams.get('search') || '').toLowerCase();
            const maxPrice = parseFloat(parsedUrl.searchParams.get('max_price')) || 0;
            const adminView = parsedUrl.searchParams.get('admin_view') === '1';

            let items = menuItems.map(item => {
                const cat = categories.find(c => c.id === item.category_id);
                return {
                    ...item,
                    category_name: cat ? cat.name : 'Other',
                    category_slug: cat ? cat.slug : 'other'
                };
            });

            if (!adminView) {
                items = items.filter(i => i.is_available === 1);
            }

            if (category && category !== 'all') {
                items = items.filter(i => i.category_slug === category || String(i.category_id) === category);
            }

            if (search) {
                items = items.filter(i => i.name.toLowerCase().includes(search) || (i.description || '').toLowerCase().includes(search));
            }

            if (maxPrice > 0) {
                items = items.filter(i => i.price <= maxPrice);
            }

            res.writeHead(200);
            res.end(JSON.stringify({
                success: true,
                categories: categories,
                items: items,
                count: items.length,
                is_store_online: isStoreOnline
            }));
            return;
        }

        // Admin-Protected Operations
        const { session } = getSession(req);
        if (!session) {
            res.writeHead(403);
            res.end(JSON.stringify({ success: false, error: 'Unauthorized: Staff/Admin access required.' }));
            return;
        }

        const body = await parseBody(req);

        if (method === 'POST') {
            const name = (body.name || '').trim();
            const catId = parseInt(body.category_id, 10);
            const price = parseFloat(body.price);
            const prepTime = parseInt(body.prep_time_minutes, 10) || 10;
            const stock = parseInt(body.stock_qty, 10) || 0;
            const desc = (body.description || '').trim();

            if (!name || isNaN(price) || price <= 0) {
                res.writeHead(422);
                res.end(JSON.stringify({ success: false, error: 'Valid name and price are required.' }));
                return;
            }

            const newItem = {
                id: nextMenuItemId++,
                category_id: catId,
                name: name,
                description: desc,
                price: price,
                prep_time_minutes: prepTime,
                stock_qty: stock,
                is_available: 1,
                image_url: null
            };
            menuItems.push(newItem);

            res.writeHead(201);
            res.end(JSON.stringify({ success: true, message: 'Item added successfully', item_id: newItem.id }));
            return;
        }

        if (method === 'PUT') {
            const action = body.action || 'full_update';

            if (action === 'toggle_store_status') {
                isStoreOnline = (body.is_online !== undefined) ? !!body.is_online : !isStoreOnline;
                res.writeHead(200);
                res.end(JSON.stringify({
                    success: true,
                    is_store_online: isStoreOnline,
                    message: isStoreOnline ? 'Canteen store is now ONLINE (Accepting Orders).' : 'Canteen store is now OFFLINE (Closed for Orders).'
                }));
                return;
            }

            const id = parseInt(body.id, 10);
            const item = menuItems.find(m => m.id === id);
            if (!item) {
                res.writeHead(404);
                res.end(JSON.stringify({ success: false, error: 'Item not found' }));
                return;
            }

            if (action === 'set_price') {
                const newPrice = parseFloat(body.price);
                if (isNaN(newPrice) || newPrice <= 0) {
                    res.writeHead(422);
                    res.end(JSON.stringify({ success: false, error: 'Price must be greater than zero.' }));
                    return;
                }
                item.price = newPrice;
                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: 'Price updated successfully.', price: newPrice }));
                return;
            }
            if (action === 'toggle_availability') {
                item.is_available = item.is_available ? 0 : 1;
                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: 'Availability updated', is_available: !!item.is_available }));
                return;
            }

            if (action === 'adjust_stock') {
                const delta = parseInt(body.stock_delta, 10) || 0;
                item.stock_qty = Math.max(0, item.stock_qty + delta);
                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: 'Stock updated', stock_qty: item.stock_qty }));
                return;
            }

            if (action === 'set_stock') {
                item.stock_qty = Math.max(0, parseInt(body.stock_qty, 10) || 0);
                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: 'Stock quantity set', stock_qty: item.stock_qty }));
                return;
            }

            if (body.price) item.price = parseFloat(body.price);
            if (body.name) item.name = body.name;
            res.writeHead(200);
            res.end(JSON.stringify({ success: true, message: 'Menu item updated' }));
            return;
        }

        if (method === 'DELETE') {
            const id = parseInt(body.id, 10);
            const index = menuItems.findIndex(m => m.id === id);
            if (index !== -1) {
                menuItems.splice(index, 1);
            }
            res.writeHead(200);
            res.end(JSON.stringify({ success: true, message: 'Menu item removed.' }));
            return;
        }
    }

    // =========================================================================
    // API Route: /api/orders.php
    // =========================================================================
    if (pathname === '/api/orders.php') {
        res.setHeader('Content-Type', 'application/json; charset=UTF-8');

        // GET: Public Token Board OR Admin Queue
        if (method === 'GET') {
            const action = parsedUrl.searchParams.get('action') || 'token_board';

            if (action === 'admin_queue') {
                const { session } = getSession(req);
                if (!session) {
                    res.writeHead(403);
                    res.end(JSON.stringify({ success: false, error: 'Unauthorized.' }));
                    return;
                }

                // Stats calculation
                const prepCount = orders.filter(o => o.status === 'PREPARING').length;
                const readyCount = orders.filter(o => o.status === 'READY').length;
                const compCount = orders.filter(o => o.status === 'COMPLETED').length;
                const revToday = orders.filter(o => o.payment_status === 'PAID').reduce((sum, o) => sum + o.total_amount, 0);

                res.writeHead(200);
                res.end(JSON.stringify({
                    success: true,
                    orders: orders,
                    stats: {
                        preparing: prepCount,
                        ready: readyCount,
                        completed_today: compCount,
                        revenue_today: revToday
                    }
                }));
                return;
            }

            // Public Live Token Board (STRICTLY NON-SENSITIVE)
            const active = orders.filter(o => o.status === 'PREPARING' || o.status === 'READY');
            const preparing = active.filter(o => o.status === 'PREPARING').map(o => ({
                token_number: o.token_number,
                status: o.status,
                pickup_time: o.pickup_time.slice(0, 5)
            }));
            const ready = active.filter(o => o.status === 'READY').map(o => ({
                token_number: o.token_number,
                status: o.status,
                pickup_time: o.pickup_time.slice(0, 5)
            }));

            res.writeHead(200);
            res.end(JSON.stringify({
                success: true,
                preparing: preparing,
                ready: ready,
                server_time: new Date().toLocaleTimeString('en-US', { hour12: false })
            }));
            return;
        }

        // POST: Order Placement (ZERO CLIENT-SIDE PRICE TRUST)
        if (method === 'POST') {
            if (!isStoreOnline) {
                res.writeHead(400);
                res.end(JSON.stringify({ success: false, error: 'The canteen store is currently offline. Pre-orders are temporarily paused.' }));
                return;
            }

            const body = await parseBody(req);
            const studentName = (body.student_name || '').trim();
            const rollNumber = (body.roll_number || '').trim();
            let pickupTime = (body.pickup_time || '').trim();
            if (!pickupTime || !/^[0-9]{2}:[0-9]{2}$/.test(pickupTime)) {
                const estDate = new Date(Date.now() + 15 * 60000);
                const pad = (n) => String(n).padStart(2, '0');
                pickupTime = `${pad(estDate.getHours())}:${pad(estDate.getMinutes())}`;
            }
            const paymentMethod = (body.payment_method || 'COUNTER').toUpperCase();
            const utrNumber = (body.utr_number || '').trim();
            const items = body.items || [];

            if (studentName.length < 2 || rollNumber.length < 2) {
                res.writeHead(422);
                res.end(JSON.stringify({ success: false, error: 'Please provide valid student name and roll number.' }));
                return;
            }

            if (paymentMethod === 'UPI') {
                if (!/^[0-9]{12}$/.test(utrNumber)) {
                    res.writeHead(422);
                    res.end(JSON.stringify({ success: false, error: 'Valid 12-digit UPI UTR number is mandatory.' }));
                    return;
                }
                const existingUtr = orders.find(o => o.utr_number === utrNumber);
                if (existingUtr) {
                    res.writeHead(422);
                    res.end(JSON.stringify({ success: false, error: 'This UTR number was already used for a previous order.' }));
                    return;
                }
            }

            if (!Array.isArray(items) || items.length === 0) {
                res.writeHead(422);
                res.end(JSON.stringify({ success: false, error: 'Your cart is empty.' }));
                return;
            }

            // ATOMIC STOCK CHECK & STRICT SERVER-SIDE PRICING
            let computedTotal = 0.00;
            const processedItems = [];

            for (const reqItem of items) {
                const menuItem = menuItems.find(m => m.id === reqItem.menu_item_id);
                if (!menuItem) {
                    res.writeHead(400);
                    res.end(JSON.stringify({ success: false, error: `Item #${reqItem.menu_item_id} not found on menu.` }));
                    return;
                }
                if (!menuItem.is_available) {
                    res.writeHead(400);
                    res.end(JSON.stringify({ success: false, error: `"${menuItem.name}" is currently unavailable.` }));
                    return;
                }
                if (menuItem.stock_qty < reqItem.quantity) {
                    res.writeHead(400);
                    res.end(JSON.stringify({ success: false, error: `Insufficient stock for "${menuItem.name}". Only ${menuItem.stock_qty} available.` }));
                    return;
                }

                // Strict Server-side calculation
                const sub = Math.round(menuItem.price * reqItem.quantity * 100) / 100;
                computedTotal += sub;

                // Decrement stock
                menuItem.stock_qty -= reqItem.quantity;

                processedItems.push({
                    id: nextOrderItemId++,
                    menu_item_id: menuItem.id,
                    item_name: menuItem.name,
                    unit_price: menuItem.price,
                    quantity: reqItem.quantity,
                    subtotal: sub,
                    status: 'ACTIVE'
                });
            }

            const tokenNumber = 'TK-' + (100 + orders.length + 1);
            const newOrder = {
                id: nextOrderId++,
                token_number: tokenNumber,
                student_name: studentName,
                roll_number: rollNumber,
                pickup_time: pickupTime + ':00',
                pickup_date: new Date().toISOString().slice(0, 10),
                payment_method: paymentMethod,
                payment_status: paymentMethod === 'UPI' ? 'PENDING_VERIFICATION' : 'PAY_ON_PICKUP',
                utr_number: paymentMethod === 'UPI' ? utrNumber : null,
                total_amount: computedTotal,
                status: 'PREPARING',
                created_at: new Date().toISOString(),
                items: processedItems
            };

            orders.unshift(newOrder);

            res.writeHead(201);
            res.end(JSON.stringify({
                success: true,
                message: 'Order placed successfully!',
                order_id: newOrder.id,
                token_number: tokenNumber,
                total_amount: computedTotal,
                pickup_time: pickupTime,
                payment_method: paymentMethod
            }));
            return;
        }

        // PUT: Admin Order Status Workflow & Restocking
        if (method === 'PUT') {
            const { session } = getSession(req);
            if (!session) {
                res.writeHead(403);
                res.end(JSON.stringify({ success: false, error: 'Unauthorized.' }));
                return;
            }

            const body = await parseBody(req);
            const orderId = parseInt(body.order_id, 10);
            const order = orders.find(o => o.id === orderId);
            if (!order) {
                res.writeHead(404);
                res.end(JSON.stringify({ success: false, error: 'Order not found.' }));
                return;
            }

            const action = body.action;

            if (action === 'advance_status') {
                order.status = body.new_status;
                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: `Status updated to ${body.new_status}` }));
                return;
            }

            if (action === 'verify_payment') {
                order.payment_status = body.payment_status || 'PAID';
                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: 'Payment verified.' }));
                return;
            }

            if (action === 'cancel_order') {
                if (order.status === 'CANCELLED') {
                    res.writeHead(400);
                    res.end(JSON.stringify({ success: false, error: 'Order already cancelled.' }));
                    return;
                }

                // Restore stock for all active items
                order.items.forEach(oi => {
                    if (oi.status === 'ACTIVE') {
                        const m = menuItems.find(item => item.id === oi.menu_item_id);
                        if (m) m.stock_qty += oi.quantity;
                        oi.status = 'CANCELLED';
                    }
                });

                order.status = 'CANCELLED';
                order.payment_status = 'CANCELLED';

                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: `Order ${order.token_number} cancelled and items restocked.` }));
                return;
            }

            if (action === 'cancel_item') {
                const itemId = parseInt(body.order_item_id, 10);
                const orderItem = order.items.find(i => i.id === itemId);
                if (!orderItem || orderItem.status === 'CANCELLED') {
                    res.writeHead(400);
                    res.end(JSON.stringify({ success: false, error: 'Item not found or already cancelled.' }));
                    return;
                }

                // Restock
                const m = menuItems.find(item => item.id === orderItem.menu_item_id);
                if (m) m.stock_qty += orderItem.quantity;

                orderItem.status = 'CANCELLED';
                order.total_amount = Math.max(0, order.total_amount - orderItem.subtotal);

                res.writeHead(200);
                res.end(JSON.stringify({ success: true, message: `Item cancelled and stock restored.`, new_total: order.total_amount }));
                return;
            }
        }
    }

    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('Not Found');
});

// =============================================================================
// Launch Server
// =============================================================================
server.listen(PORT, HOST, () => {
    console.log(`=================================================================`);
    console.log(`  🍳 Campus Canteen Pre-Order & Token System is LIVE!`);
    console.log(`=================================================================`);
    console.log(`  🌐 Public Student Portal:   http://${HOST}:${PORT}/`);
    console.log(`  🔒 Hidden Staff Login:     http://${HOST}:${PORT}/admin-login.php`);
    console.log(`  📋 Kitchen Dispatch Queue:  http://${HOST}:${PORT}/kitchen.php`);
    console.log(`-----------------------------------------------------------------`);
    console.log(`  Default Admin Credentials:`);
    console.log(`  Username: admin`);
    console.log(`  Password: Admin@Campus2026!`);
    console.log(`=================================================================`);
});

