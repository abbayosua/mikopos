<div x-data="sales()" x-init="load()">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Sales History</h2>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex gap-2">
            <input type="text" x-model="search" @input.debounce="load" placeholder="Search by invoice or customer..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="date" x-model="from" @change="load" class="px-3 py-2 border border-gray-300 rounded-lg">
            <input type="date" x-model="to" @change="load" class="px-3 py-2 border border-gray-300 rounded-lg">
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="p-3">Invoice</th>
                    <th class="p-3">Date</th>
                    <th class="p-3">Customer</th>
                    <th class="p-3">Cashier</th>
                    <th class="p-3">Payment</th>
                    <th class="p-3">Total</th>
                    <th class="p-3">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="s in items" :key="s.id">
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 font-medium" x-text="s.invoice_no"></td>
                        <td class="p-3 text-gray-500" x-text="s.created_at"></td>
                        <td class="p-3" x-text="s.customer_name || 'Walk-in'"></td>
                        <td class="p-3" x-text="s.cashier_name || '-'"></td>
                        <td class="p-3 capitalize" x-text="s.payment_method"></td>
                        <td class="p-3 font-bold" x-text="formatMoney(s.total)"></td>
                        <td class="p-3">
                            <span :class="s.status === 'completed' ? 'bg-green-100 text-green-800' : s.status === 'voided' ? 'bg-red-100 text-red-800' : 'bg-gray-100'" class="px-2 py-1 rounded-full text-xs capitalize" x-text="s.status"></span>
                        </td>
                        <td class="p-3">
                            <a :href="'/sales/' + s.id" class="text-indigo-600 hover:underline">View</a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <template x-if="!items.length">
            <p class="p-6 text-center text-gray-500">No sales found</p>
        </template>
    </div>
</div>

<script>
function sales() {
    return {
        items: [],
        search: '',
        from: '',
        to: '',
        async load() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.from) params.set('from', this.from);
            if (this.to) params.set('to', this.to);
            const res = await fetch('/api/sales?' + params);
            const data = await res.json();
            if (data.success) this.items = data.data;
        },
        formatMoney(n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0); }
    }
}
</script>
