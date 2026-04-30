// ============================================================
// MIKO Pos — Vue 3 SPA
// ============================================================
const { createApp } = Vue;

// ── mixins ──
const fmtMoney = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0);
const requireAuth = (to, from, next) => {
    if (!localStorage.getItem('token')) next('/login');
    else next();
};

// ── Login ──
const Login = {
    template: `
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
      <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-indigo-800">MIKO Pos</h1>
          <p class="text-gray-600 mt-2">Sign in to your account</p>
        </div>
        <form @submit.prevent="submit">
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-medium mb-2">Email</label>
            <input type="email" v-model="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-medium mb-2">Password</label>
            <input type="password" v-model="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
          </div>
          <div v-if="error" class="mb-4 text-red-600 text-sm">{{ error }}</div>
          <button type="submit" :disabled="loading" class="w-full bg-indigo-800 text-white py-2 rounded-lg hover:bg-indigo-900 disabled:opacity-50">{{ loading ? 'Signing in...' : 'Sign In' }}</button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-600">
          Don't have an account? <router-link to="/register" class="text-indigo-800 hover:underline">Register</router-link>
        </p>
      </div>
    </div>`,
    data: () => ({ email: '', password: '', loading: false, error: '' }),
    methods: {
        async submit() {
            this.loading = true; this.error = '';
            try {
                const res = await apiPost('/api/auth/login', { email: this.email, password: this.password });
                const data = await res.json();
                if (data.success) {
                    localStorage.setItem('token', data.data.token);
                    if (data.data.store) localStorage.setItem('store_id', data.data.store.id);
                    window.location.href = '/app';
                } else {
                    this.error = data.message || 'Login failed';
                }
            } catch(e) { this.error = 'Connection error'; }
            finally { this.loading = false; }
        }
    }
};

// ── Register ──
const Register = {
    template: `
    <div class="min-h-screen flex items-center justify-center bg-gray-100 py-12">
      <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-indigo-800">MIKO Pos</h1>
          <p class="text-gray-600 mt-2">Create your business account</p>
        </div>
        <form @submit.prevent="submit">
          <div class="mb-4"><label class="block text-gray-700 text-sm font-medium mb-2">Business Name</label><input type="text" v-model="tenant_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
          <div class="mb-4"><label class="block text-gray-700 text-sm font-medium mb-2">Your Name</label><input type="text" v-model="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
          <div class="mb-4"><label class="block text-gray-700 text-sm font-medium mb-2">Email</label><input type="email" v-model="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required></div>
          <div class="mb-6"><label class="block text-gray-700 text-sm font-medium mb-2">Password (min 6)</label><input type="password" v-model="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required minlength="6"></div>
          <div v-if="error" class="mb-4 text-red-600 text-sm">{{ error }}</div>
          <button type="submit" :disabled="loading" class="w-full bg-indigo-800 text-white py-2 rounded-lg disabled:opacity-50">{{ loading ? 'Creating...' : 'Create Account' }}</button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-600">Already have an account? <router-link to="/login" class="text-indigo-800 hover:underline">Sign in</router-link></p>
      </div>
    </div>`,
    data: () => ({ tenant_name: '', name: '', email: '', password: '', loading: false, error: '' }),
    methods: {
        async submit() {
            this.loading = true; this.error = '';
            try {
                const res = await apiPost('/api/auth/register', { tenant_name: this.tenant_name, name: this.name, email: this.email, password: this.password });
                const data = await res.json();
                if (data.success) {
                    localStorage.setItem('token', data.data.token);
                    localStorage.setItem('store_id', data.data.store.id);
                    window.location.href = '/app';
                } else { this.error = data.message || 'Registration failed'; }
            } catch(e) { this.error = 'Connection error'; }
            finally { this.loading = false; }
        }
    }
};

