<?php

namespace Miko\Controllers;

use Miko\Auth;
use Miko\Database;
use Miko\Request;
use Miko\Response;

class CustomerController
{
    private function tenantId(): int
    {
        return Auth::tenantId();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $search = Request::get('search', '');

        $params = ['tenant_id' => $this->tenantId()];
        $conditions = ['tenant_id = :tenant_id'];

        if ($search) {
            $conditions[] = '(name ILIKE :search OR phone ILIKE :search OR email ILIKE :search)';
            $params['search'] = "%{$search}%";
        }

        $where = implode(' AND ', $conditions);

        $customers = Database::fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id) as sale_count,
                    (SELECT COALESCE(SUM(s.total), 0) FROM sales s WHERE s.customer_id = c.id) as total_spent
             FROM customers c
             WHERE {$where}
             ORDER BY c.name",
            $params
        );

        Response::success($customers);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $customer = Database::fetch(
            'SELECT * FROM customers WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$customer) {
            Response::error('Customer not found', 404);
        }

        $customer['recent_sales'] = Database::fetchAll(
            'SELECT id, invoice_no, total, created_at FROM sales
             WHERE customer_id = :customer_id AND tenant_id = :tenant_id
             ORDER BY created_at DESC LIMIT 10',
            ['customer_id' => $id, 'tenant_id' => $this->tenantId()]
        );

        Response::success($customer);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $data = Request::all();

        $errors = Request::validate([
            'name' => 'required|min:1|max:255',
        ]);

        if (!empty($errors)) {
            Response::error('Validation failed', 422, $errors);
        }

        $id = Database::insert('customers', [
            'tenant_id' => $this->tenantId(),
            'name'      => $data['name'],
            'phone'     => $data['phone'] ?? '',
            'email'     => $data['email'] ?? '',
            'address'   => $data['address'] ?? '',
        ]);

        $customer = Database::fetch('SELECT * FROM customers WHERE id = :id', ['id' => $id]);
        Response::success($customer, 'Customer created successfully');
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $customer = Database::fetch(
            'SELECT * FROM customers WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$customer) {
            Response::error('Customer not found', 404);
        }

        $data = Request::all();

        Database::update(
            'customers',
            [
                'name'    => $data['name'] ?? $customer['name'],
                'phone'   => $data['phone'] ?? $customer['phone'],
                'email'   => $data['email'] ?? $customer['email'],
                'address' => $data['address'] ?? $customer['address'],
            ],
            'id = :id',
            ['id' => $id]
        );

        $updated = Database::fetch('SELECT * FROM customers WHERE id = :id', ['id' => $id]);
        Response::success($updated, 'Customer updated successfully');
    }

    public function destroy(int $id): void
    {
        Auth::requireAuth();
        $customer = Database::fetch(
            'SELECT * FROM customers WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$customer) {
            Response::error('Customer not found', 404);
        }

        Database::delete('customers', 'id = :id', ['id' => $id]);
        Response::success(null, 'Customer deleted successfully');
    }
}
