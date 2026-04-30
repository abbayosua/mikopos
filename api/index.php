<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use Miko\Router;
use Miko\Auth;
use Miko\Response;
use Miko\Database;
use Miko\Request;
use Miko\Session;

Session::start();

$uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with($uri, '/api/')) {
    ini_set('display_errors', '0');
}

$router = Router::init();

$router->before('GET|POST|PUT|DELETE', '/.*', function () {
    $publicPaths = ['/login', '/register', '/api/auth/login', '/api/auth/register'];
    $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $storeHeader = $_SERVER['HTTP_X_STORE_ID'] ?? $_SERVER['X-Store-Id'] ?? '';
    if ($storeHeader) {
        Auth::setRequestStoreId((int) $storeHeader);
    }

    $isApi = str_starts_with($currentUri, '/api/');
    $isPublic = in_array($currentUri, $publicPaths);

    if (!$isApi && !$isPublic && !Auth::check()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
});

// Auth routes
$router->get('/api/debug', function () {
    Response::success(['routes_loaded' => true, 'uri' => $_SERVER['REQUEST_URI']]);
});

$router->post('/api/auth/login', function () {
    $data = Request::all();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (!$email || !$password) {
        Response::error('Email and password are required');
    }

    $user = Auth::attempt($email, $password);
    if (!$user) {
        Response::error('Invalid email or password', 401);
    }

    $stores = Database::fetchAll(
        'SELECT s.* FROM stores s JOIN user_stores us ON us.store_id = s.id WHERE us.user_id = :user_id AND s.is_active = true',
        ['user_id' => $user['id']]
    );

    $storeId = null; $storeName = null;
    if (count($stores) === 1) {
        $storeId = $stores[0]['id']; $storeName = $stores[0]['name'];
        Session::set('store_id', $storeId); Session::set('store_name', $storeName);
    }

    $token = \Miko\JWTAuth::encode([
        'user_id' => $user['id'], 'tenant_id' => $user['tenant_id'], 'role' => $user['role'],
        'tenant_name' => $user['tenant_name'], 'store_id' => $storeId, 'store_name' => $storeName,
    ]);

    Response::success([
        'token' => $token,
        'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']],
        'tenant' => ['name' => $user['tenant_name'], 'slug' => $user['tenant_slug']],
        'stores' => $stores, 'store' => count($stores) === 1 ? $stores[0] : null,
    ], 'Login successful');
});

$router->post('/api/auth/register', function () {
    $data = Request::all();
    $errors = Request::validate([
        'tenant_name' => 'required|min:1|max:255', 'name' => 'required|min:1|max:255',
        'email' => 'required|email', 'password' => 'required|min:6',
    ]);
    if (!empty($errors)) Response::error('Validation failed', 422, $errors);

    $existingTenant = Database::fetch('SELECT id FROM tenants WHERE slug = :slug', ['slug' => strtolower(str_replace(' ', '-', $data['tenant_name']))]);
    if ($existingTenant) Response::error('Business name already registered', 422);
    $existingUser = Database::fetch('SELECT id FROM users WHERE email = :email', ['email' => $data['email']]);
    if ($existingUser) Response::error('Email already registered', 422);

    $pdo = Database::getInstance()->getConnection(); $pdo->beginTransaction();
    try {
        $slug = strtolower(str_replace(' ', '-', $data['tenant_name'])) . '-' . uniqid();
        $tenantId = Database::insert('tenants', ['name' => $data['tenant_name'], 'slug' => $slug, 'email' => $data['email']]);
        $userId = Database::insert('users', ['tenant_id' => $tenantId, 'name' => $data['name'], 'email' => $data['email'], 'password' => password_hash($data['password'], PASSWORD_BCRYPT), 'role' => 'admin']);
        $storeId = Database::insert('stores', ['tenant_id' => $tenantId, 'name' => $data['tenant_name'] . ' Main', 'code' => 'MAIN']);
        Database::insert('user_stores', ['user_id' => $userId, 'store_id' => $storeId]);
        $pdo->commit();

        Auth::attempt($data['email'], $data['password']);
        Session::set('store_id', $storeId); Session::set('store_name', $data['tenant_name'] . ' Main');
        $token = \Miko\JWTAuth::encode(['user_id' => $userId, 'tenant_id' => $tenantId, 'role' => 'admin', 'tenant_name' => $data['tenant_name'], 'store_id' => $storeId, 'store_name' => $data['tenant_name'] . ' Main']);
        Response::success(['token' => $token, 'user' => ['id' => $userId, 'name' => $data['name'], 'email' => $data['email'], 'role' => 'admin'], 'tenant' => ['name' => $data['tenant_name'], 'slug' => $slug], 'store' => ['id' => $storeId, 'name' => $data['tenant_name'] . ' Main']], 'Registration successful');
    } catch (\Exception $e) { $pdo->rollBack(); Response::error('Registration failed: ' . $e->getMessage(), 500); }
});

$router->get('/api/auth/me', function () {
    Auth::requireAuth();
    $user = Auth::user();
    $stores = Database::fetchAll('SELECT s.* FROM stores s JOIN user_stores us ON us.store_id = s.id WHERE us.user_id = :user_id AND s.is_active = true', ['user_id' => Auth::id()]);
    Response::success(['user' => $user, 'tenant' => ['name' => Auth::tenantName()], 'stores' => $stores, 'store' => Auth::hasStore() ? ['id' => Auth::storeId(), 'name' => Auth::storeName()] : null]);
});