// ── Dashboard ──
const Dashboard = {
    template: `
    <div>
      <div class="mb-6"><h2 class="text-2xl font-bold text-gray-800">Dashboard</h2></div>
      <div v-if="loading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-lg shadow p-4"><div class="flex items-center gap-3"><div class="bg-green-100 p-3 rounded-full"><i class="fas fa-dollar-sign text-green-600 text-xl"></i></div><div><p class="text-sm text-gray-500">Today's Revenue</p><p class="text-2xl font-bold">{{ fmt(stats.today_sales?.total || 0) }}</p></div></div></div>
          <div class="bg-white rounded-lg shadow p-4"><div class="flex items-center gap-3"><div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-shopping-cart text-blue-600 text-xl"></i></div><div><p class="text-sm text-gray-500">Today's Orders</p><p class="text-2xl font-bold">{{ stats.today_sales?.count || 0 }}</p></div></div></div>
          <div class="bg-white rounded-lg shadow p-4"><div class="flex items-center gap-3"><div class="bg-purple-100 p-3 rounded-full"><i class="fas fa-box text-purple-600 text-xl"></i></div><div><p class="text-sm text-gray-500">Products</p><p class="text-2xl font-bold">{{ stats.product_count || 0 }}</p></div></div></div>
          <div class="bg-white rounded-lg shadow p-4"><div class="flex items-center gap-3"><div class="bg-orange-100 p-3 rounded-full"><i class="fas fa-users text-orange-600 text-xl"></i></div><div><p class="text-sm text-gray-500">Customers</p><p class="text-2xl font-bold">{{ stats.customer_count || 0 }}</p></div></div></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white rounded-lg shadow p-4"><h3 class="font-semibold text-gray-700 mb-3">This Month</h3><div class="flex items-center gap-3"><div class="bg-indigo-100 p-3 rounded-full"><i class="fas fa-chart-line text-indigo-600 text-xl"></i></div><div><p class="text-sm text-gray-500">Revenue</p><p class="text-2xl font-bold">{{ fmt(stats.month_sales?.total || 0) }}</p><p class="text-sm text-gray-500">{{ (stats.month_sales?.count || 0) + ' transactions' }}</p></div></div></div>
          <div class="bg-white rounded-lg shadow p-4"><h3 class="font-semibold text-gray-700 mb-3">Low Stock Alerts</h3><div v-if="stats.low_stock?.length"><div v-for="p in stats.low_stock" :key="p.id" class="flex justify-between items-center py-2 border-b last:border-0"><div><p class="font-medium">{{ p.name }}</p><p class="text-sm text-gray-500">SKU: {{ p.sku }}</p></div><span class="text-red-600 font-bold">{{ p.stock }} left</span></div></div><p v-else class="text-gray-500 text-sm">All products well-stocked</p></div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 mt-6"><div class="flex justify-between items-center mb-3"><h3 class="font-semibold text-gray-700">Recent Sales</h3><router-link to="/sales" class="text-indigo-600 text-sm hover:underline">View All</router-link></div><table v-if="stats.recent_sales?.length" class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b"><th class="pb-2">Invoice</th><th class="pb-2">Customer</th><th class="pb-2">Total</th><th class="pb-2">Date</th><th></th></tr></thead><tbody><tr v-for="s in stats.recent_sales" :key="s.id" class="border-b last:border-0"><td class="py-2">{{ s.invoice_no }}</td><td class="py-2">{{ s.customer_name || 'Walk-in' }}</td><td class="py-2 font-medium">{{ fmt(s.total) }}</td><td class="py-2 text-gray-500">{{ s.created_at }}</td><td><router-link :to="'/sales/'+s.id" class="text-indigo-600 hover:underline">View</router-link></td></tr></tbody></table><p v-else class="text-gray-500 text-sm">No sales yet</p></div>
      </div>
    </div>`,
    data: () => ({ stats: {}, loading: true }),
    methods: { fmt: fmtMoney },
    async created() {
        const cached = mikoCache.get('dashboard');
        if (cached) { this.stats = cached; this.loading = false; }
        const res = await apiJson('/api/dashboard/stats');
        if (res.success) { this.stats = res.data; mikoCache.set('dashboard', res.data, 2 * 60 * 1000); }
        this.loading = false;
    },
};

