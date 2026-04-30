<div x-data="reports()" x-init="load()">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Reports</h2>
    </div>

    <div x-show="loading" class="flex justify-center py-20">
        <i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i>
    </div>

    <div x-show="!loading">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Sales Summary -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold mb-4">Sales Summary</h3>
            <div class="mb-4 flex gap-2">
                <select x-model="period" @change="load" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-indigo-50 p-4 rounded-lg text-center">
                    <p class="text-sm text-gray-500">Total Sales</p>
                    <p class="text-2xl font-bold text-indigo-800" x-text="summary.count || 0"></p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <p class="text-sm text-gray-500">Revenue</p>
                    <p class="text-2xl font-bold text-green-800" x-text="formatMoney(summary.total)"></p>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold mb-4">Top Products</h3>
            <template x-if="topProducts.length">
                <div class="space-y-2">
                    <template x-for="(p, idx) in topProducts" :key="idx">
                        <div class="flex justify-between items-center py-1 border-b last:border-0">
                            <div>
                                <span class="font-medium text-sm" x-text="p.product_name"></span>
                                <span class="text-xs text-gray-500" x-text="'sold ' + p.total_qty + 'x'"></span>
                            </div>
                            <span class="font-bold text-sm" x-text="formatMoney(p.total_sales)"></span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!topProducts.length">
                <p class="text-gray-500 text-sm">No data yet</p>
            </template>
        </div>

        <!-- Low Stock Products -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold mb-4">Low Stock Products</h3>
            <template x-if="lowStock.length">
                <div class="space-y-2">
                    <template x-for="p in lowStock" :key="p.id">
                        <div class="flex justify-between items-center py-1 border-b last:border-0">
                            <div>
                                <p class="text-sm font-medium" x-text="p.name"></p>
                                <p class="text-xs text-gray-500" x-text="'SKU: ' + (p.sku || '-')"></p>
                            </div>
                            <span class="text-red-600 font-bold text-sm" x-text="p.stock + ' / ' + p.min_stock"></span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!lowStock.length">
                <p class="text-gray-500 text-sm">All products well-stocked</p>
            </template>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold mb-4">Recent Activity</h3>
            <template x-if="recentSales.length">
                <div class="space-y-2">
                    <template x-for="s in recentSales" :key="s.id">
                        <div class="flex justify-between items-center py-1 border-b last:border-0 text-sm">
                            <div>
                                <p class="font-medium" x-text="s.invoice_no"></p>
                                <p class="text-xs text-gray-500" x-text="s.created_at"></p>
                            </div>
                            <span class="font-bold" x-text="formatMoney(s.total)"></span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!recentSales.length">
                <p class="text-gray-500 text-sm">No recent sales</p>
            </template>
        </div>
    </div>
    </div>
</div>

<script>
function reports() {
    return {
        period: 'today',
        summary: { count: 0, total: 0 },
        topProducts: [],
        lowStock: [],
        recentSales: [],
        loading: true,
        async load() {
            this.loading = true;

            // 1. Show cached data instantly
            const cached = cache.get('reportStats');
            if (cached) {
                this.summary = cached.today_sales || { count: 0, total: 0 };
                this.lowStock = cached.low_stock || [];
                this.recentSales = cached.recent_sales || [];
                this.loading = false;
            }

            // 2. Fetch fresh in background
            const res = await apiFetch('/api/dashboard/stats');
            const data = await res.json();
            if (data.success) {
                const s = data.data;
                this.summary = s.today_sales || { count: 0, total: 0 };
                this.lowStock = s.low_stock || [];
                this.recentSales = s.recent_sales || [];
                cache.set('reportStats', s, 2 * 60 * 1000);
            }

            let topProductsData = cache.get('topProducts');
                const res2 = await apiFetch('/api/sales?limit=50');
                const data2 = await res2.json();
                if (data2.success) {
                    const productMap = {};
                    for (const sale of data2.data) {
                        const detailRes = await apiFetch('/api/sales/' + sale.id);
                        const detailData = await detailRes.json();
                        if (detailData.success && detailData.data.items) {
                            for (const item of detailData.data.items) {
                                if (!productMap[item.product_name]) {
                                    productMap[item.product_name] = { product_name: item.product_name, total_qty: 0, total_sales: 0 };
                                }
                                productMap[item.product_name].total_qty += item.quantity;
                                productMap[item.product_name].total_sales += parseFloat(item.subtotal);
                            }
                        }
                    }
                    topProductsData = Object.values(productMap).sort((a, b) => b.total_sales - a.total_sales).slice(0, 10);
                    cache.set('topProducts', topProductsData, 5 * 60 * 1000);
                }
            }
            this.topProducts = topProductsData || [];
            this.loading = false;
        },
        formatMoney(n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0); }
    }
}
</script>