// Dashboard
$router->get('/api/dashboard/stats', function () {
    Auth::requireAuth();
    $tenantId = Auth::tenantId();
    $today = date('Y-m-d'); $currentMonth = date('Y-m');
    $todaySales = Database::fetch("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM sales WHERE tenant_id = :tenant_id AND created_at::date = :today AND status = 'completed'", ['tenant_id' => $tenantId, 'today' => $today]);
    $monthSales = Database::fetch("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM sales WHERE tenant_id = :tenant_id AND to_char(created_at, 'YYYY-MM') = :month AND status = 'completed'", ['tenant_id' => $tenantId, 'month' => $currentMonth]);
    $productCount = Database::fetch('SELECT COUNT(*) as count FROM products WHERE tenant_id = :tenant_id', ['tenant_id' => $tenantId]);
    $customerCount = Database::fetch('SELECT COUNT(*) as count FROM customers WHERE tenant_id = :tenant_id', ['tenant_id' => $tenantId]);
    $storeId = Auth::storeId();
    if ($storeId) {
        $lowStock = Database::fetchAll('SELECT p.id, p.name, p.sku, ps.stock, ps.min_stock FROM products p JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id WHERE p.tenant_id = :tenant_id AND p.is_active = true AND ps.stock <= ps.min_stock ORDER BY ps.stock ASC LIMIT 5', ['tenant_id' => $tenantId, 'store_id' => $storeId]);
        $recentSales = Database::fetchAll('SELECT s.id, s.invoice_no, s.total, s.created_at, c.name as customer_name FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.tenant_id = :tenant_id AND s.store_id = :store_id ORDER BY s.created_at DESC LIMIT 5', ['tenant_id' => $tenantId, 'store_id' => $storeId]);
    } else { $lowStock = []; $recentSales = []; }
    Response::success(['today_sales' => $todaySales, 'month_sales' => $monthSales, 'product_count' => $productCount['count'] ?? 0, 'customer_count' => $customerCount['count'] ?? 0, 'low_stock' => $lowStock, 'recent_sales' => $recentSales]);
});

// Products search
$router->get('/api/products/search', function () {
    Auth::requireAuth();
    $q = Request::get('q', ''); $storeId = Auth::storeId();
    if (!$q || !$storeId) { Response::success([]); }
    $products = Database::fetchAll("SELECT p.id, p.name, p.sku, p.price, p.image, ps.stock FROM products p JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id WHERE p.tenant_id = :tenant_id AND p.is_active = true AND ps.stock > 0 AND (p.name ILIKE :q OR p.sku ILIKE :q OR p.barcode ILIKE :q) ORDER BY p.name LIMIT 20", ['tenant_id' => Auth::tenantId(), 'store_id' => $storeId, 'q' => "%{$q}%"]);
    Response::success($products);
});

// Barcode lookup
$router->get('/api/products/lookup', function () {
    Auth::requireAuth();
    $barcode = Request::get('barcode', '');
    if (!$barcode) { Response::error('Barcode is required'); }
    $local = Database::fetch('SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.tenant_id = :tenant_id AND p.barcode = :barcode LIMIT 1', ['tenant_id' => Auth::tenantId(), 'barcode' => $barcode]);
    if ($local) { Response::success($local, 'Found locally'); }
    if (!preg_match('/^\d{8,14}$/', $barcode)) { Response::success(null, 'Invalid barcode format for online lookup'); }
    $url = 'https://world.openfoodfacts.org/api/v2/product/' . urlencode($barcode) . '.json';
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'MIKOPos/1.0']]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) { Response::success(null, 'Online lookup unavailable'); }
    $data = json_decode($response, true);
    if (!$data || !($data['status'] ?? 0)) { Response::success(null, 'Product not found online'); }
    $product = $data['product'] ?? [];
    Response::success(['barcode' => $barcode, 'name' => $product['product_name'] ?? $product['generic_name'] ?? '', 'brand' => $product['brands'] ?? '', 'category_name' => $product['categories_hierarchy'][0] ?? '', 'image' => $product['image_url'] ?? '', 'source' => 'openfoodfacts'], 'Found via Open Food Facts');
});

// Sale receipt
$router->get('/api/sales/(\d+)/receipt', function (int $id) {
    Auth::requireAuth();
    $sale = Database::fetch('SELECT s.*, u.name as cashier_name, c.name as customer_name, c.phone as customer_phone, t.name as tenant_name, t.address as tenant_address, t.phone as tenant_phone FROM sales s LEFT JOIN users u ON u.id = s.user_id LEFT JOIN customers c ON c.id = s.customer_id JOIN tenants t ON t.id = s.tenant_id WHERE s.id = :id AND s.tenant_id = :tenant_id', ['id' => $id, 'tenant_id' => Auth::tenantId()]);
    if (!$sale) { Response::error('Sale not found', 404); }
    $sale['items'] = Database::fetchAll('SELECT * FROM sale_items WHERE sale_id = :sale_id', ['sale_id' => $id]);
    Response::success($sale);
});

// Store routes
$router->get('/api/stores', function () { (new \Miko\Controllers\StoreController())->all(); });
$router->get('/api/stores/mine', function () { (new \Miko\Controllers\StoreController())->index(); });
$router->post('/api/stores', function () { (new \Miko\Controllers\StoreController())->store(); });
$router->put('/api/stores/(\d+)', function (int $id) { (new \Miko\Controllers\StoreController())->update($id); });
$router->post('/api/stores/switch', function () { (new \Miko\Controllers\StoreController())->switch(); });
$router->get('/api/stores/current', function () { (new \Miko\Controllers\StoreController())->current(); });

Router::apiResource('/api/categories', 'Miko\\Controllers\\CategoryController');
Router::apiResource('/api/products', 'Miko\\Controllers\\ProductController');
Router::apiResource('/api/customers', 'Miko\\Controllers\\CustomerController');
Router::apiResource('/api/sales', 'Miko\\Controllers\\SaleController');

$router->set404(function () {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Route not found']);
});

$router->run();