// ── POS (simplified — full offline version later) ──
const Pos = {
    template: `
    <div>
      <div v-if="pageLoading && !currentStore" class="flex items-center justify-center h-[calc(100vh-8rem)]"><i class="fas fa-spinner fa-pulse text-5xl text-indigo-800"></i></div>
      <div v-else-if="!currentStore" class="flex items-center justify-center h-[calc(100vh-8rem)]">
        <div class="bg-white rounded-lg shadow-lg p-8 text-center max-w-md">
          <i class="fas fa-store text-6xl text-indigo-300 mb-4"></i>
          <h2 class="text-xl font-bold mb-2">Select a Store</h2>
          <p class="text-gray-500 mb-4">You need to select a store before using the POS.</p>
          <div class="space-y-2"><button v-for="s in stores" :key="s.id" @click="selectStore(s.id)" class="w-full bg-indigo-800 text-white py-3 rounded-lg hover:bg-indigo-900 font-medium"><i class="fas fa-store mr-2"></i>{{ s.name }}</button></div>
        </div>
      </div>
      <div v-else class="flex gap-4 h-[calc(100vh-8rem)]">
        <div class="flex-1 flex flex-col">
          <div class="mb-3 flex items-center gap-2"><span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm flex items-center gap-1"><i class="fas fa-store text-xs"></i>{{ currentStore.name }}</span><button @click="currentStore=null" class="text-xs text-indigo-600 hover:underline">(change)</button></div>
          <div class="mb-2 flex gap-2"><div class="flex-1 flex gap-2 items-center bg-white border-2 border-indigo-300 rounded-lg px-3 py-1"><i class="fas fa-barcode text-indigo-400 text-lg"></i><input type="text" ref="barcode" v-model="barcode" @keydown.enter="scanBarcode" placeholder="Scan barcode..." class="flex-1 py-2 outline-none text-lg" autofocus></div></div>
          <div class="mb-3 flex gap-2"><input type="text" v-model="search" @input="searchProducts" placeholder="Search product..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg"><select v-model="catFilter" @change="searchProducts" class="px-3 py-2 border border-gray-300 rounded-lg"><option value="">All</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
          <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
              <div v-for="p in filteredProducts" :key="p.id" @click="addToCart(p)" class="bg-white rounded-lg shadow p-3 cursor-pointer hover:shadow-md border-2" :class="inCart(p.id) ? 'border-indigo-500' : 'border-transparent'"><div class="bg-gray-100 h-16 rounded flex items-center justify-center text-gray-400 mb-1"><i class="fas fa-box text-2xl"></i></div><p class="font-medium text-sm truncate">{{ p.name }}</p><p class="text-indigo-800 font-bold text-sm">{{ fmt(p.price) }}</p><p class="text-xs text-gray-500">Stock: {{ p.stock }}</p></div>
            </div>
            <p v-if="!filteredProducts.length" class="text-center text-gray-500 mt-8">No products found</p>
          </div>
        </div>
        <div class="w-96 bg-white rounded-lg shadow flex flex-col">
          <div class="p-4 border-b"><h3 class="font-semibold text-lg">Current Sale</h3></div>
          <div class="p-3 border-b"><select v-model="customer_id" class="w-full px-2 py-1 border rounded text-sm"><option value="">Walk-in</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
          <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <div v-for="(item, idx) in cart" :key="idx" class="flex items-center gap-2 bg-gray-50 rounded p-2">
              <div class="flex-1 min-w-0"><p class="text-sm font-medium truncate">{{ item.name }}</p><p class="text-xs text-gray-500">{{ fmt(item.price) }} x {{ item.quantity }}</p></div>
              <div class="flex items-center gap-1"><button @click="updateQty(idx, -1)" class="w-6 h-6 bg-gray-200 rounded-full text-sm">-</button><span class="w-8 text-center text-sm font-bold">{{ item.quantity }}</span><button @click="updateQty(idx, 1)" class="w-6 h-6 bg-gray-200 rounded-full text-sm">+</button></div>
              <p class="text-sm font-bold w-20 text-right">{{ fmt(item.subtotal) }}</p>
              <button @click="removeFromCart(idx)" class="text-red-500 text-sm"><i class="fas fa-times"></i></button>
            </div>
            <div v-if="!cart.length" class="text-center text-gray-400 mt-8"><i class="fas fa-shopping-cart text-4xl mb-2"></i><p>Cart is empty</p></div>
          </div>
          <div class="border-t p-4 space-y-2">
            <div class="flex justify-between text-sm"><span>Subtotal</span><span>{{ fmt(subtotal) }}</span></div>
            <div class="flex justify-between text-sm items-center"><span>Discount</span><input type="number" v-model.number="discount" class="w-24 px-2 py-1 border rounded text-right text-sm" placeholder="0"></div>
            <div class="flex justify-between text-sm items-center"><span>Tax</span><input type="number" v-model.number="tax" class="w-24 px-2 py-1 border rounded text-right text-sm" placeholder="0"></div>
            <div class="flex justify-between font-bold text-lg border-t pt-2"><span>Total</span><span>{{ fmt(total) }}</span></div>
            <div class="flex gap-2"><select v-model="payment_method" class="flex-1 px-2 py-2 border rounded text-sm"><option value="cash">Cash</option><option value="card">Card</option><option value="transfer">Transfer</option></select><input type="number" v-model.number="amount_paid" placeholder="Paid" class="w-32 px-2 py-2 border rounded text-right text-sm"></div>
            <div v-if="change > 0" class="flex justify-between text-sm"><span>Change</span><span class="text-green-600 font-bold">{{ fmt(change) }}</span></div>
            <div v-if="saleError" class="text-red-600 text-sm">{{ saleError }}</div>
            <button @click="checkout" :disabled="saleLoading || !cart.length" class="w-full bg-indigo-800 text-white py-3 rounded-lg font-bold disabled:opacity-50 text-lg">{{ saleLoading ? 'Processing...' : 'Charge ' + fmt(total) }}</button>
          </div>
        </div>
      </div>
      <!-- Receipt modal -->
      <div v-if="receipt" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="receipt=null">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
          <div class="text-center mb-4"><h3 class="font-bold text-lg">MIKO Pos</h3><p class="text-sm text-gray-500">Sale Completed</p></div>
          <div class="border-t border-b py-3 mb-3 space-y-1 text-sm"><p><strong>Invoice:</strong> {{ receipt.invoice_no }}</p><p><strong>Date:</strong> {{ receipt.created_at }}</p><p><strong>Cashier:</strong> {{ receipt.cashier_name }}</p><p v-if="receipt.customer_name"><strong>Customer:</strong> {{ receipt.customer_name }}</p></div>
          <div class="mb-3 space-y-1 text-sm"><div v-for="item in receipt.items" :key="item.id" class="flex justify-between"><span>{{ item.product_name }} x{{ item.quantity }}</span><span>{{ fmt(item.subtotal) }}</span></div></div>
          <div class="border-t pt-2 font-bold flex justify-between"><span>Total</span><span>{{ fmt(receipt.total) }}</span></div>
          <div class="text-sm flex justify-between"><span>Paid</span><span>{{ fmt(receipt.amount_paid) }}</span></div>
          <div v-if="receipt.change_amount > 0" class="text-sm flex justify-between"><span>Change</span><span>{{ fmt(receipt.change_amount) }}</span></div>
          <div class="mt-4 flex gap-2"><button @click="receipt=null; resetCart()" class="flex-1 bg-indigo-800 text-white py-2 rounded-lg">New Sale</button></div>
        </div>
      </div>
    </div>`,
    data: () => ({
        pageLoading: true, products: [], categories: [], customers: [], stores: [], currentStore: null,
        search: '', catFilter: '', barcode: '', cart: [], customer_id: '',
        discount: 0, tax: 0, subtotal: 0, total: 0, amount_paid: 0, change: 0,
        payment_method: 'cash', saleLoading: false, saleError: '', receipt: null,
    }),
    computed: {
        filteredProducts() {
            let p = this.products;
            if (this.search) { const q = this.search.toLowerCase(); p = p.filter(x => x.name.toLowerCase().includes(q) || (x.sku||'').toLowerCase().includes(q)); }
            if (this.catFilter) p = p.filter(x => x.category_id == this.catFilter);
            return p;
        },
    },
    methods: {
        fmt: fmtMoney,
        inCart(id) { return this.cart.some(i => i.product_id === id); },
        calcTotals() { this.subtotal = this.cart.reduce((s, i) => s + i.subtotal, 0); this.total = this.subtotal - this.discount + this.tax; this.change = Math.max(0, this.amount_paid - this.total); },
        addToCart(p) {
            const ex = this.cart.find(i => i.product_id === p.id);
            if (ex) { if (ex.quantity < p.stock) { ex.quantity++; ex.subtotal = ex.price * ex.quantity; } }
            else this.cart.push({ product_id: p.id, name: p.name, price: parseFloat(p.price), quantity: 1, subtotal: parseFloat(p.price) });
            this.calcTotals();
        },
        updateQty(idx, delta) { const i = this.cart[idx]; i.quantity = Math.max(1, i.quantity + delta); i.subtotal = i.price * i.quantity; this.calcTotals(); },
        removeFromCart(idx) { this.cart.splice(idx, 1); this.calcTotals(); },
        searchProducts() {}, // client-side filter via computed
        async scanBarcode() { if (!this.barcode) return; const res = await apiJson('/api/products/search?q='+encodeURIComponent(this.barcode)); if (res.success && res.data.length === 1) { this.addToCart(res.data[0]); this.barcode = ''; } },
        async selectStore(id) {
            const res = await apiPost('/api/stores/switch', { store_id: id });
            const d = await res.json();
            if (d.success) { localStorage.setItem('store_id', id); this.currentStore = d.data.store; await this.loadInit(); }
        },
        async loadInit() {
            const cached = mikoCache.get('posInit');
            if (cached) { this.products = cached.products; this.categories = cached.categories; this.customers = cached.customers; this.pageLoading = false; }
            const res = await apiJson('/api/pos/init');
            if (res.success) { this.products = res.data.products; this.categories = res.data.categories; this.customers = res.data.customers; mikoCache.set('posInit', { products: this.products, categories: this.categories, customers: this.customers }, 300000); }
            this.pageLoading = false;
        },
        resetCart() { this.cart = []; this.customer_id = ''; this.discount = 0; this.tax = 0; this.amount_paid = 0; this.change = 0; this.subtotal = 0; this.total = 0; this.saleError = ''; mikoCache.removePattern('products'); },
        async checkout() {
            if (!this.cart.length) return; if (this.amount_paid < this.total) { this.saleError = 'Amount paid less than total'; return; }
            this.saleLoading = true; this.saleError = '';
            try {
                const res = await apiPost('/api/sales', { items: this.cart.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price })), customer_id: this.customer_id || null, discount: this.discount, tax: this.tax, payment_method: this.payment_method, amount_paid: this.amount_paid });
                const d = await res.json();
                if (d.success) { this.receipt = d.data; mikoCache.removePattern('products'); } else this.saleError = d.message;
            } catch(e) { this.saleError = 'Connection error'; }
            finally { this.saleLoading = false; }
        },
    },
    async created() {
        const me = await apiJson('/api/auth/me');
        if (me.success) { this.stores = me.data.stores || []; const cachedStore = localStorage.getItem('store_id'); if (cachedStore) this.currentStore = me.data.stores.find(s => s.id == cachedStore) || null; }
        if (this.currentStore) await this.loadInit(); else this.pageLoading = false;
    },
};

