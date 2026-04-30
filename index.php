<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Miko\Router;
use Miko\Auth;
use Miko\Response;
use Miko\Database;
use Miko\Request;
use Miko\Session;

Session::start();

// Suppress HTML errors in API responses
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with($uri, '/api/')) {
    ini_set('display_errors', '0');
}

$router = Router::init();

$router->before('GET|POST|PUT|DELETE', '/api/.*', function () {
    $storeHeader = $_SERVER['HTTP_X_STORE_ID'] ?? $_SERVER['X-Store-Id'] ?? '';
    if ($storeHeader) Auth::setRequestStoreId((int) $storeHeader);

    $publicPaths = ['/api/auth/login', '/api/auth/register'];
    $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (!in_array($currentUri, $publicPaths) && !Auth::check()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
});

// SPA - serve on /app
$router->get('/app', function () {
    require __DIR__ . '/views/spa.php';
});

// Redirect page routes to SPA (but not API routes)
// All unmatched routes redirect to SPA

$router->get('/login', function () {
    if (Auth::check()) Response::redirect('/');
    Response::page('Login', 'auth/login');
});

$router->get('/register', function () {
    if (Auth::check()) Response::redirect('/');
    Response::page('Register', 'auth/register');
});

$router->get('/pos', function () {
    Auth::requireAuth();
    Response::page('POS - Point of Sale', 'pos/index');
});

$router->get('/products', function () {
    Auth::requireAuth();
    Response::page('Products', 'products/index');
});

$router->get('/products/create', function () {
    Auth::requireAuth();
    Response::page('Add Product', 'products/form');
});

$router->get('/products/(\d+)/edit', function (int $id) {
    Auth::requireAuth();
    Response::page('Edit Product', 'products/form', ['productId' => $id]);
});

$router->get('/categories', function () {
    Auth::requireAuth();
    Response::page('Categories', 'categories/index');
});

$router->get('/customers', function () {
    Auth::requireAuth();
    Response::page('Customers', 'customers/index');
});

$router->get('/sales', function () {
    Auth::requireAuth();
    Response::page('Sales History', 'sales/index');
});

$router->get('/sales/(\d+)', function (int $id) {
    Auth::requireAuth();
    Response::page('Sale Details', 'sales/show', ['saleId' => $id]);
});

$router->get('/reports', function () {
    Auth::requireAuth();
    Response::page('Reports', 'dashboard/reports');
});

$router->get('/logout', function () {
    Auth::logout();
    Response::redirect('/login');
});

$router->get('/app', function () {
    require __DIR__ . '/views/spa.php';
});

// API: Auth
$router->post('/api/auth/login', function () {
    $email = Request::input('email');
    $password = Request::input('password');

    if (!$email || !$password) {
        Response::error('Email and password are required');
    }

    $user = Auth::attempt($email, $password);
    if (!$user) {
        Response::error('Invalid email or password', 401);
    }

    $stores = Database::fetchAll(
        'SELECT s.* FROM stores s
         JOIN user_stores us ON us.store_id = s.id
         WHERE us.user_id = :user_id AND s.is_active = true',
        ['user_id' => $user['id']]
    );

    $storeId = null;
    $storeName = null;
    if (count($stores) === 1) {
        $storeId = $stores[0]['id'];
        $storeName = $stores[0]['name'];
        Session::set('store_id', $storeId);
        Session::set('store_name', $storeName);
    }

    $token = \Miko\JWTAuth::encode([
        'user_id'     => $user['id'],
        'tenant_id'   => $user['tenant_id'],
        'role'        => $user['role'],
        'tenant_name' => $user['tenant_name'],
        'store_id'    => $storeId,
        'store_name'  => $storeName,
    ]);

    Response::success([
        'token' => $token,
        'user' => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
        'tenant' => [
            'name' => $user['tenant_name'],
            'slug' => $user['tenant_slug'],
        ],
        'stores' => $stores,
        'store'  => count($stores) === 1 ? $stores[0] : null,
    ], 'Login successful');
});

$router->post('/api/auth/register', function () {
    $data = Request::all();

    $errors = Request::validate([
        'tenant_name' => 'required|min:1|max:255',
        'name'        => 'required|min:1|max:255',
        'email'       => 'required|email',
        'password'    => 'required|min:6',
    ]);

    if (!empty($errors)) {
        Response::error('Validation failed', 422, $errors);
    }

    $existingTenant = Database::fetch(
        'SELECT id FROM tenants WHERE slug = :slug',
        ['slug' => strtolower(str_replace(' ', '-', $data['tenant_name']))]
    );

    if ($existingTenant) {
        Response::error('Business name already registered', 422);
    }

    $existingUser = Database::fetch(
        'SELECT id FROM users WHERE email = :email',
        ['email' => $data['email']]
    );

    if ($existingUser) {
        Response::error('Email already registered', 422);
    }

    Database::getInstance()->getConnection()->beginTransaction();

    try {
        $slug = strtolower(str_replace(' ', '-', $data['tenant_name'])) . '-' . uniqid();

        $tenantId = Database::insert('tenants', [
            'name'  => $data['tenant_name'],
            'slug'  => $slug,
            'email' => $data['email'],
        ]);

        $userId = Database::insert('users', [
            'tenant_id' => $tenantId,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'      => 'admin',
        ]);

        $storeId = Database::insert('stores', [
            'tenant_id' => $tenantId,
            'name'      => $data['tenant_name'] . ' Main',
            'code'      => 'MAIN',
        ]);

        Database::insert('user_stores', [
            'user_id'  => $userId,
            'store_id' => $storeId,
        ]);

        Database::getInstance()->getConnection()->commit();

        Auth::attempt($data['email'], $data['password']);
        Session::set('store_id', $storeId);
        Session::set('store_name', $data['tenant_name'] . ' Main');

        $token = \Miko\JWTAuth::encode([
            'user_id'     => $userId,
            'tenant_id'   => $tenantId,
            'role'        => 'admin',
            'tenant_name' => $data['tenant_name'],
            'store_id'    => $storeId,
            'store_name'  => $data['tenant_name'] . ' Main',
        ]);

        Response::success([
            'token'  => $token,
            'user'   => ['id' => $userId, 'name' => $data['name'], 'email' => $data['email'], 'role' => 'admin'],
            'tenant' => ['name' => $data['tenant_name'], 'slug' => $slug],
            'store'  => ['id' => $storeId, 'name' => $data['tenant_name'] . ' Main'],
        ], 'Registration successful');
    } catch (\Exception $e) {
        Database::getInstance()->getConnection()->rollBack();
        Response::error('Registration failed: ' . $e->getMessage(), 500);
    }
});

$router->get('/api/auth/me', function () {
    Auth::requireAuth();
    $user = Auth::user();

    $stores = Database::fetchAll(
        'SELECT s.* FROM stores s
         JOIN user_stores us ON us.store_id = s.id
         WHERE us.user_id = :user_id AND s.is_active = true',
        ['user_id' => Auth::id()]
    );

    Response::success([
        'user'   => $user,
        'tenant' => ['name' => Auth::tenantName()],
        'stores' => $stores,
        'store'  => Auth::hasStore() ? ['id' => Auth::storeId(), 'name' => Auth::storeName()] : null,
    ]);
});

// API: Dashboard
$router->get('/api/dashboard/stats', function () {
    Auth::requireAuth();
    $tenantId = Auth::tenantId();

    $today = date('Y-m-d');
    $currentMonth = date('Y-m');

    $todaySales = Database::fetch(
        "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total
         FROM sales WHERE tenant_id = :tenant_id AND created_at::date = :today AND status = 'completed'",
        ['tenant_id' => $tenantId, 'today' => $today]
    );

    $monthSales = Database::fetch(
        "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total
         FROM sales WHERE tenant_id = :tenant_id AND to_char(created_at, 'YYYY-MM') = :month AND status = 'completed'",
        ['tenant_id' => $tenantId, 'month' => $currentMonth]
    );

    $productCount = Database::fetch(
        'SELECT COUNT(*) as count FROM products WHERE tenant_id = :tenant_id',
        ['tenant_id' => $tenantId]
    );

    $customerCount = Database::fetch(
        'SELECT COUNT(*) as count FROM customers WHERE tenant_id = :tenant_id',
        ['tenant_id' => $tenantId]
    );

    $storeId = Auth::storeId();

    if ($storeId) {
        $lowStock = Database::fetchAll(
            'SELECT p.id, p.name, p.sku, ps.stock, ps.min_stock
             FROM products p
             JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
             WHERE p.tenant_id = :tenant_id AND p.is_active = true AND ps.stock <= ps.min_stock
             ORDER BY ps.stock ASC LIMIT 5',
            ['tenant_id' => $tenantId, 'store_id' => $storeId]
        );

        $recentSales = Database::fetchAll(
            'SELECT s.id, s.invoice_no, s.total, s.created_at, c.name as customer_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.tenant_id = :tenant_id AND s.store_id = :store_id
             ORDER BY s.created_at DESC LIMIT 5',
            ['tenant_id' => $tenantId, 'store_id' => $storeId]
        );
    } else {
        $lowStock = [];
        $recentSales = [];
    }

    Response::success([
        'today_sales'     => $todaySales,
        'month_sales'     => $monthSales,
        'product_count'   => $productCount['count'] ?? 0,
        'customer_count'  => $customerCount['count'] ?? 0,
        'low_stock'       => $lowStock,
        'recent_sales'    => $recentSales,
    ]);
});

// API: Products search
$router->get('/api/products/search', function () {
    Auth::requireAuth();
    $q = Request::get('q', '');
    $storeId = Auth::storeId();

    if (!$q || !$storeId) {
        Response::success([]);
    }

    $products = Database::fetchAll(
        "SELECT p.id, p.name, p.sku, p.price, p.image, ps.stock
         FROM products p
         JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
         WHERE p.tenant_id = :tenant_id AND p.is_active = true AND ps.stock > 0
           AND (p.name ILIKE :q OR p.sku ILIKE :q OR p.barcode ILIKE :q)
         ORDER BY p.name LIMIT 20",
        ['tenant_id' => Auth::tenantId(), 'store_id' => $storeId, 'q' => "%{$q}%"]
    );

    Response::success($products);
});

// API: Barcode product lookup (local + Open Food Facts)
$router->get('/api/products/lookup', function () {
    Auth::requireAuth();
    $barcode = Request::get('barcode', '');

    if (!$barcode) {
        Response::error('Barcode is required');
    }

    $local = Database::fetch(
        'SELECT p.*, c.name as category_name FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.tenant_id = :tenant_id AND p.barcode = :barcode
         LIMIT 1',
        ['tenant_id' => Auth::tenantId(), 'barcode' => $barcode]
    );

    if ($local) {
        Response::success($local, 'Found locally');
    }

    if (!preg_match('/^\d{8,14}$/', $barcode)) {
        Response::success(null, 'Invalid barcode format for online lookup');
    }

    $url = 'https://world.openfoodfacts.org/api/v2/product/' . urlencode($barcode) . '.json';
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'MIKOPos/1.0']]);
    $response = @file_get_contents($url, false, $ctx);

    if ($response === false) {
        Response::success(null, 'Online lookup unavailable');
    }

    $data = json_decode($response, true);

    if (!$data || !($data['status'] ?? 0)) {
        Response::success(null, 'Product not found online');
    }

    $product = $data['product'] ?? [];

    $result = [
        'barcode'      => $barcode,
        'name'         => $product['product_name'] ?? $product['generic_name'] ?? '',
        'brand'        => $product['brands'] ?? '',
        'category'     => $product['categories'] ?? '',
        'category_name'=> $product['categories_hierarchy'][0] ?? '',
        'image'        => $product['image_url'] ?? ($product['selected_images']['front']['display']['xxhdpi'] ?? ''),
        'source'       => 'openfoodfacts',
        'quantity'     => $product['product_quantity'] ?? null,
    ];

    Response::success($result, 'Found via Open Food Facts');
});

