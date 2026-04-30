<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Miko\Database;

echo "Seeding demo data...\n";

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->beginTransaction();

    // Clean existing data
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

    $tables = ['tenants', 'users', 'categories', 'products', 'customers', 'sales', 'sale_items', 'settings', 'stores', 'product_stocks', 'user_stores'];
    foreach ($tables as $table) {
        $pdo->exec("ALTER SEQUENCE {$table}_id_seq RESTART WITH 1");
    }

    // ====== Tenant ======
    $pdo->exec("INSERT INTO tenants (id, name, slug, email, phone, address, currency) VALUES (1, 'Miko Mart', 'miko-mart', 'admin@mikomart.com', '021-5551234', 'Jl. Sudirman No. 123, Jakarta', 'IDR')");
    echo "  [OK] Tenant: Miko Mart\n";

    // ====== Stores ======
    $pdo->exec("INSERT INTO stores (id, tenant_id, name, code, address, phone) VALUES (1, 1, 'Miko Mart Pusat', 'PST', 'Jl. Sudirman No. 123, Jakarta', '021-5551234')");
    $pdo->exec("INSERT INTO stores (id, tenant_id, name, code, address, phone) VALUES (2, 1, 'Miko Mart Cabang', 'CBG', 'Jl. Gatot Subroto No. 45, Jakarta', '021-5555678')");
    echo "  [OK] 2 Stores\n";

    // ====== Admin User ======
    $password = password_hash('password123', PASSWORD_BCRYPT);
    $pdo->exec("INSERT INTO users (id, tenant_id, name, email, password, role) VALUES (1, 1, 'Admin Miko', 'admin@mikomart.com', '{$password}', 'admin')");
    $pdo->exec("INSERT INTO users (id, tenant_id, name, email, password, role) VALUES (2, 1, 'Kasir Budi', 'kasir@mikomart.com', '{$password}', 'cashier')");
    echo "  [OK] Users: admin@mikomart.com / password123\n";

    // ====== User-Stores ======
    $pdo->exec("INSERT INTO user_stores (user_id, store_id) VALUES (1, 1)");
    $pdo->exec("INSERT INTO user_stores (user_id, store_id) VALUES (1, 2)");
    $pdo->exec("INSERT INTO user_stores (user_id, store_id) VALUES (2, 1)");
    echo "  [OK] User-Store assignments\n";

    // ====== Categories ======
    $categories = [
        [1, 'Food & Beverages', 'Makanan dan minuman ringan'],
        [2, 'Electronics', 'Aksesoris dan perangkat elektronik'],
        [3, 'Household', 'Perlengkapan rumah tangga'],
        [4, 'Stationery', 'Alat tulis dan kantor'],
        [5, 'Personal Care', 'Perawatan diri dan kecantikan'],
    ];
    foreach ($categories as $c) {
        $pdo->exec("INSERT INTO categories (id, tenant_id, name, description) VALUES ({$c[0]}, 1, '{$c[1]}', '{$c[2]}')");
    }
    echo "  [OK] 5 Categories\n";

    // ====== Products ======
    $products = [
        ['name' => 'Indomie Goreng',             'sku' => 'FNB-001', 'barcode' => '8991002101113', 'price' => 3500,  'cost' => 2800, 'cat' => 1],
        ['name' => 'Indomie Kuah Soto',           'sku' => 'FNB-002', 'barcode' => '8991002101120', 'price' => 3500,  'cost' => 2800, 'cat' => 1],
        ['name' => 'Coca-Cola 390ml',             'sku' => 'FNB-003', 'barcode' => '8998846880015', 'price' => 6000,  'cost' => 4800, 'cat' => 1],
        ['name' => 'Aqua 600ml',                  'sku' => 'FNB-004', 'barcode' => '8992777211148', 'price' => 3000,  'cost' => 2200, 'cat' => 1],
        ['name' => 'Oreo Original',               'sku' => 'FNB-005', 'barcode' => '8991102517010', 'price' => 8500,  'cost' => 6800, 'cat' => 1],
        ['name' => 'USB Cable Type-C 1m',         'sku' => 'ELC-001', 'barcode' => '6934177700012', 'price' => 25000, 'cost' => 15000, 'cat' => 2],
        ['name' => 'Power Bank 10000mAh',         'sku' => 'ELC-002', 'barcode' => '6934177700029', 'price' => 85000, 'cost' => 60000, 'cat' => 2],
        ['name' => 'Earphone Stereo',             'sku' => 'ELC-003', 'barcode' => '6934177700036', 'price' => 35000, 'cost' => 22000, 'cat' => 2],
        ['name' => 'Sapu Lidi',                   'sku' => 'HSH-001', 'barcode' => '8994321000010', 'price' => 15000, 'cost' => 10000, 'cat' => 3],
        ['name' => 'Lampu LED 10W',               'sku' => 'HSH-002', 'barcode' => '8994321000027', 'price' => 22000, 'cost' => 16000, 'cat' => 3],
        ['name' => 'Buku Tulis 38 Lembar',        'sku' => 'STN-001', 'barcode' => '8992752110019', 'price' => 5000,  'cost' => 3500,  'cat' => 4],
        ['name' => 'Pulpen Standard AE7',         'sku' => 'STN-002', 'barcode' => '8992752110026', 'price' => 3000,  'cost' => 2000,  'cat' => 4],
        ['name' => 'Sikat Gigi Pepsodent',        'sku' => 'PCR-001', 'barcode' => '8999909010012', 'price' => 8000,  'cost' => 5500,  'cat' => 5],
        ['name' => 'Sabun Lifebuoy 90gr',         'sku' => 'PCR-002', 'barcode' => '8999909010029', 'price' => 7000,  'cost' => 5000,  'cat' => 5],
        ['name' => 'Shampoo Clear 70ml',          'sku' => 'PCR-003', 'barcode' => '8999909010036', 'price' => 12000, 'cost' => 8500,  'cat' => 5],
    ];

    $stockByStore = [
        1 => [200, 150, 100, 250, 80,  50, 25, 40, 30, 45, 300, 200, 60, 75, 55],
        2 => [80,  60,  40,  100, 30,  20, 10, 15, 10, 20, 100, 80,  25, 30, 20],
    ];
    $minStock =     [30,  30,  20,  50,  15,  10, 5,  8,  5,  10, 50,  40,  10, 15, 10];

    foreach ($products as $i => $p) {
        $id = $i + 1;
        $pdo->exec("INSERT INTO products (id, tenant_id, category_id, name, sku, barcode, price, cost, description, is_active)
                    VALUES ({$id}, 1, {$p['cat']}, '{$p['name']}', '{$p['sku']}', '{$p['barcode']}', {$p['price']}, {$p['cost']}, '', true)");

        foreach ($stockByStore as $storeId => $stocks) {
            $pdo->exec("INSERT INTO product_stocks (product_id, store_id, stock, min_stock)
                        VALUES ({$id}, {$storeId}, {$stocks[$i]}, {$minStock[$i]})");
        }
    }
    echo "  [OK] 15 Products with per-store stock\n";

    // ====== Customers ======
    $customers = [
        ['name' => 'Budi Santoso',  'phone' => '081234567890', 'email' => 'budi@email.com',  'address' => 'Jl. Merpati No. 5, Jakarta'],
        ['name' => 'Siti Rahayu',   'phone' => '081234567891', 'email' => 'siti@email.com',  'address' => 'Jl. Kenanga No. 10, Jakarta'],
        ['name' => 'Ahmad Hidayat', 'phone' => '081234567892', 'email' => 'ahmad@email.com', 'address' => 'Jl. Mawar No. 15, Jakarta'],
        ['name' => 'Dewi Lestari',  'phone' => '081234567893', 'email' => 'dewi@email.com',  'address' => 'Jl. Anggrek No. 20, Jakarta'],
        ['name' => 'Rudi Hermawan', 'phone' => '081234567894', 'email' => 'rudi@email.com',  'address' => 'Jl. Melati No. 25, Jakarta'],
    ];
    foreach ($customers as $i => $c) {
        $id = $i + 1;
        $pdo->exec("INSERT INTO customers (id, tenant_id, name, phone, email, address) VALUES ({$id}, 1, '{$c['name']}', '{$c['phone']}', '{$c['email']}', '{$c['address']}')");
    }
    echo "  [OK] 5 Customers\n";

    // ====== Sales (spread over last 7 days, mixed stores) ======
    $salesData = [
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

    $saleId = 1;
    $itemId = 1;

    foreach ($salesData as $sd) {
        list($daysAgo, $custId, $items, $payment, $storeId) = $sd;

        $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days " . rand(8, 20) . " hours"));
        $invoiceNo = 'INV-' . date('Ymd', strtotime($createdAt)) . '-' . strtoupper(substr(uniqid(), -6));

        $subtotal = 0;
        $saleItemRows = [];

        foreach ($items as $item) {
            list($prodId, $qty) = $item;
            $prod = $products[$prodId - 1];
            $price = $prod['price'];
            $itemSubtotal = $price * $qty;
            $subtotal += $itemSubtotal;
            $saleItemRows[] = [
                'product_id'   => $prodId,
                'product_name' => $prod['name'],
                'quantity'     => $qty,
                'price'        => $price,
                'subtotal'     => $itemSubtotal,
            ];
        }

        $tax = round($subtotal * 0.11);
        $discount = $subtotal > 100000 ? round($subtotal * 0.05) : 0;
        $total = $subtotal - $discount + $tax;
        $amountPaid = $total + ($total > 50000 ? round($total * 0.1) : 0);
        $change = $amountPaid - $total;

        $custStr = $custId ? $custId : 'NULL';

        $pdo->exec("INSERT INTO sales (id, tenant_id, store_id, user_id, customer_id, invoice_no, subtotal, tax, discount, total, payment_method, amount_paid, change_amount, status, created_at, updated_at)
                    VALUES ({$saleId}, 1, {$storeId}, 1, {$custStr}, '{$invoiceNo}', {$subtotal}, {$tax}, {$discount}, {$total}, '{$payment}', {$amountPaid}, {$change}, 'completed', '{$createdAt}', '{$createdAt}')");

        foreach ($saleItemRows as $si) {
            $pdo->exec("INSERT INTO sale_items (id, sale_id, product_id, product_name, quantity, price, subtotal, created_at)
                        VALUES ({$itemId}, {$saleId}, {$si['product_id']}, '{$si['product_name']}', {$si['quantity']}, {$si['price']}, {$si['subtotal']}, '{$createdAt}')");
            $itemId++;
        }

        // Reduce stock from correct store
        foreach ($items as $item) {
            list($prodId, $qty) = $item;
            $pdo->exec("UPDATE product_stocks SET stock = stock - {$qty} WHERE product_id = {$prodId} AND store_id = {$storeId}");
        }

        $saleId++;
    }

    echo "  [OK] 10 Sales with " . ($itemId - 1) . " items\n";

    // ====== Settings ======
    $pdo->exec("INSERT INTO settings (tenant_id, key, value) VALUES (1, 'tax_rate', '11')");
    $pdo->exec("INSERT INTO settings (tenant_id, key, value) VALUES (1, 'currency', 'IDR')");
    $pdo->exec("INSERT INTO settings (tenant_id, key, value) VALUES (1, 'store_name', 'Miko Mart')");
    echo "  [OK] Settings\n";

    $pdo->commit();

    // Fix sequences
    $allTables = ['tenants', 'stores', 'users', 'user_stores', 'categories', 'products', 'product_stocks', 'customers', 'sales', 'sale_items', 'settings'];
    foreach ($allTables as $table) {
        $pdo->exec("SELECT setval('{$table}_id_seq', (SELECT COALESCE(MAX(id), 0) FROM {$table}))");
    }

    echo "\n✅ Seeding complete!\n";
    echo "──────────────────────────────\n";
    echo "  Login: admin@mikomart.com\n";
    echo "  Pass:  password123\n";
    echo "  2 Stores: Pusat & Cabang\n";
    echo "  Admin has access to BOTH stores\n";
    echo "──────────────────────────────\n";

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