// ── Products list ──
const Products = {
    template: `
    <div>
      <div class="flex justify-between items-center mb-4"><h2 class="text-2xl font-bold">Products</h2><router-link to="/products/create" class="bg-indigo-800 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i> Add Product</router-link></div>
      <div v-if="loading && !items.length" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else class="bg-white rounded-lg shadow">
        <div class="p-4 border-b"><input type="text" v-model="search" @input="load" placeholder="Search..." class="w-full px-3 py-2 border rounded-lg"></div>
        <table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b bg-gray-50"><th class="p-3">Name</th><th class="p-3">SKU</th><th class="p-3">Category</th><th class="p-3">Price</th><th class="p-3">Stock</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead><tbody><tr v-for="p in items" :key="p.id" class="border-b hover:bg-gray-50"><td class="p-3 font-medium">{{ p.name }}</td><td class="p-3">{{ p.sku || '-' }}</td><td class="p-3">{{ p.category_name || '-' }}</td><td class="p-3">{{ fmt(p.price) }}</td><td class="p-3"><span :class="p.stock <= p.min_stock ? 'text-red-600 font-bold' : ''">{{ p.stock }}</span></td><td class="p-3"><span :class="p.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100' " class="px-2 py-1 rounded-full text-xs">{{ p.is_active ? 'Active' : 'Inactive' }}</span></td><td class="p-3"><router-link :to="'/products/'+p.id+'/edit'" class="text-indigo-600 hover:underline mr-2">Edit</router-link><button @click="remove(p.id)" class="text-red-600 hover:underline">Delete</button></td></tr></tbody></table>
        <p v-if="!items.length" class="p-6 text-center text-gray-500">No products found</p>
      </div>
    </div>`,
    data: () => ({ items: [], search: '', loading: true }),
    methods: {
        fmt: fmtMoney,
        async load() {
            const params = new URLSearchParams(); if (this.search) params.set('search', this.search);
            if (!this.search) { const c = mikoCache.get('products'); if (c) { this.items = c; this.loading = false; } }
            const res = await apiJson('/api/products?' + params);
            if (res.success) { this.items = res.data; if (!this.search) mikoCache.set('products', res.data, 300000); }
            this.loading = false;
        },
        async remove(id) { if (!confirm('Delete?')) return; await apiDelete('/api/products/'+id); mikoCache.remove('products'); this.load(); },
    },
    created() { this.load(); },
};