// API: Sync endpoints
$router->get('/api/sync/init', function () {
    Auth::requireAuth();
    $tid = Auth::tenantId(); $sid = Auth::storeId();
    if (!$sid) Response::error('No store selected', 400);
    $store = Database::fetch('SELECT * FROM stores WHERE id=:i', ['i'=>$sid]);
    $categories = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
    $customers = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) as sale_count,(SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE s.customer_id=c.id) as total_spent FROM customers c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
    $products = Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true ORDER BY p.name", ['t'=>$tid, 's'=>$sid]);
    $allStores = Database::fetchAll('SELECT * FROM stores WHERE tenant_id=:t AND is_active=true', ['t'=>$tid]);
    Response::success(['store'=>$store,'categories'=>$categories,'customers'=>$customers,'products'=>$products,'stores'=>$allStores,'synced_at'=>date('c')]);
});
$router->get('/api/sync/products', function () {
    Auth::requireAuth();
    $tid = Auth::tenantId(); $sid = Auth::storeId(); $since = Request::get('since','');
    if (!$sid) Response::error('No store selected', 400);
    $p = ['t'=>$tid, 's'=>$sid]; $w = 'p.tenant_id=:t AND p.is_active=true';
    if ($since) { $w .= ' AND p.updated_at>:since'; $p['since'] = $since; }
    $products = Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE $w ORDER BY p.name", $p);
    Response::success(['products'=>$products, 'synced_at'=>date('c')]);
});
$router->post('/api/sync/sales', function () {
    Auth::requireAuth();
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $items = $data['items'] ?? []; if (!is_array($items) || !$items) Response::error('Cart is empty');
    $tid = Auth::tenantId(); $uid = Auth::id(); $sid = Auth::storeId();
    if (!$sid) Response::error('No store selected', 400);
    $pdo = Database::getInstance()->getConnection();
    try { $pdo->beginTransaction(); } catch (\Exception $e) { Response::error('TX: '.$e->getMessage(), 500); }
    try {
        $subtotal = 0; $saleItems = [];
        foreach ($items as $item) {
            $stmt = $pdo->prepare('SELECT p.id,p.name,p.price,ps.stock FROM products p JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.id=:i AND p.tenant_id=:t');
            $stmt->execute(['i'=>(int)$item['product_id'],'t'=>$tid,'s'=>$sid]);
            $prod = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$prod) throw new \Exception("Product not found");
            $qty = max(1, (int)($item['quantity']??1));
            $price = (float)($item['price']??$prod['price']); $st = $price*$qty; $subtotal += $st;
            $saleItems[] = ['product_id'=>$prod['id'],'product_name'=>$prod['name'],'quantity'=>$qty,'price'=>$price,'subtotal'=>$st];
            $upd = $pdo->prepare('UPDATE product_stocks SET stock=GREATEST(0, stock-:q) WHERE product_id=:i AND store_id=:s');
            $upd->execute(['q'=>$qty,'i'=>$prod['id'],'s'=>$sid]);
        }
        $disc = (float)($data['discount']??0); $tax = (float)($data['tax']??0);
        $total = $subtotal-$disc+$tax; $paid = (float)($data['amount_paid']??0); $change = max(0,$paid-$total);
        $inv = 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-6));
        $ins = $pdo->prepare("INSERT INTO sales (tenant_id,store_id,user_id,customer_id,invoice_no,subtotal,tax,discount,total,payment_method,amount_paid,change_amount,status,notes) VALUES (:t,:s,:u,:c,:inv,:sub,:tax,:disc,:total,:pm,:paid,:ch,'completed','') RETURNING id");
        $ins->execute(['t'=>$tid,'s'=>$sid,'u'=>$uid,'c'=>!empty($data['customer_id'])?(int)$data['customer_id']:null,'inv'=>$inv,'sub'=>$subtotal,'tax'=>$tax,'disc'=>$disc,'total'=>$total,'pm'=>$data['payment_method']??'cash','paid'=>$paid,'ch'=>$change]);
        $saleId = (int) $ins->fetchColumn();
        foreach ($saleItems as $si) {
            $i2 = $pdo->prepare("INSERT INTO sale_items (product_id,sale_id,product_name,quantity,price,subtotal) VALUES (:pid,:sid,:pn,:qty,:pr,:sub)");
            $i2->execute(['pid'=>$si['product_id'],'sid'=>$saleId,'pn'=>$si['product_name'],'qty'=>$si['quantity'],'pr'=>$si['price'],'sub'=>$si['subtotal']]);
        }
        $pdo->commit();
        $s = $pdo->prepare('SELECT s.*,u.name as cashier_name FROM sales s LEFT JOIN users u ON u.id=s.user_id WHERE s.id=:i');
        $s->execute(['i'=>$saleId]);
        $sale = $s->fetch(\PDO::FETCH_ASSOC);
        $sale['items'] = $saleItems; Response::success($sale, 'Sale synced');
    } catch (\Exception $e) { try { $pdo->rollBack(); } catch (\Exception $r) {} Response::error('Sync: '.$e->getMessage(), 422); }
});
$router->get('/api/sync/status', function () {
    Auth::requireAuth(); Response::success(['server_time'=>date('c'), 'db_connected'=>true]);
});

