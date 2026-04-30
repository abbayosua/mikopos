<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MIKO Pos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://unpkg.com/vue-router@4"></script>
</head>
<body class="bg-gray-100">
    <div id="app">
        <div v-if="!authenticated && $route.path !== '/login' && $route.path !== '/register'" class="min-h-screen flex items-center justify-center">
            <i class="fas fa-spinner fa-pulse text-4xl text-indigo-800"></i>
        </div>
        <div v-else class="flex h-screen">
            <aside v-show="authenticated" :class="sidebarOpen ? 'w-64' : 'w-16'" class="bg-indigo-800 text-white transition-all duration-300 flex-shrink-0">
                <div class="p-4 border-b border-indigo-700">
                    <div class="flex items-center justify-between">
                        <h1 class="font-bold text-xl" v-show="sidebarOpen">MIKO Pos</h1>
                        <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:text-indigo-200">
                            <i :class="sidebarOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
                        </button>
                    </div>
                    <div v-show="sidebarOpen" class="mt-2 text-xs text-indigo-200 truncate">
                        <span v-if="storeName">Store: {{ storeName }}</span>
                    </div>
                </div>
                <nav class="p-2 space-y-1">
                    <router-link to="/" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-tachometer-alt w-5 text-center"></i>
                        <span v-show="sidebarOpen">Dashboard</span>
                    </router-link>
                    <router-link to="/pos" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/pos' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-cash-register w-5 text-center"></i>
                        <span v-show="sidebarOpen">POS</span>
                    </router-link>
                    <router-link to="/products" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path.startsWith('/products') ? 'bg-indigo-700' : ''">
                        <i class="fas fa-box w-5 text-center"></i>
                        <span v-show="sidebarOpen">Products</span>
                    </router-link>
                    <router-link to="/categories" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/categories' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-tags w-5 text-center"></i>
                        <span v-show="sidebarOpen">Categories</span>
                    </router-link>
                    <router-link to="/customers" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/customers' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-users w-5 text-center"></i>
                        <span v-show="sidebarOpen">Customers</span>
                    </router-link>
                    <router-link to="/sales" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/sales' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-receipt w-5 text-center"></i>
                        <span v-show="sidebarOpen">Sales</span>
                    </router-link>
                    <router-link to="/reports" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/reports' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-chart-bar w-5 text-center"></i>
                        <span v-show="sidebarOpen">Reports</span>
                    </router-link>
                    <hr class="border-indigo-700 my-2">
                    <router-link to="/stores" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700" :class="$route.path === '/stores' ? 'bg-indigo-700' : ''">
                        <i class="fas fa-store w-5 text-center"></i>
                        <span v-show="sidebarOpen">Stores</span>
                    </router-link>
                    <a href="/logout" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-red-600">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                        <span v-show="sidebarOpen">Logout</span>
                    </a>
                </nav>
            </aside>
            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                <router-view></router-view>
            </main>
        </div>
    </div>

    <script src="/assets/js/lib/api.js"></script>
    <script src="/assets/js/lib/cache.js"></script>
    <script src="/assets/js/vue-app.js"></script>
</body>
</html>