// ── Products form ──
const ProductForm = {
    template: `
    <div><div class="mb-4"><h2 class="text-2xl font-bold">{{ editing ? 'Edit Product' : 'Add Product' }}</h2></div>
      <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <form @submit.prevent="save">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2"><label class="block text-sm font-medium mb-2">Name *</label><input type="text" v-model="form.name" class="w-full px-3 py-2 border rounded-lg" required></div>
            <div><label class="block text-sm font-medium mb-2">SKU</label><input type="text" v-model="form.sku" class="w-full px-3 py-2 border rounded-lg"></div>
            <div><label class="block text-sm font-medium mb-2">Barcode</label><input type="text" v-model="form.barcode" class="w-full px-3 py-2 border rounded-lg"></div>
            <div><label class="block text-sm font-medium mb-2">Category</label><select v-model="form.category_id" class="w-full px-3 py-2 border rounded-lg"><option value="">None</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
            <div><label class="block text-sm font-medium mb-2">Price *</label><input type="number" step="0.01" v-model="form.price" class="w-full px-3 py-2 border rounded-lg" required></div>
            <div><label class="block text-sm font-medium mb-2">Cost</label><input type="number" step="0.01" v-model="form.cost" class="w-full px-3 py-2 border rounded-lg"></div>
            <div><label class="block text-sm font-medium mb-2">Stock</label><input type="number" v-model="form.stock" class="w-full px-3 py-2 border rounded-lg"></div>
            <div><label class="block text-sm font-medium mb-2">Min Stock</label><input type="number" v-model="form.min_stock" class="w-full px-3 py-2 border rounded-lg"></div>
            <div class="col-span-2"><label class="block text-sm font-medium mb-2">Description</label><textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea></div>
          </div>
          <div v-if="error" class="mt-4 text-red-600 text-sm">{{ error }}</div>
          <div class="mt-6 flex gap-3"><button type="submit" :disabled="loading" class="bg-indigo-800 text-white px-6 py-2 rounded-lg disabled:opacity-50">{{ loading ? 'Saving...' : 'Save' }}</button><router-link to="/products" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">Cancel</router-link></div>
        </form>
      </div>
    </div>`,
    data: () => ({ editing: false, form: { name: '', sku: '', barcode: '', category_id: '', price: '', cost: '', stock: 0, min_stock: 0, description: '' }, categories: [], loading: false, error: '' }),
    methods: {
        async save() {
            this.loading = true; this.error = '';
            try {
                const url = this.editing ? '/api/products/' + this.$route.params.id : '/api/products';
                const method = this.editing ? 'PUT' : 'POST';
                const res = await apiFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
                const d = await res.json();
                if (d.success) { mikoCache.removePattern('products'); this.$router.push('/products'); }
                else this.error = d.message;
            } catch(e) { this.error = 'Connection error'; }
            finally { this.loading = false; }
        },
    },
    async created() {
        const catRes = await apiJson('/api/categories');
        if (catRes.success) this.categories = catRes.data;
        if (this.$route.params.id) {
            this.editing = true;
            const res = await apiJson('/api/products/' + this.$route.params.id);
            if (res.success) this.form = res.data;
        }
    },
};