// API: POS init (bundled data)
$router->get('/api/pos/init', function () {
    Auth::requireAuth();
    $tid = Auth::tenantId(); $sid = Auth::storeId();
    if (!$sid) Response::error('No store selected', 400);

    $store = Database::fetch('SELECT * FROM stores WHERE id=:i', ['i'=>$sid]);
    $categories = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
    $customers = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) as sale_count,(SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE s.customer_id=c.id) as total_spent FROM customers c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
    $products = Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true ORDER BY p.name", ['t'=>$tid, 's'=>$sid]);
    $lowStock = Database::fetchAll('SELECT p.id,p.name,p.sku,ps.stock,ps.min_stock FROM products p JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true AND ps.stock<=ps.min_stock ORDER BY ps.stock LIMIT 5', ['t'=>$tid, 's'=>$sid]);

    Response::success(['store'=>$store,'categories'=>$categories,'customers'=>$customers,'products'=>$products,'low_stock'=>$lowStock,'token_check'=>time()]);
});

// API: Sale receipt
$router->get('/api/sales/(\d+)/receipt', function (int $id) {
    Auth::requireAuth();
    $sale = Database::fetch(
        'SELECT s.*, u.name as cashier_name, c.name as customer_name, c.phone as customer_phone, t.name as tenant_name, t.address as tenant_address, t.phone as tenant_phone
         FROM sales s
         LEFT JOIN users u ON u.id = s.user_id
         LEFT JOIN customers c ON c.id = s.customer_id
         JOIN tenants t ON t.id = s.tenant_id
         WHERE s.id = :id AND s.tenant_id = :tenant_id',
        ['id' => $id, 'tenant_id' => Auth::tenantId()]
    );

    if (!$sale) {
        Response::error('Sale not found', 404);
    }

    $sale['items'] = Database::fetchAll(
        'SELECT * FROM sale_items WHERE sale_id = :sale_id',
        ['sale_id' => $id]
    );

    Response::success($sale);
});

