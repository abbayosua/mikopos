<?php

namespace Miko\Controllers;

use Miko\Auth;
use Miko\Database;
use Miko\Request;
use Miko\Response;

class CategoryController
{
    private function tenantId(): int
    {
        return Auth::tenantId();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $categories = Database::fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count
             FROM categories c
             WHERE c.tenant_id = :tenant_id
             ORDER BY c.name',
            ['tenant_id' => $this->tenantId()]
        );

        Response::success($categories);
    }

    public function show(int $id): void
    {
        Auth::requireAuth();
        $category = Database::fetch(
            'SELECT * FROM categories WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$category) {
            Response::error('Category not found', 404);
        }

        Response::success($category);
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

        $existing = Database::fetch(
            'SELECT id FROM categories WHERE tenant_id = :tenant_id AND name = :name',
            ['tenant_id' => $this->tenantId(), 'name' => $data['name']]
        );

        if ($existing) {
            Response::error('Category with this name already exists', 422);
        }

        $id = Database::insert('categories', [
            'tenant_id'   => $this->tenantId(),
            'name'        => $data['name'],
            'description' => $data['description'] ?? '',
        ]);

        $category = Database::fetch(
            'SELECT * FROM categories WHERE id = :id',
            ['id' => $id]
        );

        Response::success($category, 'Category created successfully');
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $category = Database::fetch(
            'SELECT * FROM categories WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$category) {
            Response::error('Category not found', 404);
        }

        $data = Request::all();

        if (isset($data['name'])) {
            $existing = Database::fetch(
                'SELECT id FROM categories WHERE tenant_id = :tenant_id AND name = :name AND id != :id',
                ['tenant_id' => $this->tenantId(), 'name' => $data['name'], 'id' => $id]
            );

            if ($existing) {
                Response::error('Category with this name already exists', 422);
            }
        }

        Database::update(
            'categories',
            [
                'name'        => $data['name'] ?? $category['name'],
                'description' => $data['description'] ?? $category['description'],
            ],
            'id = :id',
            ['id' => $id]
        );

        $updated = Database::fetch('SELECT * FROM categories WHERE id = :id', ['id' => $id]);
        Response::success($updated, 'Category updated successfully');
    }

    public function destroy(int $id): void
    {
        Auth::requireAuth();
        $category = Database::fetch(
            'SELECT * FROM categories WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => $this->tenantId()]
        );

        if (!$category) {
            Response::error('Category not found', 404);
        }

        $productCount = Database::fetch(
            'SELECT COUNT(*) as count FROM products WHERE category_id = :id',
            ['id' => $id]
        );

        if ($productCount && $productCount['count'] > 0) {
            Response::error('Cannot delete category that has products. Reassign or delete products first.', 422);
        }

        Database::delete('categories', 'id = :id', ['id' => $id]);
        Response::success(null, 'Category deleted successfully');
    }
}
