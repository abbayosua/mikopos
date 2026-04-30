<div x-data="dashboard()" x-init="load()">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
        <p class="text-gray-600">Welcome back, <?= htmlspecialchars(\Miko\Auth::user()['name'] ?? '') ?></p>
    </div>

    <div x-show="loading" class="flex justify-center py-20">
        <i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i>
    </div>

    <div x-show="!loading">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Today's Revenue</p>
                    <p class="text-2xl font-bold" x-text="formatMoney(stats.today_sales?.total || 0)"></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Today's Orders</p>
                    <p class="text-2xl font-bold" x-text="stats.today_sales?.count || 0"></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="bg-purple-100 p-3 rounded-full">
                    <i class="fas fa-box text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Products</p>
                    <p class="text-2xl font-bold" x-text="stats.product_count || 0"></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center gap-3">
                <div class="bg-orange-100 p-3 rounded-full">
                    <i class="fas fa-users text-orange-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Customers</p>
                    <p class="text-2xl font-bold" x-text="stats.customer_count || 0"></p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Monthly Sales -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-700 mb-3">This Month</h3>
            <div class="flex items-center gap-3">
                <div class="bg-indigo-100 p-3 rounded-full">
                    <i class="fas fa-chart-line text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Revenue</p>
                    <p class="text-2xl font-bold" x-text="formatMoney(stats.month_sales?.total || 0)"></p>
                    <p class="text-sm text-gray-500" x-text="(stats.month_sales?.count || 0) + ' transactions'"></p>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-700 mb-3">Low Stock Alerts</h3>
            <template x-if="stats.low_stock?.length">
                <div>
                    <template x-for="p in stats.low_stock" :key="p.id">
                        <div class="flex justify-between items-center py-2 border-b last:border-0">
                            <div>
                                <p class="font-medium" x-text="p.name"></p>
                                <p class="text-sm text-gray-500" x-text="'SKU: ' + p.sku"></p>
                            </div>
                            <span class="text-red-600 font-bold" x-text="p.stock + ' left'"></span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!stats.low_stock?.length">
                <p class="text-gray-500 text-sm">All products are well-stocked</p>
            </template>
        </div>

        <!-- Recent Sales -->
        <div class="bg-white rounded-lg shadow p-4 lg:col-span-2">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold text-gray-700">Recent Sales</h3>
                <a href="/sales" class="text-indigo-600 text-sm hover:underline">View All</a>
            </div>
            <template x-if="stats.recent_sales?.length">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="pb-2">Invoice</th>
                            <th class="pb-2">Customer</th>
                            <th class="pb-2">Total</th>
                            <th class="pb-2">Date</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="s in stats.recent_sales" :key="s.id">
                            <tr class="border-b last:border-0">
                                <td class="py-2" x-text="s.invoice_no"></td>
                                <td class="py-2" x-text="s.customer_name || 'Walk-in'"></td>
                                <td class="py-2 font-medium" x-text="formatMoney(s.total)"></td>
                                <td class="py-2 text-gray-500" x-text="s.created_at"></td>
                                <td class="py-2"><a :href="'/sales/' + s.id" class="text-indigo-600 hover:underline">View</a></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
            <template x-if="!stats.recent_sales?.length">
                <p class="text-gray-500 text-sm">No sales yet</p>
            </template>
        </div>
    </div>
    </div>
</div>

<script>
function dashboard() {
    return {
        stats: {},
        loading: true,
        async load() {
            this.loading = true;
            const res = await apiFetch('/api/dashboard/stats');
            const data = await res.json();
            if (data.success) this.stats = data.data;
            this.loading = false;
        },
        formatMoney(n) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0);
        }
    }
}
</script>