$router->get('/stores', function () {
    Auth::requireAuth();
    Response::page('Stores', 'stores/index');
});

Router::apiResource('/api/categories', 'Miko\\Controllers\\CategoryController');
Router::apiResource('/api/products', 'Miko\\Controllers\\ProductController');
Router::apiResource('/api/customers', 'Miko\\Controllers\\CustomerController');
Router::apiResource('/api/sales', 'Miko\\Controllers\\SaleController');

// Store API
$router->get('/api/stores', function () {
    $ctrl = new \Miko\Controllers\StoreController();
    $ctrl->all();
});
$router->get('/api/stores/mine', function () {
    $ctrl = new \Miko\Controllers\StoreController();
    $ctrl->index();
});
$router->post('/api/stores', function () {
    $ctrl = new \Miko\Controllers\StoreController();
    $ctrl->store();
});
$router->put('/api/stores/(\d+)', function (int $id) {
    $ctrl = new \Miko\Controllers\StoreController();
    $ctrl->update($id);
});
$router->post('/api/stores/switch', function () {
    $ctrl = new \Miko\Controllers\StoreController();
    $ctrl->switch();
});
$router->get('/api/stores/current', function () {
    $ctrl = new \Miko\Controllers\StoreController();
    $ctrl->current();
});

$router->set404(function () {
    Response::redirect('/app');
});

$router->run();
