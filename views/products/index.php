<div x-data="products()" x-init="load()">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Products</h2>
        <a href="/products/create" class="bg-indigo-800 text-white px-4 py-2 rounded-lg hover:bg-indigo-900 text-sm">
            <i class="fas fa-plus mr-1"></i> Add Product
        </a>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <input type="text" x-model="search" @input.debounce="load" placeholder="Search products..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b bg-gray-50">
                        <th class="p-3">Name</th>
                        <th class="p-3">SKU</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Price</th>
                        <th class="p-3">Cost</th>
                        <th class="p-3">Stock</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in products" :key="p.id">
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3 font-medium" x-text="p.name"></td>
                            <td class="p-3 text-gray-500" x-text="p.sku || '-'"></td>
                            <td class="p-3" x-text="p.category_name || '-'"></td>
                            <td class="p-3" x-text="formatMoney(p.price)"></td>
                            <td class="p-3" x-text="formatMoney(p.cost)"></td>
                            <td class="p-3">
                                <span :class="p.stock <= p.min_stock ? 'text-red-600 font-bold' : 'text-gray-700'" x-text="p.stock"></span>
                            </td>
                            <td class="p-3">
                                <span :class="p.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 rounded-full text-xs" x-text="p.is_active ? 'Active' : 'Inactive'"></span>
                            </td>
                            <td class="p-3">
                                <a :href="'/products/' + p.id + '/edit'" class="text-indigo-600 hover:underline mr-2">Edit</a>
                                <button @click="remove(p.id)" class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="!products.length">
                <p class="p-6 text-center text-gray-500">No products found</p>
            </template>
        </div>
    </div>
</div>

<script>
function products() {
    return {
        products: [],
        search: '',
        async load() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            const res = await fetch('/api/products?' + params);
            const data = await res.json();
            if (data.success) this.products = data.data;
        },
        async remove(id) {
            if (!confirm('Delete this product?')) return;
            const res = await fetch('/api/products/' + id, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) this.load();
        },
        formatMoney(n) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0);
        }
    }
}
</script>