// ── Categories ──
const Categories = {
    template: `
    <div><div class="flex justify-between items-center mb-4"><h2 class="text-2xl font-bold">Categories</h2><button @click="openForm()" class="bg-indigo-800 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i> Add</button></div>
      <div v-if="pageLoading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else class="bg-white rounded-lg shadow"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b bg-gray-50"><th class="p-3">Name</th><th class="p-3">Products</th><th class="p-3"></th></tr></thead><tbody><tr v-for="cat in items" :key="cat.id" class="border-b hover:bg-gray-50"><td class="p-3 font-medium">{{ cat.name }}</td><td class="p-3">{{ cat.product_count || 0 }}</td><td class="p-3"><button @click="openForm(cat)" class="text-indigo-600 hover:underline mr-2">Edit</button><button @click="remove(cat.id)" class="text-red-600 hover:underline">Delete</button></td></tr></tbody></table></div>
      <div v-if="showForm" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="showForm=false"><div class="bg-white rounded-lg p-6 w-full max-w-md"><h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit' : 'Add' }} Category</h3><form @submit.prevent="save"><div class="mb-4"><input type="text" v-model="form.name" class="w-full px-3 py-2 border rounded-lg" required></div><div class="flex gap-3 justify-end"><button type="button" @click="showForm=false" class="bg-gray-200 px-4 py-2 rounded-lg">Cancel</button><button type="submit" :disabled="loading" class="bg-indigo-800 text-white px-4 py-2 rounded-lg disabled:opacity-50">{{ loading ? 'Saving...' : 'Save' }}</button></div></form></div></div>
    </div>`,
    data: () => ({ items: [], showForm: false, editing: false, form: { name: '' }, loading: false, pageLoading: true }),
    methods: {
        openForm(cat) { this.editing = !!cat; this.form = cat ? { id: cat.id, name: cat.name } : { name: '' }; this.showForm = true; },
        async load() {
            const cached = mikoCache.get('categories'); if (cached) { this.items = cached; this.pageLoading = false; }
            const res = await apiJson('/api/categories');
            if (res.success) { this.items = res.data; mikoCache.set('categories', res.data, 1800000); }
            this.pageLoading = false;
        },
        async save() {
            this.loading = true;
            const url = this.editing ? '/api/categories/' + this.form.id : '/api/categories';
            const method = this.editing ? 'PUT' : 'POST';
            const res = await apiFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
            const d = await res.json();
            if (d.success) { mikoCache.remove('categories'); this.showForm = false; this.load(); }
            this.loading = false;
        },
        async remove(id) { if (!confirm('Delete?')) return; await apiDelete('/api/categories/'+id); mikoCache.remove('categories'); this.load(); },
    },
    created() { this.load(); },
};

// ── Customers ──
const Customers = {
    template: `
    <div><div class="flex justify-between items-center mb-4"><h2 class="text-2xl font-bold">Customers</h2><button @click="openForm()" class="bg-indigo-800 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i> Add</button></div>
      <div v-if="pageLoading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else class="bg-white rounded-lg shadow">
        <div class="p-4 border-b"><input type="text" v-model="search" @input="load" placeholder="Search..." class="w-full px-3 py-2 border rounded-lg"></div>
        <table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b bg-gray-50"><th class="p-3">Name</th><th class="p-3">Phone</th><th class="p-3">Sales</th><th class="p-3">Spent</th><th></th></tr></thead><tbody><tr v-for="c in items" :key="c.id" class="border-b hover:bg-gray-50"><td class="p-3 font-medium">{{ c.name }}</td><td class="p-3">{{ c.phone || '-' }}</td><td class="p-3">{{ c.sale_count || 0 }}</td><td class="p-3">{{ fmt(c.total_spent) }}</td><td class="p-3"><button @click="openForm(c)" class="text-indigo-600 hover:underline">Edit</button></td></tr></tbody></table></div>
      <div v-if="showForm" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="showForm=false"><div class="bg-white rounded-lg p-6 w-full max-w-md"><h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit' : 'Add' }} Customer</h3><form @submit.prevent="save"><div class="mb-4"><input type="text" v-model="form.name" class="w-full px-3 py-2 border rounded-lg" required></div><div class="grid grid-cols-2 gap-4 mb-4"><input type="text" v-model="form.phone" placeholder="Phone" class="px-3 py-2 border rounded-lg"><input type="email" v-model="form.email" placeholder="Email" class="px-3 py-2 border rounded-lg"></div><div class="flex gap-3 justify-end"><button type="button" @click="showForm=false" class="bg-gray-200 px-4 py-2 rounded-lg">Cancel</button><button type="submit" class="bg-indigo-800 text-white px-4 py-2 rounded-lg">Save</button></div></form></div></div>
    </div>`,
    data: () => ({ items: [], search: '', showForm: false, editing: false, form: { name: '', phone: '', email: '' }, loading: false, pageLoading: true }),
    methods: {
        fmt: fmtMoney,
        openForm(c) { this.editing = !!c; this.form = c ? { id: c.id, name: c.name, phone: c.phone || '', email: c.email || '' } : { name: '', phone: '', email: '' }; this.showForm = true; },
        async load() {
            const p = new URLSearchParams(); if (this.search) p.set('search', this.search);
            if (!this.search) { const cached = mikoCache.get('customers'); if (cached) { this.items = cached; this.pageLoading = false; } }
            const res = await apiJson('/api/customers?' + p);
            if (res.success) { this.items = res.data; if (!this.search) mikoCache.set('customers', res.data, 600000); }
            this.pageLoading = false;
        },
        async save() {
            const url = this.editing ? '/api/customers/' + this.form.id : '/api/customers';
            const method = this.editing ? 'PUT' : 'POST';
            const res = await apiFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.form) });
            const d = await res.json();
            if (d.success) { mikoCache.remove('customers'); this.showForm = false; this.load(); }
        },
    },
    created() { this.load(); },
};

