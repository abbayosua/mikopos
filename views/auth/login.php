<div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-indigo-800">MIKO Pos</h1>
            <p class="text-gray-600 mt-2">Sign in to your account</p>
        </div>

        <div x-data="loginForm()">
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                    <input type="email" x-model="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                    <input type="password" x-model="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>

                <template x-if="error">
                    <div class="mb-4 text-red-600 text-sm" x-text="error"></div>
                </template>

                <button type="submit" :disabled="loading" class="w-full bg-indigo-800 text-white py-2 rounded-lg hover:bg-indigo-900 disabled:opacity-50" x-text="loading ? 'Signing in...' : 'Sign In'"></button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-600">
                Don't have an account? <a href="/register" class="text-indigo-800 hover:underline">Register</a>
            </p>
        </div>
    </div>
</div>

<script>
function loginForm() {
    return {
        email: '',
        password: '',
        loading: false,
        error: '',
        async submit() {
            this.loading = true;
            this.error = '';
            try {
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.email, password: this.password })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = '/';
                } else {
                    this.error = data.message || 'Login failed';
                }
            } catch (e) {
                this.error = 'Connection error';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
