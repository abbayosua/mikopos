<div x-data="stores()" x-init="load()">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Stores</h2>
        <button @click="showForm = true; editing = false; form = {name:'', code:'', address:'', phone:''}" class="bg-indigo-800 text-white px-4 py-2 rounded-lg hover:bg-indigo-900 text-sm">
            <i class="fas fa-plus mr-1"></i> Add Store
        </button>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="p-3">Name</th>
                    <th class="p-3">Code</th>
                    <th class="p-3">Phone</th>
                    <th class="p-3">Address</th>
                    <th class="p-3">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="s in items" :key="s.id">
                    <tr class="border-b hover:bg-gray-50" :class="s.id == currentStore?.id ? 'bg-indigo-50' : ''">
                        <td class="p-3 font-medium" x-text="s.name"></td>
                        <td class="p-3 text-gray-500" x-text="s.code || '-'"></td>
                        <td class="p-3" x-text="s.phone || '-'"></td>
                        <td class="p-3 text-gray-500" x-text="s.address || '-'"></td>
                        <td class="p-3">
                            <span :class="s.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 rounded-full text-xs" x-text="s.is_active ? 'Active' : 'Inactive'"></span>
                        </td>
                        <td class="p-3">
                            <button @click="edit(s)" class="text-indigo-600 hover:underline mr-2">Edit</button>
                            <button @click="switchStore(s)" :class="s.id == currentStore?.id ? 'text-green-600' : 'text-gray-600'" class="hover:underline" x-text="s.id == currentStore?.id ? 'Active' : 'Switch'"></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <template x-if="!items.length">
            <p class="p-6 text-center text-gray-500">No stores yet</p>
        </template>
    </div>

    <!-- Modal -->
    <div x-show="showForm" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="showForm = false">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4" x-text="editing ? 'Edit Store' : 'Add Store'"></h3>
            <form @submit.prevent="save">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Name *</label>
                    <input type="text" x-model="form.name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Code</label>
                        <input type="text" x-model="form.code" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Phone</label>
                        <input type="text" x-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
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
                    <button type="button" @click="showForm = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" :disabled="loading" class="bg-indigo-800 text-white px-4 py-2 rounded-lg disabled:opacity-50" x-text="loading ? 'Saving...' : 'Save'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function stores() {
    return {
        items: [],
        currentStore: null,
        showForm: false,
        editing: false,
        form: { name: '', code: '', address: '', phone: '' },
        loading: false,
        error: '',
        async load() {
            const [allRes, curRes] = await Promise.all([
                fetch('/api/stores'),
                fetch('/api/auth/me')
            ]);
            const all = await allRes.json();
            const cur = await curRes.json();
            if (all.success) this.items = all.data;
            if (cur.success) this.currentStore = cur.data.store;
        },
        edit(s) {
            this.editing = true;
            this.form = { id: s.id, name: s.name, code: s.code || '', address: s.address || '', phone: s.phone || '' };
            this.showForm = true;
        },
        async save() {
            this.loading = true; this.error = '';
            try {
                const url = this.editing ? '/api/stores/' + this.form.id : '/api/stores';
                const method = this.editing ? 'PUT' : 'POST';
                const res = await fetch(url, {
                    method, headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (data.success) { this.showForm = false; this.load(); }
                else this.error = data.message;
            } catch(e) { this.error = 'Connection error'; }
            finally { this.loading = false; }
        },
        async switchStore(store) {
            const res = await fetch('/api/stores/switch', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ store_id: store.id })
            });
            const data = await res.json();
            if (data.success) { this.currentStore = store; this.load(); window.location.reload(); }
        }
    }
}
</script>
