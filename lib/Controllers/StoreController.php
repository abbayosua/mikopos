<?php

namespace Miko\Controllers;

use Miko\Auth;
use Miko\Database;
use Miko\Request;
use Miko\Response;
use Miko\Session;

class StoreController
{
    public function index(): void
    {
        Auth::requireAuth();
        $userId = Auth::id();

        $stores = Database::fetchAll(
            'SELECT s.* FROM stores s
             JOIN user_stores us ON us.store_id = s.id
             WHERE us.user_id = :user_id AND s.is_active = true
             ORDER BY s.name',
            ['user_id' => $userId]
        );

        Response::success($stores);
    }

    public function all(): void
    {
        Auth::requireAuth();

        $stores = Database::fetchAll(
            'SELECT * FROM stores WHERE tenant_id = :tenant_id AND is_active = true ORDER BY name',
            ['tenant_id' => Auth::tenantId()]
        );

        Response::success($stores);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $data = Request::all();

        $errors = Request::validate(['name' => 'required|min:1|max:255']);
        if (!empty($errors)) {
            Response::error('Validation failed', 422, $errors);
        }

        $id = Database::insert('stores', [
            'tenant_id' => Auth::tenantId(),
            'name'      => $data['name'],
            'code'      => $data['code'] ?? '',
            'address'   => $data['address'] ?? '',
            'phone'     => $data['phone'] ?? '',
        ]);

        $store = Database::fetch('SELECT * FROM stores WHERE id = :id', ['id' => $id]);
        Response::success($store, 'Store created successfully');
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $store = Database::fetch(
            'SELECT * FROM stores WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $id, 'tenant_id' => Auth::tenantId()]
        );

        if (!$store) {
            Response::error('Store not found', 404);
        }

        $data = Request::all();
        Database::update('stores', [
            'name'    => $data['name'] ?? $store['name'],
            'code'    => $data['code'] ?? $store['code'],
            'address' => $data['address'] ?? $store['address'],
            'phone'   => $data['phone'] ?? $store['phone'],
        ], 'id = :id', ['id' => $id]);

        $updated = Database::fetch('SELECT * FROM stores WHERE id = :id', ['id' => $id]);
        Response::success($updated, 'Store updated successfully');
    }

    public function switch(): void
    {
        Auth::requireAuth();
        $storeId = (int) Request::input('store_id');

        $access = Database::fetch(
            'SELECT s.* FROM stores s
             JOIN user_stores us ON us.store_id = s.id
             WHERE us.user_id = :user_id AND s.id = :store_id',
            ['user_id' => Auth::id(), 'store_id' => $storeId]
        );

        if (!$access) {
            Response::error('Store not found or no access', 404);
        }

        Session::set('store_id', $storeId);
        Session::set('store_name', $access['name']);

        Response::success(['store' => $access], 'Store switched');
    }

    public function current(): void
    {
        Auth::requireAuth();
        $storeId = Auth::storeId();

        if (!$storeId) {
            Response::success(null, 'No store selected');
        }

        $store = Database::fetch('SELECT * FROM stores WHERE id = :id', ['id' => $storeId]);
        Response::success($store);
    }
}
