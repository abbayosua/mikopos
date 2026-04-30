<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use Miko\Database;

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'migrate';
    $pdo = Database::getInstance()->getConnection();

    if ($action === 'migrate') {
        $migrationsDir = __DIR__ . '/../migrations';
        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $executed = $pdo->query("SELECT filename FROM migrations")->fetchAll(\PDO::FETCH_COLUMN);
        $results = [];

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $executed)) {
                $results[] = ['file' => $filename, 'status' => 'skipped'];
                continue;
            }
            $pdo->beginTransaction();
            try {
                $pdo->exec(file_get_contents($file));
                $pdo->exec("INSERT INTO migrations (filename) VALUES ('{$filename}')");
                $pdo->commit();
                $results[] = ['file' => $filename, 'status' => 'ok'];
            } catch (\Exception $e) {
                $pdo->rollBack();
                $results[] = ['file' => $filename, 'status' => 'fail', 'error' => $e->getMessage()];
            }
        }

        echo json_encode(['success' => true, 'message' => 'Migrations complete', 'data' => $results]);
        exit;
    }

    if ($action === 'seed') {
        $password = getenv('SEED_PASSWORD') ?: 'password123';

        $pdo->beginTransaction();

        // Clean
        $pdo->exec("DELETE FROM sale_items");
        $pdo->exec("DELETE FROM sales");
        $pdo->exec("DELETE FROM product_stocks");
        $pdo->exec("DELETE FROM user_stores");
        $pdo->exec("DELETE FROM stores");
        $pdo->exec("DELETE FROM products");
        $pdo->exec("DELETE FROM categories");
        $pdo->exec("DELETE FROM customers");
        $pdo->exec("DELETE FROM users");
        $pdo->exec("DELETE FROM tenants");
        $pdo->exec("DELETE FROM settings");

        // Reset sequences
        foreach (['tenants','stores','users','user_stores','categories','products','product_stocks','customers','sales','sale_items','settings'] as $t) {
            $pdo->exec("ALTER SEQUENCE {$t}_id_seq RESTART WITH 1");
        }

        // Tenant
        $pdo->exec("INSERT INTO tenants (id, name, slug, email, phone, address, currency) VALUES (1, 'Miko Mart', 'miko-mart', 'admin@mikomart.com', '021-5551234', 'Jl. Sudirman No. 123, Jakarta', 'IDR')");

        // Stores
        $pdo->exec("INSERT INTO stores (id, tenant_id, name, code, address, phone) VALUES (1, 1, 'Miko Mart Pusat', 'PST', 'Jl. Sudirman No. 123, Jakarta', '021-5551234')");
        $pdo->exec("INSERT INTO stores (id, tenant_id, name, code, address, phone) VALUES (2, 1, 'Miko Mart Cabang', 'CBG', 'Jl. Gatot Subroto No. 45, Jakarta', '021-5555678')");

        // Users
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (id, tenant_id, name, email, password, role) VALUES (1, 1, 'Admin Miko', 'admin@mikomart.com', '{$hash}', 'admin')");
        $pdo->exec("INSERT INTO users (id, tenant_id, name, email, password, role) VALUES (2, 1, 'Kasir Budi', 'kasir@mikomart.com', '{$hash}', 'cashier')");

        // User-Stores
        $pdo->exec("INSERT INTO user_stores (user_id, store_id) VALUES (1, 1), (1, 2), (2, 1)");

        // Categories
        $pdo->exec("INSERT INTO categories (id, tenant_id, name, description) VALUES
            (1, 1, 'Food & Beverages', 'Makanan dan minuman ringan'),
            (2, 1, 'Electronics', 'Aksesoris dan perangkat elektronik'),
            (3, 1, 'Household', 'Perlengkapan rumah tangga'),
            (4, 1, 'Stationery', 'Alat tulis dan kantor'),
            (5, 1, 'Personal Care', 'Perawatan diri dan kecantikan')");

        // Products
        $pdo->exec("INSERT INTO products (id, tenant_id, category_id, name, sku, barcode, price, cost, description, is_active) VALUES
            (1, 1, 1, 'Indomie Goreng', 'FNB-001', '8991002101113', 3500, 2800, '', true),
            (2, 1, 1, 'Indomie Kuah Soto', 'FNB-002', '8991002101120', 3500, 2800, '', true),
            (3, 1, 1, 'Coca-Cola 390ml', 'FNB-003', '8998846880015', 6000, 4800, '', true),
            (4, 1, 1, 'Aqua 600ml', 'FNB-004', '8992777211148', 3000, 2200, '', true),
            (5, 1, 1, 'Oreo Original', 'FNB-005', '8991102517010', 8500, 6800, '', true),
            (6, 1, 2, 'USB Cable Type-C 1m', 'ELC-001', '6934177700012', 25000, 15000, '', true),
            (7, 1, 2, 'Power Bank 10000mAh', 'ELC-002', '6934177700029', 85000, 60000, '', true),
            (8, 1, 2, 'Earphone Stereo', 'ELC-003', '6934177700036', 35000, 22000, '', true),
            (9, 1, 3, 'Sapu Lidi', 'HSH-001', '8994321000010', 15000, 10000, '', true),
            (10, 1, 3, 'Lampu LED 10W', 'HSH-002', '8994321000027', 22000, 16000, '', true),
            (11, 1, 4, 'Buku Tulis 38 Lembar', 'STN-001', '8992752110019', 5000, 3500, '', true),
            (12, 1, 4, 'Pulpen Standard AE7', 'STN-002', '8992752110026', 3000, 2000, '', true),
            (13, 1, 5, 'Sikat Gigi Pepsodent', 'PCR-001', '8999909010012', 8000, 5500, '', true),
            (14, 1, 5, 'Sabun Lifebuoy 90gr', 'PCR-002', '8999909010029', 7000, 5000, '', true),
            (15, 1, 5, 'Shampoo Clear 70ml', 'PCR-003', '8999909010036', 12000, 8500, '', true)");

        // Product stocks
        $pdo->exec("INSERT INTO product_stocks (product_id, store_id, stock, min_stock) VALUES
            (1,1,200,30), (1,2,80,30),
            (2,1,150,30), (2,2,60,30),
            (3,1,100,20), (3,2,40,20),
            (4,1,250,50), (4,2,100,50),
            (5,1,80,15),  (5,2,30,15),
            (6,1,50,10),  (6,2,20,10),
            (7,1,25,5),   (7,2,10,5),
            (8,1,40,8),   (8,2,15,8),
            (9,1,30,5),   (9,2,10,5),
            (10,1,45,10), (10,2,20,10),
            (11,1,300,50),(11,2,100,50),
            (12,1,200,40),(12,2,80,40),
            (13,1,60,10), (13,2,25,10),
            (14,1,75,15), (14,2,30,15),
            (15,1,55,10), (15,2,20,10)");

        // Customers
        $pdo->exec("INSERT INTO customers (id, tenant_id, name, phone, email, address) VALUES
            (1, 1, 'Budi Santoso', '081234567890', 'budi@email.com', 'Jl. Merpati No. 5, Jakarta'),
            (2, 1, 'Siti Rahayu', '081234567891', 'siti@email.com', 'Jl. Kenanga No. 10, Jakarta'),
            (3, 1, 'Ahmad Hidayat', '081234567892', 'ahmad@email.com', 'Jl. Mawar No. 15, Jakarta'),
            (4, 1, 'Dewi Lestari', '081234567893', 'dewi@email.com', 'Jl. Anggrek No. 20, Jakarta'),
            (5, 1, 'Rudi Hermawan', '081234567894', 'rudi@email.com', 'Jl. Melati No. 25, Jakarta')");

        // Sales (10 sales spread over last 7 days)
        $now = time();
        $sales = [
            [0, 1, [[1,3],[11,2],[12,5]], 'cash', 1],
            [0, null, [[4,2],[14,1]], 'cash', 1],
            [1, 2, [[3,4],[5,2]], 'card', 2],
            [1, null, [[6,1],[7,1],[8,2]], 'transfer', 2],
            [2, 3, [[2,5],[4,6],[13,2]], 'cash', 1],
            [2, null, [[9,2],[10,1]], 'cash', 1],
            [3, 4, [[1,10],[11,5],[12,10]], 'card', 2],
            [4, 5, [[7,2],[8,1]], 'transfer', 2],
            [5, null, [[14,3],[15,2],[5,1]], 'cash', 1],
            [6, 1, [[3,2],[4,3],[1,4]], 'cash', 1],
        ];

        $prodNames = ['Indomie Goreng','Indomie Kuah Soto','Coca-Cola 390ml','Aqua 600ml','Oreo Original','USB Cable Type-C 1m','Power Bank 10000mAh','Earphone Stereo','Sapu Lidi','Lampu LED 10W','Buku Tulis 38 Lembar','Pulpen Standard AE7','Sikat Gigi Pepsodent','Sabun Lifebuoy 90gr','Shampoo Clear 70ml'];
        $prodPrices = [3500,3500,6000,3000,8500,25000,85000,35000,15000,22000,5000,3000,8000,7000,12000];

        $saleId = 1;
        $itemId = 1;

        foreach ($sales as $sd) {
            list($daysAgo, $custId, $items, $payment, $storeId) = $sd;
            $ts = strtotime("-{$daysAgo} days " . rand(8, 20) . " hours");
            $createdAt = date('Y-m-d H:i:s', $ts);
            $inv = 'INV-' . date('Ymd', $ts) . '-' . strtoupper(substr(uniqid(), -6));

            $subtotal = 0;
            $rows = [];
            foreach ($items as $item) {
                list($pid, $qty) = $item;
                $prodPrice = $prodPrices[$pid - 1];
                $prodName = $prodNames[$pid - 1];
                $st = $prodPrice * $qty;
                $subtotal += $st;
                $rows[] = "({$itemId},{$saleId},{$pid},'{$prodName}',$qty,$prodPrice,$st,'{$createdAt}')";
                $itemId++;
            }

            $tax = round($subtotal * 0.11);
            $disc = $subtotal > 100000 ? round($subtotal * 0.05) : 0;
            $total = $subtotal - $disc + $tax;
            $paid = $total + ($total > 50000 ? round($total * 0.1) : 0);
            $change = $paid - $total;
            $custStr = $custId ?? 'NULL';

            $pdo->exec("INSERT INTO sales (id, tenant_id, store_id, user_id, customer_id, invoice_no, subtotal, tax, discount, total, payment_method, amount_paid, change_amount, status, created_at, updated_at)
                VALUES ({$saleId}, 1, {$storeId}, 1, {$custStr}, '{$inv}', {$subtotal}, {$tax}, {$disc}, {$total}, '{$payment}', {$paid}, {$change}, 'completed', '{$createdAt}', '{$createdAt}')");

            $pdo->exec("INSERT INTO sale_items (id, sale_id, product_id, product_name, quantity, price, subtotal, created_at) VALUES " . implode(',', $rows));

            foreach ($items as $item) {
                list($pid, $qty) = $item;
                $pdo->exec("UPDATE product_stocks SET stock = stock - {$qty} WHERE product_id = {$pid} AND store_id = {$storeId}");
            }

            $saleId++;
        }

        // Settings
        $pdo->exec("INSERT INTO settings (tenant_id, key, value) VALUES (1, 'tax_rate', '11'), (1, 'currency', 'IDR'), (1, 'store_name', 'Miko Mart')");

        $pdo->commit();

        // Fix sequences
        foreach (['tenants','stores','users','user_stores','categories','products','product_stocks','customers','sales','sale_items','settings'] as $t) {
            $pdo->exec("SELECT setval('{$t}_id_seq', (SELECT COALESCE(MAX(id), 0) FROM {$t}))");
        }

        echo json_encode(['success' => true, 'message' => 'Seed complete', 'data' => [
            'tenant' => 'Miko Mart',
            'login'  => 'admin@mikomart.com',
            'password' => $password,
            'stores' => 2,
            'products' => 15,
            'customers' => 5,
            'sales' => count($sales),
        ]]);
        exit;
    }

    if ($action === 'reset') {
        $pdo->exec("DROP TABLE IF EXISTS sale_items, sales, product_stocks, user_stores, stores, products, categories, customers, users, tenants, settings, migrations CASCADE");
        echo json_encode(['success' => true, 'message' => 'All tables dropped. Run ?action=migrate then ?action=seed']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action. Use: migrate, seed, or reset']);

} catch (\Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