// ── Sales list ──
const Sales = {
    template: `
    <div><h2 class="text-2xl font-bold mb-4">Sales History</h2>
      <div v-if="loading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex gap-2"><input type="text" v-model="search" @input="load" placeholder="Search..." class="flex-1 px-3 py-2 border rounded-lg"><input type="date" v-model="from" @change="load" class="px-3 py-2 border rounded-lg"><input type="date" v-model="to" @change="load" class="px-3 py-2 border rounded-lg"></div>
        <table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b bg-gray-50"><th class="p-3">Invoice</th><th class="p-3">Date</th><th class="p-3">Customer</th><th class="p-3">Payment</th><th class="p-3">Total</th><th class="p-3">Status</th><th></th></tr></thead><tbody><tr v-for="s in items" :key="s.id" class="border-b hover:bg-gray-50"><td class="p-3 font-medium">{{ s.invoice_no }}</td><td class="p-3">{{ s.created_at }}</td><td class="p-3">{{ s.customer_name || 'Walk-in' }}</td><td class="p-3 capitalize">{{ s.payment_method }}</td><td class="p-3 font-bold">{{ fmt(s.total) }}</td><td class="p-3"><span :class="s.status==='completed'?'bg-green-100 text-green-800':'bg-red-100 text-red-800'" class="px-2 py-1 rounded-full text-xs capitalize">{{ s.status }}</span></td><td><router-link :to="'/sales/'+s.id" class="text-indigo-600 hover:underline">View</router-link></td></tr></tbody></table></div>
    </div>`,
    data: () => ({ items: [], search: '', from: '', to: '', loading: true }),
    methods: {
        fmt: fmtMoney,
        async load() {
            this.loading = true;
            const p = new URLSearchParams(); if (this.search) p.set('search', this.search); if (this.from) p.set('from', this.from); if (this.to) p.set('to', this.to);
            const res = await apiJson('/api/sales?' + p);
            if (res.success) this.items = res.data;
            this.loading = false;
        },
    },
    created() { this.load(); },
};

// ── Sale Detail ──
const SaleDetail = {
    template: `
    <div>
      <router-link to="/sales" class="text-indigo-600 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i> Back</router-link>
      <div v-if="loading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else-if="sale" class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
          <div class="grid grid-cols-2 gap-4 text-sm mb-6">
            <div><p class="text-gray-500">Invoice</p><p class="font-medium">{{ sale.invoice_no }}</p></div>
            <div><p class="text-gray-500">Date</p><p class="font-medium">{{ sale.created_at }}</p></div>
            <div><p class="text-gray-500">Cashier</p><p class="font-medium">{{ sale.cashier_name || '-' }}</p></div>
            <div><p class="text-gray-500">Customer</p><p class="font-medium">{{ sale.customer_name || 'Walk-in' }}</p></div>
          </div>
          <table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b"><th>Product</th><th>Price</th><th>Qty</th><th class="text-right">Subtotal</th></tr></thead><tbody><tr v-for="item in sale.items" :key="item.id" class="border-b"><td class="py-2">{{ item.product_name }}</td><td>{{ fmt(item.price) }}</td><td>{{ item.quantity }}</td><td class="text-right">{{ fmt(item.subtotal) }}</td></tr></tbody></table>
          <div class="mt-4 space-y-1 text-sm border-t pt-3">
            <div class="flex justify-between"><span>Subtotal</span><span>{{ fmt(sale.subtotal) }}</span></div>
            <div class="flex justify-between"><span>Discount</span><span>{{ fmt(sale.discount) }}</span></div>
            <div class="flex justify-between"><span>Tax</span><span>{{ fmt(sale.tax) }}</span></div>
            <div class="flex justify-between font-bold text-lg"><span>Total</span><span>{{ fmt(sale.total) }}</span></div>
          </div>
        </div>
      </div>
    </div>`,
    data: () => ({ sale: null, loading: true }),
    methods: { fmt: fmtMoney },
    async created() { const res = await apiJson('/api/sales/' + this.$route.params.id); if (res.success) this.sale = res.data; this.loading = false; },
};

