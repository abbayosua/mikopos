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
        <!-- Auth check: not logged in and not on public page -->
        <div v-if="!authenticated && $route.path !== '/login' && $route.path !== '/register'" class="min-h-screen flex items-center justify-center">
            <div class="text-center">
                <i class="fas fa-spinner fa-pulse text-4xl text-indigo-800 mb-4"></i>
                <p class="text-gray-500 text-sm">Redirecting to login...</p>
            </div>
        </div>

        <!-- Store selector: logged in but no store selected -->
        <div v-else-if="authenticated && !storeId && $route.path !== '/login' && $route.path !== '/register'" class="min-h-screen flex items-center justify-center bg-gray-100">
            <div class="bg-white rounded-lg shadow-lg p-8 text-center max-w-md">
                <i class="fas fa-store text-6xl text-indigo-300 mb-4"></i>
                <h2 class="text-xl font-bold mb-2">Select a Store</h2>
                <p class="text-gray-500 mb-6">Choose a store to start working.</p>
                <div v-if="storesLoading" class="py-4"><i class="fas fa-spinner fa-pulse text-xl text-indigo-800 mr-2"></i>Loading stores...</div>
                <div v-else-if="loadError" class="py-4 text-red-600 text-sm">{{ loadError }}</div>
                <div v-else-if="stores.length === 0" class="py-4 text-gray-500">No stores found. <a href="/logout" class="text-indigo-600">Logout</a></div>
                <div v-else class="space-y-2">
                    <button v-for="s in stores" :key="s.id" @click="selectStore(s)" class="w-full bg-indigo-800 text-white py-3 rounded-lg hover:bg-indigo-900 font-medium">
                        <i class="fas fa-store mr-2"></i>{{ s.name }}
                    </button>
                </div>
                <p class="mt-6 text-xs text-gray-400"><a href="#" @click.prevent="logout" class="text-indigo-600 hover:underline">Logout</a></p>
            </div>
        </div>

        <!-- Main app layout -->
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
                    <hr class="border-indigo-700 my-2">
                    <div v-if="!syncOnline" class="flex items-center gap-3 px-3 py-2 text-yellow-300 text-sm">
                        <i class="fas fa-wifi-slash w-5 text-center"></i>
                        <span v-show="sidebarOpen">Offline</span>
                    </div>
                    <div v-if="syncPending > 0" class="flex items-center gap-3 px-3 py-2 text-indigo-300 text-sm">
                        <i class="fas fa-cloud-upload-alt w-5 text-center"></i>
                        <span v-show="sidebarOpen">{{ syncPending }} pending</span>
                    </div>
                    <a href="#" @click.prevent="logout" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-red-600">
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
    <script src="/assets/js/lib/db.js"></script>
    <script src="/assets/js/lib/sync.js"></script>
    <script src="/assets/js/vue-app.js"></script>
</body>
</html>
