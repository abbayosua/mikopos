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
                    localStorage.setItem('miko_stores', JSON.stringify(data.data.stores || []));
                    window.location.href = '/app';
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
        storesLoading: true,
        loadError: '',
    }),
    methods: {
        logout() {
            localStorage.removeItem('token');
            localStorage.removeItem('store_id');
            localStorage.removeItem('miko_stores');
            this.authenticated = false;
            this.storeId = null;
            this.$router.push('/login');
        },
        async selectStore(s) {
            try {
                const res = await apiPost('/api/stores/switch', { store_id: s.id });
                const d = await res.json();
                if (d.success) {
                    localStorage.setItem('store_id', s.id);
                    localStorage.setItem('store_name', s.name);
                    this.storeId = s.id;
                    this.storeName = s.name;
                    this.$router.push('/');
                }
            } catch(e) {
                this.loadError = 'Failed: ' + e.message;
            }
        },
    },
    created() {
        // Read stores from localStorage (saved on login)
        try {
            const stored = localStorage.getItem('miko_stores');
            if (stored) this.stores = JSON.parse(stored);
        } catch(e) {}
        this.storesLoading = false;
    },
});

app.use(router);
app.mount('#app');
