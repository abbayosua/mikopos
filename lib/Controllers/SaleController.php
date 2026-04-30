<?php

namespace Miko\Controllers;

use Miko\Auth;
use Miko\Database;
use Miko\Request;
use Miko\Response;

class SaleController
{
    private function tenantId(): int
    {
        return Auth::tenantId();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $search = Request::get('search', '');
        $from = Request::get('from', '');
        $to = Request::get('to', '');
        $storeId = Auth::storeId();

        $params = ['tenant_id' => $this->tenantId()];
        $conditions = ['s.tenant_id = :tenant_id'];

        if ($storeId) {
            $conditions[] = 's.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        if ($search) {
            $conditions[] = '(s.invoice_no ILIKE :search OR c.name ILIKE :search)';
            $params['search'] = "%{$search}%";
        }

        if ($from) {
            $conditions[] = 's.created_at >= :from';
            $params['from'] = $from;
        }

        if ($to) {
            $conditions[] = 's.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);

        $sales = Database::fetchAll(
            "SELECT s.*, u.name as cashier_name, c.name as customer_name
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE {$where}
             ORDER BY s.created_at DESC",
            $params
        );

        Response::success($sales);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $sale = Database::fetch(
            'SELECT s.*, u.name as cashier_name, c.name as customer_name, c.phone as customer_phone
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.id = :id AND s.tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$sale) {
            Response::error('Sale not found', 404);
        }

        $sale['items'] = Database::fetchAll(
            'SELECT si.*, p.sku
             FROM sale_items si
             LEFT JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = :sale_id
             ORDER BY si.id',
            ['sale_id' => $id]
        );

        Response::success($sale);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $data = Request::all();

        $errors = Request::validate([
            'items'       => 'required',
            'amount_paid' => 'required|numeric',
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 422, $errors);
        }

        $items = $data['items'];
        if (!is_array($items) || empty($items)) {
            Response::error('Sale must have at least one item', 422);
        }

        $tenantId = $this->tenantId();
        $userId = Auth::id();
        $storeId = Auth::storeId();

        if (!$storeId) {
            Response::error('No store selected', 400);
        }

        $subtotal = 0;
        $saleItems = [];

        Database::getInstance()->getConnection()->beginTransaction();

        try {
            foreach ($items as $item) {
                $product = Database::fetch(
                    'SELECT p.id, p.name, p.price, ps.stock
                     FROM products p
                     JOIN product_stocks ps ON ps.product_id = p.id AND ps.store_id = :store_id
                     WHERE p.id = :id AND p.tenant_id = :tenant_id',
                    ['id' => (int)$item['product_id'], 'tenant_id' => $tenantId, 'store_id' => $storeId]
                );

                if (!$product) {
                    throw new \Exception("Product ID {$item['product_id']} not found");
                }

                $qty = (int)($item['quantity'] ?? 1);
                if ($qty < 1) {
                    throw new \Exception("Invalid quantity for {$product['name']}");
                }

                if ($product['stock'] < $qty) {
                    throw new \Exception("Insufficient stock for {$product['name']}. Available: {$product['stock']}");
                }

                $price = (float)($item['price'] ?? $product['price']);
                $itemSubtotal = $price * $qty;
                $subtotal += $itemSubtotal;

                $saleItems[] = [
                    'product_id'   => $product['id'],
                    'product_name' => $product['name'],
                    'quantity'     => $qty,
                    'price'        => $price,
                    'subtotal'     => $itemSubtotal,
                ];

                Database::query(
                    'UPDATE product_stocks SET stock = stock - :qty WHERE product_id = :id AND store_id = :store_id',
                    ['qty' => $qty, 'id' => $product['id'], 'store_id' => $storeId]
                );
            }

            $discount = (float)($data['discount'] ?? 0);
            $tax = (float)($data['tax'] ?? 0);
            $total = $subtotal - $discount + $tax;
            $amountPaid = (float)$data['amount_paid'];
            $changeAmount = max(0, $amountPaid - $total);
            $paymentMethod = $data['payment_method'] ?? 'cash';

            $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $saleId = Database::insert('sales', [
                'tenant_id'      => $tenantId,
                'store_id'       => $storeId,
                'user_id'        => $userId,
                'customer_id'    => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                'invoice_no'     => $invoiceNo,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'discount'       => $discount,
                'total'          => $total,
                'payment_method' => $paymentMethod,
                'amount_paid'    => $amountPaid,
                'change_amount'  => $changeAmount,
                'status'         => 'completed',
                'notes'          => $data['notes'] ?? '',
            ]);

            foreach ($saleItems as $saleItem) {
                $saleItem['sale_id'] = $saleId;
                Database::insert('sale_items', $saleItem);
            }

            Database::getInstance()->getConnection()->commit();

            $sale = Database::fetch(
                'SELECT s.*, u.name as cashier_name, c.name as customer_name
                 FROM sales s
                 LEFT JOIN users u ON u.id = s.user_id
                 LEFT JOIN customers c ON c.id = s.customer_id
                 WHERE s.id = :id',
                ['id' => $saleId]
            );

            $sale['items'] = $saleItems;

            Response::success($sale, 'Sale completed successfully');
        } catch (\Exception $e) {
            Database::getInstance()->getConnection()->rollBack();
            Response::error($e->getMessage(), 422);
        }
    }

    public function destroy(int $id): void
    {
        Auth::requireAuth();
        $sale = Database::fetch(
            'SELECT * FROM sales WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$sale) {
            Response::error('Sale not found', 404);
        }

        Database::getInstance()->getConnection()->beginTransaction();

        try {
            $items = Database::fetchAll(
                'SELECT product_id, quantity FROM sale_items WHERE sale_id = :sale_id',
                ['sale_id' => $id]
            );

            foreach ($items as $item) {
                if ($item['product_id']) {
                    Database::query(
                        'UPDATE product_stocks SET stock = stock + :qty WHERE product_id = :id AND store_id = :store_id',
                        ['qty' => $item['quantity'], 'id' => $item['product_id'], 'store_id' => $sale['store_id']]
                    );
                }
            }

            Database::update('sales', ['status' => 'voided'], 'id = :id', ['id' => $id]);

            Database::getInstance()->getConnection()->commit();
            Response::success(null, 'Sale voided successfully');
        } catch (\Exception $e) {
            Database::getInstance()->getConnection()->rollBack();
            Response::error($e->getMessage(), 422);
        }
    }
}