// ── Reports ──
const Reports = {
    template: `
    <div><h2 class="text-2xl font-bold mb-4">Reports</h2>
      <div v-if="loading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6"><h3 class="font-semibold mb-4">Sales Summary</h3><div class="grid grid-cols-2 gap-4"><div class="bg-indigo-50 p-4 rounded-lg text-center"><p class="text-sm text-gray-500">Today</p><p class="text-2xl font-bold text-indigo-800">{{ summary.count || 0 }}</p></div><div class="bg-green-50 p-4 rounded-lg text-center"><p class="text-sm text-gray-500">Revenue</p><p class="text-2xl font-bold text-green-800">{{ fmt(summary.total) }}</p></div></div></div>
        <div class="bg-white rounded-lg shadow p-6"><h3 class="font-semibold mb-4">Low Stock</h3><div v-for="p in lowStock" :key="p.id" class="flex justify-between py-1 border-b text-sm"><span class="font-medium">{{ p.name }}</span><span class="text-red-600 font-bold">{{ p.stock }} / {{ p.min_stock }}</span></div><p v-if="!lowStock.length" class="text-gray-500 text-sm">Well-stocked</p></div>
      </div>
    </div>`,
    data: () => ({ summary: { count: 0, total: 0 }, lowStock: [], loading: true }),
    methods: { fmt: fmtMoney },
    async created() {
        const cached = mikoCache.get('reportStats'); if (cached) { this.summary = cached.today_sales || {}; this.lowStock = cached.low_stock || []; this.loading = false; }
        const res = await apiJson('/api/dashboard/stats');
        if (res.success) { const s = res.data; this.summary = s.today_sales || {}; this.lowStock = s.low_stock || []; mikoCache.set('reportStats', s, 120000); }
        this.loading = false;
    },
};

// ── Stores ──
const Stores = {
    template: `
    <div><div class="flex justify-between items-center mb-4"><h2 class="text-2xl font-bold">Stores</h2><button @click="openForm()" class="bg-indigo-800 text-white px-4 py-2 rounded-lg text-sm"><i class="fas fa-plus mr-1"></i> Add</button></div>
      <div v-if="pageLoading" class="flex justify-center py-20"><i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i></div>
      <div v-else class="bg-white rounded-lg shadow"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b bg-gray-50"><th class="p-3">Name</th><th class="p-3">Code</th><th class="p-3">Status</th><th></th></tr></thead><tbody><tr v-for="s in items" :key="s.id" class="border-b hover:bg-gray-50"><td class="p-3 font-medium">{{ s.name }}</td><td class="p-3">{{ s.code || '-' }}</td><td class="p-3"><span :class="s.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100'" class="px-2 py-1 rounded-full text-xs">{{ s.is_active ? 'Active' : 'Inactive' }}</span></td><td class="p-3"><button @click="switchTo(s)" class="text-indigo-600 hover:underline">Switch</button></td></tr></tbody></table></div>
    </div>`,
    data: () => ({ items: [], pageLoading: true }),
    methods: {
        async load() {
            const res = await apiJson('/api/stores');
            if (res.success) this.items = res.data; this.pageLoading = false;
        },
        async switchTo(s) {
            await apiPost('/api/stores/switch', { store_id: s.id });
            localStorage.setItem('store_id', s.id);
            window.location.reload();
        },
    },
    created() { this.load(); },
};

// ── 404 ──
const NotFound = { template: '<div class="text-center py-20"><h1 class="text-6xl font-bold text-indigo-800">404</h1><p class="text-gray-600 mt-2">Page not found</p><router-link to="/" class="mt-4 inline-block bg-indigo-800 text-white px-4 py-2 rounded-lg">Go Home</router-link></div>' };

// ── Router ──
const routes = [
    { path: '/login', component: Login },
    { path: '/register', component: Register },
    { path: '/app', redirect: '/' },
    { path: '/', component: Dashboard, beforeEnter: requireAuth },
    { path: '/pos', component: Pos, beforeEnter: requireAuth },
    { path: '/products', component: Products, beforeEnter: requireAuth },
    { path: '/products/create', component: ProductForm, beforeEnter: requireAuth },
    { path: '/products/:id/edit', component: ProductForm, beforeEnter: requireAuth },
    { path: '/categories', component: Categories, beforeEnter: requireAuth },
    { path: '/customers', component: Customers, beforeEnter: requireAuth },
    { path: '/sales', component: Sales, beforeEnter: requireAuth },
    { path: '/sales/:id', component: SaleDetail, beforeEnter: requireAuth },
    { path: '/reports', component: Reports, beforeEnter: requireAuth },
    { path: '/stores', component: Stores, beforeEnter: requireAuth },
    { path: '/:pathMatch(.*)*', component: NotFound },
];

const router = VueRouter.createRouter({
    history: VueRouter.createWebHistory(),
    routes,
});

// Global auth guard: redirect to /login if not authenticated
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    const publicPaths = ['/login', '/register', '/app'];
    if (!token && !publicPaths.includes(to.path)) {
        next('/login');
    } else {
        next();
    }
});

// ── App ──
const app = createApp({
    data: () => ({
        sidebarOpen: true,
        authenticated: !!localStorage.getItem('token'),
        storeName: localStorage.getItem('store_name') || '',
        storeId: localStorage.getItem('store_id') || null,
        stores: [],
    }),
    methods: {
        logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('store_id');
            this.authenticated = false;
            this.storeId = null;
            this.$router.push('/login');
        },
        async selectStore(s) {
            const res = await apiPost('/api/stores/switch', { store_id: s.id });
            const d = await res.json();
            if (d.success) {
                localStorage.setItem('store_id', s.id);
                localStorage.setItem('store_name', s.name);
                this.storeId = s.id;
                this.storeName = s.name;
            }
        },
        async fetchStores() {
            const d = await apiJson('/api/stores/mine');
            if (d.success) this.stores = d.data;
        },
    },
    async mounted() {
        if (this.authenticated) await this.fetchStores();
    },
});

app.use(router);
app.mount('#app');
