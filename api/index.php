<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use Miko\Auth;
use Miko\Database;
use Miko\Request;
use Miko\Response;
use Miko\Session;

Session::start();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

ini_set('display_errors', '0');

// Handle CORS / preflight
if ($method === 'OPTIONS') {
    http_response_code(204); exit;
}

// Auth helper
function auth(): void {
    $storeHeader = $_SERVER['HTTP_X_STORE_ID'] ?? $_SERVER['X-Store-Id'] ?? '';
    if ($storeHeader) Auth::setRequestStoreId((int) $storeHeader);
    Auth::requireAuth();
}

try {
    // === AUTH ===
    if ($uri === '/api/auth/login' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $email = $data['email'] ?? ''; $password = $data['password'] ?? '';
        if (!$email || !$password) Response::error('Email and password are required');

        $user = Auth::attempt($email, $password);
        if (!$user) Response::error('Invalid email or password', 401);

        $stores = Database::fetchAll('SELECT s.* FROM stores s JOIN user_stores us ON us.store_id = s.id WHERE us.user_id = :user_id AND s.is_active = true', ['user_id' => $user['id']]);
        $storeId = null; $storeName = null;
        if (count($stores) === 1) { $storeId = $stores[0]['id']; $storeName = $stores[0]['name']; Session::set('store_id', $storeId); Session::set('store_name', $storeName); }
        $token = \Miko\JWTAuth::encode(['user_id' => $user['id'], 'tenant_id' => $user['tenant_id'], 'role' => $user['role'], 'tenant_name' => $user['tenant_name'], 'store_id' => $storeId, 'store_name' => $storeName]);
        Response::success(['token' => $token, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']], 'tenant' => ['name' => $user['tenant_name'], 'slug' => $user['tenant_slug']], 'stores' => $stores, 'store' => count($stores) === 1 ? $stores[0] : null], 'Login successful');
    }

    elseif ($uri === '/api/auth/register' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $tenantName = $data['tenant_name'] ?? ''; $name = $data['name'] ?? ''; $email = $data['email'] ?? ''; $password = $data['password'] ?? '';
        if (!$tenantName || !$name || !$email || !$password) Response::error('All fields are required');
        if (strlen($password) < 6) Response::error('Password must be at least 6 characters');

        $pdo = Database::getInstance()->getConnection();

        $existing = $pdo->prepare("SELECT id FROM tenants WHERE slug = ?");
        $existing->execute([strtolower(str_replace(' ', '-', $tenantName))]);
        if ($existing->fetch()) Response::error('Business name already registered', 422);

        $existing2 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $existing2->execute([$email]);
        if ($existing2->fetch()) Response::error('Email already registered', 422);

        $pdo->beginTransaction();
        try {
            $slug = strtolower(str_replace(' ', '-', $tenantName)) . '-' . uniqid();
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $q1 = $pdo->prepare("INSERT INTO tenants (name, slug, email) VALUES (?,?,?) RETURNING id");
            $q1->execute([$tenantName, $slug, $email]); $tenantId = (int)$q1->fetchColumn();

            $q2 = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password, role) VALUES (?,?,?,?,?) RETURNING id");
            $q2->execute([$tenantId, $name, $email, $hash, 'admin']); $userId = (int)$q2->fetchColumn();

            $q3 = $pdo->prepare("INSERT INTO stores (tenant_id, name, code) VALUES (?,?,?) RETURNING id");
            $q3->execute([$tenantId, $tenantName.' Main', 'MAIN']); $storeId = (int)$q3->fetchColumn();

            $q4 = $pdo->prepare("INSERT INTO user_stores (user_id, store_id) VALUES (?,?)");
            $q4->execute([$userId, $storeId]);

            $pdo->commit();
            Auth::attempt($email, $password); Session::set('store_id', $storeId); Session::set('store_name', $tenantName . ' Main');
            $token = \Miko\JWTAuth::encode(['user_id' => $userId, 'tenant_id' => $tenantId, 'role' => 'admin', 'tenant_name' => $tenantName, 'store_id' => $storeId, 'store_name' => $tenantName . ' Main']);
            Response::success(['token' => $token, 'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'admin'], 'tenant' => ['name' => $tenantName, 'slug' => $slug], 'store' => ['id' => $storeId, 'name' => $tenantName . ' Main']], 'Registration successful');
        } catch (\Exception $e) { $pdo->rollBack(); Response::error('Registration failed: ' . $e->getMessage(), 500); }
    }

    elseif ($uri === '/api/auth/me' && $method === 'GET') {
        auth(); $user = Auth::user();
        $stores = Database::fetchAll('SELECT s.* FROM stores s JOIN user_stores us ON us.store_id = s.id WHERE us.user_id = :user_id AND s.is_active = true', ['user_id' => Auth::id()]);
        Response::success(['user' => $user, 'tenant' => ['name' => Auth::tenantName()], 'stores' => $stores, 'store' => Auth::hasStore() ? ['id' => Auth::storeId(), 'name' => Auth::storeName()] : null]);
    }

    // === DASHBOARD ===
    elseif ($uri === '/api/dashboard/stats' && $method === 'GET') {
        auth(); $tid = Auth::tenantId(); $today = date('Y-m-d'); $month = date('Y-m');
        $ts = Database::fetch("SELECT COUNT(*) as c, COALESCE(SUM(total),0) as t FROM sales WHERE tenant_id=:t AND created_at::date=:d AND status='completed'", ['t'=>$tid,'d'=>$today]);
        $ms = Database::fetch("SELECT COUNT(*) as c, COALESCE(SUM(total),0) as t FROM sales WHERE tenant_id=:t AND to_char(created_at,'YYYY-MM')=:m AND status='completed'", ['t'=>$tid,'m'=>$month]);
        $pc = Database::fetch('SELECT COUNT(*) as c FROM products WHERE tenant_id=:t',['t'=>$tid])['c']??0;
        $cc = Database::fetch('SELECT COUNT(*) as c FROM customers WHERE tenant_id=:t',['t'=>$tid])['c']??0;
        $sid = Auth::storeId();
        if ($sid) {
            $ls = Database::fetchAll('SELECT p.id,p.name,p.sku,ps.stock,ps.min_stock FROM products p JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true AND ps.stock<=ps.min_stock ORDER BY ps.stock LIMIT 5',['t'=>$tid,'s'=>$sid]);
            $rs = Database::fetchAll('SELECT s.id,s.invoice_no,s.total,s.created_at,c.name as customer_name FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.tenant_id=:t AND s.store_id=:s ORDER BY s.created_at DESC LIMIT 5',['t'=>$tid,'s'=>$sid]);
        } else { $ls = []; $rs = []; }
        Response::success(['today_sales'=>$ts,'month_sales'=>$ms,'product_count'=>$pc,'customer_count'=>$cc,'low_stock'=>$ls,'recent_sales'=>$rs]);
    }

    // === CATEGORIES ===
    elseif ($uri === '/api/categories' && $method === 'GET') {
        auth(); Response::success(Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c WHERE c.tenant_id=:t ORDER BY c.name',['t'=>Auth::tenantId()]));
    }
    elseif ($uri === '/api/categories' && $method === 'POST') {
        auth(); $d = Request::all(); $id = Database::insert('categories',['tenant_id'=>Auth::tenantId(),'name'=>$d['name'],'description'=>$d['description']??'']);
        Response::success(Database::fetch('SELECT * FROM categories WHERE id=:i',['i'=>$id]), 'Created');
    }
    elseif (preg_match('#^/api/categories/(\d+)$#', $uri, $m) && $method === 'GET') {
        auth(); $c = Database::fetch('SELECT * FROM categories WHERE id=:i AND tenant_id=:t',['i'=>$m[1],'t'=>Auth::tenantId()]);
        $c ? Response::success($c) : Response::error('Not found', 404);
    }
    elseif (preg_match('#^/api/categories/(\d+)$#', $uri, $m) && $method === 'PUT') {
        auth(); $d = Request::all();
        Database::update('categories',['name'=>$d['name']],'id=:i',['i'=>$m[1]]);
        Response::success(Database::fetch('SELECT * FROM categories WHERE id=:i',['i'=>$m[1]]), 'Updated');
    }
    elseif (preg_match('#^/api/categories/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        auth(); Database::delete('categories','id=:i',['i'=>$m[1]]);
        Response::success(null, 'Deleted');
    }

    // === PRODUCTS ===
    elseif ($uri === '/api/products' && $method === 'GET') {
        auth(); $sid = Auth::storeId(); if (!$sid) { Response::success([]); }
        $q = Request::get('search',''); $cat = Request::get('category_id','');
        $p = ['tenant_id'=>Auth::tenantId(), 'store_id'=>$sid];
        $w = ['p.tenant_id=:tenant_id', 'ps.store_id=:store_id'];
        if ($q) { $w[] = '(p.name ILIKE :q OR p.sku ILIKE :q OR p.barcode ILIKE :q)'; $p['q'] = "%$q%"; }
        if ($cat) { $w[] = 'p.category_id=:cat'; $p['cat'] = $cat; }
        Response::success(Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:store_id WHERE ".implode(' AND ',$w)." ORDER BY p.name", $p));
    }
    elseif ($uri === '/api/products' && $method === 'POST') {
        auth(); $d = Request::all();
        $id = Database::insert('products',['tenant_id'=>Auth::tenantId(),'category_id'=>!empty($d['category_id'])?(int)$d['category_id']:null,'name'=>$d['name'],'sku'=>$d['sku']??'','barcode'=>$d['barcode']??'','price'=>$d['price'],'cost'=>$d['cost']??0,'description'=>$d['description']??'','is_active'=>true]);
        foreach (Database::fetchAll('SELECT id FROM stores WHERE tenant_id=:t AND is_active=true',['t'=>Auth::tenantId()]) as $s) {
            Database::insert('product_stocks',['product_id'=>$id,'store_id'=>$s['id'],'stock'=>$d['stock']??0,'min_stock'=>$d['min_stock']??0]);
        }
        Response::success(Database::fetch('SELECT p.*,COALESCE(ps.stock,0) as stock,COALESCE(ps.min_stock,0) as min_stock FROM products p LEFT JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.id=:i',['i'=>$id,'s'=>Auth::storeId()??0]), 'Created');
    }
    elseif (preg_match('#^/api/products/(\d+)$#', $uri, $m) && $method === 'GET') {
        auth(); $p = Database::fetch('SELECT p.*,c.name as category_name,COALESCE(ps.stock,0) as stock,COALESCE(ps.min_stock,0) as min_stock FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.id=:i AND p.tenant_id=:t',['i'=>$m[1],'t'=>Auth::tenantId(),'s'=>Auth::storeId()??0]);
        $p ? Response::success($p) : Response::error('Not found', 404);
    }
    elseif (preg_match('#^/api/products/(\d+)$#', $uri, $m) && $method === 'PUT') {
        auth(); $d = Request::all(); $id = $m[1];
        Database::update('products',['name'=>$d['name']??'','price'=>$d['price']??0,'cost'=>$d['cost']??0,'description'=>$d['description']??''],'id=:i',['i'=>$id]);
        Response::success(Database::fetch('SELECT p.*,COALESCE(ps.stock,0) as stock,COALESCE(ps.min_stock,0) as min_stock FROM products p LEFT JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.id=:i',['i'=>$id,'s'=>Auth::storeId()??0]), 'Updated');
    }
    elseif (preg_match('#^/api/products/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        auth(); Database::delete('products','id=:i',['i'=>$m[1]]);
        Response::success(null, 'Deleted');
    }

    // === PRODUCTS SEARCH ===
    elseif ($uri === '/api/products/search' && $method === 'GET') {
        auth(); $q = Request::get('q',''); $sid = Auth::storeId();
        if (!$q || !$sid) { Response::success([]); }
        Response::success(Database::fetchAll("SELECT p.id,p.name,p.sku,p.price,p.image,ps.stock FROM products p JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true AND ps.stock>0 AND (p.name ILIKE :q OR p.sku ILIKE :q OR p.barcode ILIKE :q) ORDER BY p.name LIMIT 20",['t'=>Auth::tenantId(),'s'=>$sid,'q'=>"%$q%"]));
    }

    // === BARCODE LOOKUP ===
    elseif ($uri === '/api/products/lookup' && $method === 'GET') {
        auth(); $bc = Request::get('barcode','');
        if (!$bc) Response::error('Barcode required');
        $local = Database::fetch('SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.tenant_id=:t AND p.barcode=:b LIMIT 1',['t'=>Auth::tenantId(),'b'=>$bc]);
        if ($local) Response::success($local, 'Found locally');
        if (!preg_match('/^\d{8,14}$/',$bc)) Response::success(null, 'Invalid format');
        $ctx = stream_context_create(['http'=>['timeout'=>5,'user_agent'=>'MIKOPos/1.0']]);
        $resp = @file_get_contents("https://world.openfoodfacts.org/api/v2/product/".urlencode($bc).".json", false, $ctx);
        if ($resp === false) Response::success(null, 'Unavailable');
        $d = json_decode($resp, true);
        if (!$d||!($d['status']??0)) Response::success(null, 'Not found online');
        $p = $d['product']??[];
        Response::success(['barcode'=>$bc,'name'=>$p['product_name']??$p['generic_name']??'','brand'=>$p['brands']??'','category_name'=>$p['categories_hierarchy'][0]??'','image'=>$p['image_url']??'','source'=>'openfoodfacts'], 'Found online');
    }

    // === CUSTOMERS ===
    elseif ($uri === '/api/customers' && $method === 'GET') {
        auth(); $s = Request::get('search',''); $p = ['tenant_id'=>Auth::tenantId()]; $w = 'tenant_id=:tenant_id';
        if ($s) { $w .= ' AND (name ILIKE :s OR phone ILIKE :s OR email ILIKE :s)'; $p['s'] = "%$s%"; }
        Response::success(Database::fetchAll("SELECT c.*,(SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) as sale_count,(SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE s.customer_id=c.id) as total_spent FROM customers c WHERE $w ORDER BY c.name", $p));
    }
    elseif ($uri === '/api/customers' && $method === 'POST') {
        auth(); $d = Request::all();
        $id = Database::insert('customers',['tenant_id'=>Auth::tenantId(),'name'=>$d['name'],'phone'=>$d['phone']??'','email'=>$d['email']??'','address'=>$d['address']??'']);
        Response::success(Database::fetch('SELECT * FROM customers WHERE id=:i',['i'=>$id]), 'Created');
    }
    elseif (preg_match('#^/api/customers/(\d+)$#', $uri, $m) && $method === 'GET') {
        auth(); $c = Database::fetch('SELECT * FROM customers WHERE id=:i AND tenant_id=:t',['i'=>$m[1],'t'=>Auth::tenantId()]);
        $c ? Response::success($c) : Response::error('Not found', 404);
    }
    elseif (preg_match('#^/api/customers/(\d+)$#', $uri, $m) && $method === 'PUT') {
        auth(); $d = Request::all();
        Database::update('customers',['name'=>$d['name']??'','phone'=>$d['phone']??'','email'=>$d['email']??'','address'=>$d['address']??''],'id=:i',['i'=>$m[1]]);
        Response::success(Database::fetch('SELECT * FROM customers WHERE id=:i',['i'=>$m[1]]), 'Updated');
    }
    elseif (preg_match('#^/api/customers/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        auth(); Database::delete('customers','id=:i',['i'=>$m[1]]);
        Response::success(null, 'Deleted');
    }

    // === SALES ===
    elseif ($uri === '/api/sales' && $method === 'GET') {
        auth(); $sid = Auth::storeId(); $p = ['tenant_id'=>Auth::tenantId()]; $w = ['s.tenant_id=:tenant_id'];
        if ($sid) { $w[] = 's.store_id=:store_id'; $p['store_id'] = $sid; }
        $search = Request::get('search',''); $from = Request::get('from',''); $to = Request::get('to','');
        if ($search) { $w[] = '(s.invoice_no ILIKE :s OR c.name ILIKE :s)'; $p['s'] = "%$search%"; }
        if ($from) { $w[] = 's.created_at>=:from'; $p['from'] = $from; }
        if ($to) { $w[] = "s.created_at<=:to"; $p['to'] = "$to 23:59:59"; }
        Response::success(Database::fetchAll("SELECT s.*,u.name as cashier_name,c.name as customer_name FROM sales s LEFT JOIN users u ON u.id=s.user_id LEFT JOIN customers c ON c.id=s.customer_id WHERE ".implode(' AND ',$w)." ORDER BY s.created_at DESC", $p));
    }
    elseif ($uri === '/api/sales' && $method === 'POST') {
        auth(); $d = json_decode(file_get_contents('php://input'), true) ?: $_POST; $items = $d['items'] ?? [];
        if (!is_array($items) || !$items) Response::error('Cart is empty');
        $tid = Auth::tenantId(); $uid = Auth::id(); $sid = Auth::storeId();
        if (!$sid) Response::error('No store selected', 400);
        $pdo = Database::getInstance()->getConnection();
        try { $pdo->beginTransaction(); } catch (\Exception $e) { Response::error('beginTransaction: '.$e->getMessage(), 500); }
        try {
            $subtotal = 0; $saleItems = [];
            foreach ($items as $item) {
                try {
                    $stmt = $pdo->prepare('SELECT p.id,p.name,p.price,ps.stock FROM products p JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.id=:i AND p.tenant_id=:t');
                    $stmt->execute(['i'=>(int)$item['product_id'],'t'=>$tid,'s'=>$sid]);
                    $prod = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                } catch (\Exception $e) { throw new \Exception("SELECT product: ".$e->getMessage()); }
                if (!$prod) throw new \Exception("Product not found");
                $qty = max(1, (int)($item['quantity']??1));
                if ((int)$prod['stock'] < $qty) throw new \Exception("Insufficient stock: have {$prod['stock']}, need $qty");
                $price = (float)($item['price']??$prod['price']); $st = $price*$qty; $subtotal += $st;
                $saleItems[] = ['product_id'=>$prod['id'],'product_name'=>$prod['name'],'quantity'=>$qty,'price'=>$price,'subtotal'=>$st];
                try {
                    $upd = $pdo->prepare('UPDATE product_stocks SET stock=stock-:q WHERE product_id=:i AND store_id=:s');
                    $upd->execute(['q'=>$qty,'i'=>$prod['id'],'s'=>$sid]);
                } catch (\Exception $e) { throw new \Exception("UPDATE stock: ".$e->getMessage()); }
            }
            $disc = (float)($d['discount']??0); $tax = (float)($d['tax']??0);
            $total = $subtotal-$disc+$tax; $paid = (float)($d['amount_paid']??0); $change = max(0,$paid-$total);
            $inv = 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-6));
            try {
                $ins = $pdo->prepare("INSERT INTO sales (tenant_id,store_id,user_id,customer_id,invoice_no,subtotal,tax,discount,total,payment_method,amount_paid,change_amount,status,notes) VALUES (:tenant_id,:store_id,:user_id,:customer_id,:invoice_no,:subtotal,:tax,:discount,:total,:payment_method,:amount_paid,:change_amount,:status,:notes) RETURNING id");
                $ins->execute(['tenant_id'=>$tid,'store_id'=>$sid,'user_id'=>$uid,'customer_id'=>!empty($d['customer_id'])?(int)$d['customer_id']:null,'invoice_no'=>$inv,'subtotal'=>$subtotal,'tax'=>$tax,'discount'=>$disc,'total'=>$total,'payment_method'=>$d['payment_method']??'cash','amount_paid'=>$paid,'change_amount'=>$change,'status'=>'completed','notes'=>$d['notes']??'']);
                $saleId = (int) $ins->fetchColumn();
            } catch (\Exception $e) { throw new \Exception("INSERT sale: ".$e->getMessage()); }
            foreach ($saleItems as $si) {
                $si['sale_id'] = $saleId;
                try {
                    $i2 = $pdo->prepare("INSERT INTO sale_items (product_id,sale_id,product_name,quantity,price,subtotal) VALUES (:product_id,:sale_id,:product_name,:quantity,:price,:subtotal)");
                    $i2->execute($si);
                } catch (\Exception $e) { throw new \Exception("INSERT sale_item: ".$e->getMessage()); }
            }
            $pdo->commit();
            $s = $pdo->prepare('SELECT s.*,u.name as cashier_name,c.name as customer_name FROM sales s LEFT JOIN users u ON u.id=s.user_id LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=:i');
            $s->execute(['i'=>$saleId]);
            $sale = $s->fetch(\PDO::FETCH_ASSOC);
            $sale['items'] = $saleItems; Response::success($sale, 'Sale completed');
        } catch (\Exception $e) {
            try { $pdo->rollBack(); } catch (\Exception $r) {}
            Response::error('Sale: '.$e->getMessage(), 422);
        }
    }
    elseif (preg_match('#^/api/sales/(\d+)$#', $uri, $m) && $method === 'GET') {
        auth();
        $s = Database::fetch('SELECT s.*,u.name as cashier_name,c.name as customer_name,c.phone as customer_phone FROM sales s LEFT JOIN users u ON u.id=s.user_id LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=:i AND s.tenant_id=:t',['i'=>$m[1],'t'=>Auth::tenantId()]);
        if (!$s) Response::error('Not found', 404);
        $s['items'] = Database::fetchAll('SELECT si.*,p.sku FROM sale_items si LEFT JOIN products p ON p.id=si.product_id WHERE si.sale_id=:s',['s'=>$m[1]]);
        Response::success($s);
    }
    elseif (preg_match('#^/api/sales/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        auth(); $sid = $m[1]; $s = Database::fetch('SELECT * FROM sales WHERE id=:i AND tenant_id=:t',['i'=>$sid,'t'=>Auth::tenantId()]);
        if (!$s) Response::error('Not found', 404);
        $pdo = Database::getInstance()->getConnection(); $pdo->beginTransaction();
        try {
            foreach (Database::fetchAll('SELECT product_id,quantity FROM sale_items WHERE sale_id=:s',['s'=>$sid]) as $item) {
                if ($item['product_id']) Database::query('UPDATE product_stocks SET stock=stock+:q WHERE product_id=:i AND store_id=:s',['q'=>$item['quantity'],'i'=>$item['product_id'],'s'=>$s['store_id']]);
            }
            Database::update('sales',['status'=>'voided'],'id=:i',['i'=>$sid]);
            $pdo->commit(); Response::success(null, 'Voided');
        } catch (\Exception $e) { $pdo->rollBack(); Response::error($e->getMessage(), 422); }
    }

    // === STORES ===
    elseif ($uri === '/api/stores' && $method === 'GET') {
        auth(); Response::success(Database::fetchAll('SELECT * FROM stores WHERE tenant_id=:t AND is_active=true ORDER BY name',['t'=>Auth::tenantId()]));
    }
    elseif ($uri === '/api/stores/mine' && $method === 'GET') {
        auth(); Response::success(Database::fetchAll('SELECT s.* FROM stores s JOIN user_stores us ON us.store_id=s.id WHERE us.user_id=:u AND s.is_active=true',['u'=>Auth::id()]));
    }
    elseif ($uri === '/api/stores' && $method === 'POST') {
        auth(); $d = Request::all(); $id = Database::insert('stores',['tenant_id'=>Auth::tenantId(),'name'=>$d['name'],'code'=>$d['code']??'','address'=>$d['address']??'','phone'=>$d['phone']??'']);
        Response::success(Database::fetch('SELECT * FROM stores WHERE id=:i',['i'=>$id]), 'Created');
    }
    elseif ($uri === '/api/stores/switch' && $method === 'POST') {
        auth(); $sid = (int)Request::input('store_id');
        $s = Database::fetch('SELECT s.* FROM stores s JOIN user_stores us ON us.store_id=s.id WHERE us.user_id=:u AND s.id=:i',['u'=>Auth::id(),'i'=>$sid]);
        if (!$s) Response::error('Store not found or no access', 404);
        Session::set('store_id',$sid); Session::set('store_name',$s['name']);
        Response::success(['store'=>$s], 'Store switched');
    }
    elseif ($uri === '/api/stores/current' && $method === 'GET') {
        auth(); $sid = Auth::storeId();
        if (!$sid) Response::success(null, 'No store selected');
        Response::success(Database::fetch('SELECT * FROM stores WHERE id=:i',['i'=>$sid]));
    }
    elseif (preg_match('#^/api/stores/(\d+)$#', $uri, $m) && $method === 'PUT') {
        auth(); $d = Request::all();
        Database::update('stores',['name'=>$d['name']??'','code'=>$d['code']??'','address'=>$d['address']??'','phone'=>$d['phone']??''],'id=:i',['i'=>$m[1]]);
        Response::success(Database::fetch('SELECT * FROM stores WHERE id=:i',['i'=>$m[1]]), 'Updated');
    }

    // === RECEIPT ===
    elseif (preg_match('#^/api/sales/(\d+)/receipt$#', $uri, $m) && $method === 'GET') {
        auth(); $id = $m[1];
        $s = Database::fetch('SELECT s.*,u.name as cashier_name,c.name as customer_name,c.phone as customer_phone,t.name as tenant_name,t.address as tenant_address,t.phone as tenant_phone FROM sales s LEFT JOIN users u ON u.id=s.user_id LEFT JOIN customers c ON c.id=s.customer_id JOIN tenants t ON t.id=s.tenant_id WHERE s.id=:i AND s.tenant_id=:t',['i'=>$id,'t'=>Auth::tenantId()]);
        if (!$s) Response::error('Not found', 404);
        $s['items'] = Database::fetchAll('SELECT * FROM sale_items WHERE sale_id=:s',['s'=>$id]);
        Response::success($s);
    }

    // === POS INIT (bundled data for fast POS loading) ===
    elseif ($uri === '/api/pos/init' && $method === 'GET') {
        auth(); $tid = Auth::tenantId(); $sid = Auth::storeId();
        if (!$sid) Response::error('No store selected', 400);

        $store = Database::fetch('SELECT * FROM stores WHERE id=:i', ['i'=>$sid]);
        $categories = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
        $customers = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) as sale_count,(SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE s.customer_id=c.id) as total_spent FROM customers c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
        $products = Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true ORDER BY p.name", ['t'=>$tid, 's'=>$sid]);

        $lowStock = Database::fetchAll('SELECT p.id,p.name,p.sku,ps.stock,ps.min_stock FROM products p JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true AND ps.stock<=ps.min_stock ORDER BY ps.stock LIMIT 5', ['t'=>$tid, 's'=>$sid]);

        Response::success([
            'store' => $store,
            'categories' => $categories,
            'customers' => $customers,
            'products' => $products,
            'low_stock' => $lowStock,
            'token_check' => time(),
        ]);
    }

    // === SYNC ENDPOINTS ===
    elseif ($uri === '/api/sync/init' && $method === 'GET') {
        auth(); $tid = Auth::tenantId(); $sid = Auth::storeId();
        if (!$sid) Response::error('No store selected', 400);

        $store = Database::fetch('SELECT * FROM stores WHERE id=:i', ['i'=>$sid]);
        $categories = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
        $customers = Database::fetchAll('SELECT c.*,(SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) as sale_count,(SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE s.customer_id=c.id) as total_spent FROM customers c WHERE c.tenant_id=:t ORDER BY c.name', ['t'=>$tid]);
        $products = Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE p.tenant_id=:t AND p.is_active=true ORDER BY p.name", ['t'=>$tid, 's'=>$sid]);
        $allStores = Database::fetchAll('SELECT * FROM stores WHERE tenant_id=:t AND is_active=true', ['t'=>$tid]);
        $todaySales = Database::fetch("SELECT COUNT(*) as count, COALESCE(SUM(total),0) as total FROM sales WHERE tenant_id=:t AND store_id=:s AND created_at::date = CURRENT_DATE AND status='completed'", ['t'=>$tid, 's'=>$sid]);

        Response::success(['store'=>$store,'categories'=>$categories,'customers'=>$customers,'products'=>$products,'stores'=>$allStores,'today_sales'=>$todaySales,'synced_at'=>date('c')]);
    }
    elseif ($uri === '/api/sync/products' && $method === 'GET') {
        auth(); $tid = Auth::tenantId(); $sid = Auth::storeId(); $since = Request::get('since','');
        if (!$sid) Response::error('No store selected', 400);

        $params = ['t'=>$tid, 's'=>$sid];
        $where = 'p.tenant_id=:t AND p.is_active=true';
        if ($since) { $where .= ' AND p.updated_at>:since'; $params['since'] = $since; }

        $products = Database::fetchAll("SELECT p.id,p.tenant_id,p.category_id,p.name,p.sku,p.barcode,p.price,p.cost,ps.stock,ps.min_stock,p.description,p.image,p.is_active,p.created_at,p.updated_at,c.name as category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN product_stocks ps ON ps.product_id=p.id AND ps.store_id=:s WHERE $where ORDER BY p.name", $params);
        Response::success(['products'=>$products, 'synced_at'=>date('c')]);
    }
    elseif ($uri === '/api/sync/sales' && $method === 'POST') {
        auth(); $d = json_decode(file_get_contents('php://input'), true) ?: $_POST; $items = $d['items'] ?? [];
        if (!is_array($items) || !$items) Response::error('Cart is empty');
        $tid = Auth::tenantId(); $uid = Auth::id(); $sid = Auth::storeId();
        if (!$sid) Response::error('No store selected', 400);

        $pdo = Database::getInstance()->getConnection();
        $subtotal = 0; $saleItems = []; $errors = [];
        foreach ($items as $i => $item) {
            $pid = (int)$item['product_id'];
            try {
                $s = $pdo->prepare('SELECT id,name,price FROM products WHERE id=? AND tenant_id=?');
                $s->execute([$pid, $tid]);
                $prod = $s->fetch(\PDO::FETCH_ASSOC) ?: null;
            } catch (\Exception $e) { return Response::error("Q1($pid): ".$e->getMessage(), 500); }
            if (!$prod) { $errors[] = "Product #{$pid} not found"; continue; }
            try {
                $s2 = $pdo->prepare('SELECT stock FROM product_stocks WHERE product_id=? AND store_id=?');
                $s2->execute([$pid, $sid]);
                $stock = $s2->fetch(\PDO::FETCH_ASSOC) ?: null;
            } catch (\Exception $e) { return Response::error("Q2($pid): ".$e->getMessage(), 500); }
            if (!$stock) { $errors[] = "Product #{$pid} no stock"; continue; }
            $qty = max(1, (int)($item['quantity']??1));
            $price = (float)($item['price']??$prod['price']); $st = $price*$qty;
            $subtotal += $st;
            $saleItems[] = ['product_id'=>$prod['id'],'product_name'=>$prod['name'],'quantity'=>$qty,'price'=>$price,'subtotal'=>$st];
            try {
                $u = $pdo->prepare('UPDATE product_stocks SET stock=GREATEST(0, stock-?) WHERE product_id=? AND store_id=?');
                $u->execute([$qty, $pid, $sid]);
            } catch (\Exception $e) { return Response::error("Q3($pid): ".$e->getMessage(), 500); }
        }
        if (empty($saleItems)) Response::error('No valid items. Errors: '.implode('; ', $errors), 422);

        $disc = (float)($d['discount']??0); $tax = (float)($d['tax']??0);
        $total = $subtotal-$disc+$tax; $paid = (float)($d['amount_paid']??0); $change = max(0,$paid-$total);
        $inv = 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-6));
        try {
            $si = $pdo->prepare("INSERT INTO sales (tenant_id,store_id,user_id,customer_id,invoice_no,subtotal,tax,discount,total,payment_method,amount_paid,change_amount,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'completed','') RETURNING id");
            $si->execute([$tid,$sid,$uid,!empty($d['customer_id'])?(int)$d['customer_id']:null,$inv,$subtotal,$tax,$disc,$total,$d['payment_method']??'cash',$paid,$change]);
            $saleId = (int) $si->fetchColumn();
        } catch (\Exception $e) { return Response::error("Q4: ".$e->getMessage(), 500); }
        foreach ($saleItems as $item) {
            try {
                $i2 = $pdo->prepare("INSERT INTO sale_items (product_id,sale_id,product_name,quantity,price,subtotal) VALUES (?,?,?,?,?,?)");
                $i2->execute([$item['product_id'],$saleId,$item['product_name'],$item['quantity'],$item['price'],$item['subtotal']]);
            } catch (\Exception $e) { return Response::error("Q5: ".$e->getMessage(), 500); }
        }
        try {
            $sf = $pdo->prepare('SELECT s.*,u.name as cashier_name FROM sales s LEFT JOIN users u ON u.id=s.user_id WHERE s.id=?');
            $sf->execute([$saleId]);
            $sale = $sf->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) { return Response::error("Q6: ".$e->getMessage(), 500); }
        $sale['items'] = $saleItems;
        $resp = ['success'=>true, 'message'=>'Sale synced', 'data'=>$sale];
        if ($errors) $resp['warnings'] = $errors;
        Response::json($resp);
    }
    elseif ($uri === '/api/sync/status' && $method === 'GET') {
        auth();
        $pending = Database::fetch('SELECT COUNT(*) as c FROM sales WHERE status=:s', ['s'=>'pending']); // for future use
        Response::success(['server_time'=>date('c'), 'db_connected'=>true]);
    }

    // === SPA Shell ===
    elseif ($uri === '/app' && $method === 'GET') {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: private, max-age=30, stale-while-revalidate=300');
        require __DIR__ . '/../views/spa.php';
        exit;
    }

    // === Logout (from old sidebar link) ===
    elseif ($uri === '/logout' && $method === 'GET') {
        http_response_code(302);
        header('Location: /app'); exit;
    }

    // === Redirect all non-API to SPA ===
    elseif (!str_starts_with($uri, '/api/')) {
        http_response_code(302);
        header('Location: /app'); exit;
    }

    // 404 API
    else {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'Route not found', 'uri'=>$uri, 'method'=>$method]);
    }

} catch (\Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
