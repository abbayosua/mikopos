<div x-data="customers()" x-init="load()">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Customers</h2>
        <button @click="showModal = true" class="bg-indigo-800 text-white px-4 py-2 rounded-lg hover:bg-indigo-900 text-sm">
            <i class="fas fa-plus mr-1"></i> Add Customer
        </button>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <input type="text" x-model="search" @input.debounce="load" placeholder="Search customers..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="p-3">Name</th>
                    <th class="p-3">Phone</th>
                    <th class="p-3">Email</th>
                    <th class="p-3">Sales</th>
                    <th class="p-3">Total Spent</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="c in items" :key="c.id">
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 font-medium" x-text="c.name"></td>
                        <td class="p-3" x-text="c.phone || '-'"></td>
                        <td class="p-3 text-gray-500" x-text="c.email || '-'"></td>
                        <td class="p-3" x-text="c.sale_count || 0"></td>
                        <td class="p-3" x-text="formatMoney(c.total_spent)"></td>
                        <td class="p-3">
                            <button @click="edit(c)" class="text-indigo-600 hover:underline mr-2">Edit</button>
                            <button @click="remove(c.id)" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <template x-if="!items.length">
            <p class="p-6 text-center text-gray-500">No customers found</p>
        </template>
    </div>

    <!-- Modal -->
    <div x-show="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="showModal = false">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4" x-text="editing ? 'Edit Customer' : 'Add Customer'"></h3>
            <form @submit.prevent="save">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Name *</label>
                    <input type="text" x-model="form.name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Phone</label>
                        <input type="text" x-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                        <input type="email" x-model="form.email" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                    <textarea x-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                </div>
                <template x-if="error">
                    <div class="mb-4 text-red-600 text-sm" x-text="error"></div>
                </template>
                <div class="flex gap-3 justify-end">
                    <button type="button" @click="showModal = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" :disabled="loading" class="bg-indigo-800 text-white px-4 py-2 rounded-lg disabled:opacity-50" x-text="loading ? 'Saving...' : 'Save'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function customers() {
    return {
        items: [],
        search: '',
        showModal: false,
        editing: false,
        form: { name: '', phone: '', email: '', address: '' },
        loading: false,
        error: '',
        async load() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            const res = await apiFetch('/api/customers?' + params);
            const data = await res.json();
            if (data.success) this.items = data.data;
        },
        edit(c) {
            this.editing = true;
            this.form = { id: c.id, name: c.name, phone: c.phone || '', email: c.email || '', address: c.address || '' };
            this.showModal = true;
        },
        async save() {
            this.loading = true; this.error = '';
            try {
                const url = this.editing ? '/api/customers/' + this.form.id : '/api/customers';
                const method = this.editing ? 'PUT' : 'POST';
                const res = await apiFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
                const data = await res.json();
                if (data.success) { cache.remove('customers'); this.showModal = false; this.editing = false; this.form = { name: '', phone: '', email: '', address: '' }; this.load(); }
                else this.error = data.message;
            } catch(e) { this.error = 'Connection error'; }
            finally { this.loading = false; }
        },
        async remove(id) {
            if (!confirm('Delete this customer?')) return;
            const res = await apiFetch('/api/customers/' + id, { method: 'DELETE' });
            if ((await res.json()).success) { cache.remove('customers'); this.load(); }
        },
        formatMoney(n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0); }
    }
}
</script>
