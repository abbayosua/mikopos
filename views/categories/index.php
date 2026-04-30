<div x-data="categories()" x-init="load()">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-gray-800">Categories</h2>
        <button @click="showForm = true" class="bg-indigo-800 text-white px-4 py-2 rounded-lg hover:bg-indigo-900 text-sm">
            <i class="fas fa-plus mr-1"></i> Add Category
        </button>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="p-3">Name</th>
                    <th class="p-3">Description</th>
                    <th class="p-3">Products</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="cat in items" :key="cat.id">
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 font-medium" x-text="cat.name"></td>
                        <td class="p-3 text-gray-500" x-text="cat.description || '-'"></td>
                        <td class="p-3" x-text="cat.product_count || 0"></td>
                        <td class="p-3">
                            <button @click="edit(cat)" class="text-indigo-600 hover:underline mr-2">Edit</button>
                            <button @click="remove(cat.id)" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <template x-if="!items.length">
            <p class="p-6 text-center text-gray-500">No categories yet</p>
        </template>
    </div>

    <!-- Modal -->
    <div x-show="showForm" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="showForm = false">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4" x-text="editing ? 'Edit Category' : 'Add Category'"></h3>
            <form @submit.prevent="save">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Name *</label>
                    <input type="text" x-model="form.name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                    <textarea x-model="form.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <template x-if="error">
                    <div class="mb-4 text-red-600 text-sm" x-text="error"></div>
                </template>
                <div class="flex gap-3 justify-end">
                    <button type="button" @click="showForm = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" :disabled="loading" class="bg-indigo-800 text-white px-4 py-2 rounded-lg hover:bg-indigo-900 disabled:opacity-50" x-text="loading ? 'Saving...' : 'Save'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function categories() {
    return {
        items: [],
        showForm: false,
        editing: false,
        form: { name: '', description: '' },
        loading: false,
        error: '',
        async load() {
            const res = await apiFetch('/api/categories');
            const data = await res.json();
            if (data.success) this.items = data.data;
        },
        edit(cat) {
            this.editing = true;
            this.form = { id: cat.id, name: cat.name, description: cat.description || '' };
            this.showForm = true;
        },
        async save() {
            this.loading = true;
            this.error = '';
            try {
                const url = this.editing ? '/api/categories/' + this.form.id : '/api/categories';
                const method = this.editing ? 'PUT' : 'POST';
                const res = await apiFetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (data.success) {
                    this.showForm = false;
                    this.form = { name: '', description: '' };
                    this.editing = false;
                    cache.remove('categories');
                    this.load();
                } else {
                    this.error = data.message || 'Save failed';
                }
            } catch (e) {
                this.error = 'Connection error';
            } finally {
                this.loading = false;
            }
        },
        async remove(id) {
            if (!confirm('Delete this category?')) return;
            const res = await apiFetch('/api/categories/' + id, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) { cache.remove('categories'); this.load(); }
        }
    }
}
</script>
