<?php

namespace Miko\Controllers;

use Miko\Auth;
use Miko\Database;
use Miko\Request;
use Miko\Response;

class ProductController
{
    private function tenantId(): int
    {
        return Auth::tenantId();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $storeId = Auth::storeId();

        if (!$storeId) {
            Response::success([]);
        }

        $search = Request::get('search', '');
        $categoryId = Request::get('category_id', '');

        $params = ['tenant_id' => $this->tenantId(), 'store_id' => $storeId];
        $conditions = ['p.tenant_id = :tenant_id', 'ps.store_id = :store_id'];

        if ($search) {
            $conditions[] = '(p.name ILIKE :search OR p.sku ILIKE :search OR p.barcode ILIKE :search)';
            $params['search'] = "%{$search}%";
        }

        if ($categoryId) {
            $conditions[] = 'p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $where = implode(' AND ', $conditions);

        $products = Database::fetchAll(
            "SELECT p.id, p.tenant_id, p.category_id, p.name, p.sku, p.barcode, p.price, p.cost,
                    ps.stock, ps.min_stock,
                    p.description, p.image, p.is_active, p.created_at, p.updated_at,
                    c.name as category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
             WHERE {$where}
             ORDER BY p.name",
            $params
        );

        Response::success($products);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $storeId = Auth::storeId();

        $product = Database::fetch(
            'SELECT p.*, c.name as category_name,
                    COALESCE(ps.stock, 0) as stock, COALESCE(ps.min_stock, 0) as min_stock
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
             WHERE p.id = :id AND p.tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId(), 'store_id' => $storeId ?? 0]
        );

        if (!$product) {
            Response::error('Product not found', 404);
        }

        Response::success($product);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $data = Request::all();

        $errors = Request::validate([
            'name'  => 'required|min:1|max:255',
            'price' => 'required|numeric',
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 422, $errors);
        }

        if (!empty($data['sku'])) {
            $existing = Database::fetch(
                'SELECT id FROM products WHERE tenant_id = :tenant_id AND sku = :sku',
                ['tenant_id' => $this->tenantId(), 'sku' => $data['sku']]
            );
            if ($existing) {
                Response::error('Product SKU already exists', 422);
            }
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->beginTransaction();

        try {
            $id = Database::insert('products', [
                'tenant_id'   => $this->tenantId(),
                'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
                'name'        => $data['name'],
                'sku'         => $data['sku'] ?? '',
                'barcode'     => $data['barcode'] ?? '',
                'price'       => $data['price'],
                'cost'        => $data['cost'] ?? 0,
                'description' => $data['description'] ?? '',
                'is_active'   => isset($data['is_active']) ? (bool)$data['is_active'] : true,
            ]);

            $stores = Database::fetchAll(
                'SELECT id FROM stores WHERE tenant_id = :tenant_id AND is_active = true',
                ['tenant_id' => $this->tenantId()]
            );

            foreach ($stores as $store) {
                Database::insert('product_stocks', [
                    'product_id' => $id,
                    'store_id'   => $store['id'],
                    'stock'      => $data['stock'] ?? 0,
                    'min_stock'  => $data['min_stock'] ?? 0,
                ]);
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            Response::error('Failed to create product: ' . $e->getMessage(), 500);
        }

        $product = Database::fetch(
            'SELECT p.*, COALESCE(ps.stock, 0) as stock, COALESCE(ps.min_stock, 0) as min_stock
             FROM products p
             LEFT JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
             WHERE p.id = :id',
            ['id' => $id, 'store_id' => Auth::storeId() ?? 0]
        );
        Response::success($product, 'Product created successfully');
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $product = Database::fetch(
            'SELECT * FROM products WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$product) {
            Response::error('Product not found', 404);
        }

        $data = Request::all();

        if (!empty($data['sku']) && $data['sku'] !== $product['sku']) {
            $existing = Database::fetch(
                'SELECT id FROM products WHERE tenant_id = :tenant_id AND sku = :sku AND id != :id',
                ['tenant_id' => $this->tenantId(), 'sku' => $data['sku'], 'id' => $id]
            );
            if ($existing) {
                Response::error('Product SKU already exists', 422);
            }
        }

        Database::update(
            'products',
            [
                'category_id' => isset($data['category_id']) ? ((int)$data['category_id'] ?: null) : $product['category_id'],
                'name'        => $data['name'] ?? $product['name'],
                'sku'         => $data['sku'] ?? $product['sku'],
                'barcode'     => $data['barcode'] ?? $product['barcode'],
                'price'       => $data['price'] ?? $product['price'],
                'cost'        => $data['cost'] ?? $product['cost'],
                'description' => $data['description'] ?? $product['description'],
                'is_active'   => isset($data['is_active']) ? (bool)$data['is_active'] : $product['is_active'],
            ],
            'id = :id',
            ['id' => $id]
        );

        $storeId = Auth::storeId();
        if ($storeId && (isset($data['stock']) || isset($data['min_stock']))) {
            $existingStock = Database::fetch(
                'SELECT id FROM product_stocks WHERE product_id = :product_id AND store_id = :store_id',
                ['product_id' => $id, 'store_id' => $storeId]
            );

            if ($existingStock) {
                Database::update(
                    'product_stocks',
                    [
                        'stock'     => $data['stock'] ?? $existingStock['stock'],
                        'min_stock' => $data['min_stock'] ?? $existingStock['min_stock'],
                    ],
                    'id = :id',
                    ['id' => $existingStock['id']]
                );
            }
        }

        $updated = Database::fetch(
            'SELECT p.*, COALESCE(ps.stock, 0) as stock, COALESCE(ps.min_stock, 0) as min_stock
             FROM products p
             LEFT JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
             WHERE p.id = :id',
            ['id' => $id, 'store_id' => $storeId ?? 0]
        );
        Response::success($updated, 'Product updated successfully');
    }

    public function destroy(int $id): void
    {
        Auth::requireAuth();
        $product = Database::fetch(
            'SELECT * FROM products WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$product) {
            Response::error('Product not found', 404);
        }

        Database::delete('products', 'id = :id', ['id' => $id]);
        Response::success(null, 'Product deleted successfully');
    }
}
