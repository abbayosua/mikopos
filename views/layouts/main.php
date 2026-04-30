<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'MIKO Pos') ?> — MIKO Pos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div x-data="{ sidebarOpen: true, authenticated: !!localStorage.getItem('token') }" x-init="() => { if (!localStorage.getItem('token') && window.location.pathname !== '/login' && window.location.pathname !== '/register') window.location.href = '/login'; }" class="flex h-screen">
        <!-- Sidebar -->
        <aside x-show="authenticated" :class="sidebarOpen ? 'w-64' : 'w-16'" class="bg-indigo-800 text-white transition-all duration-300 flex-shrink-0">
            <div class="p-4 border-b border-indigo-700">
                <div class="flex items-center justify-between">
                    <h1 class="font-bold text-xl" x-show="sidebarOpen">MIKO Pos</h1>
                    <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:text-indigo-200">
                        <i :class="sidebarOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
                    </button>
                </div>
                <div x-show="sidebarOpen" x-data="storeIndicator()" x-init="load()" class="mt-2 text-xs text-indigo-200 truncate">
                    <span x-text="storeName || 'Loading...'"></span>
                </div>
            </div>
            <nav class="p-2 space-y-1">
                <a href="/" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/') && ($_SERVER['REQUEST_URI'] ?? '') === '/' ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-tachometer-alt w-5 text-center"></i>
                    <span x-show="sidebarOpen">Dashboard</span>
                </a>
                <a href="/pos" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/pos') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-cash-register w-5 text-center"></i>
                    <span x-show="sidebarOpen">POS</span>
                </a>
                <a href="/products" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/products') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-box w-5 text-center"></i>
                    <span x-show="sidebarOpen">Products</span>
                </a>
                <a href="/categories" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/categories') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-tags w-5 text-center"></i>
                    <span x-show="sidebarOpen">Categories</span>
                </a>
                <a href="/customers" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/customers') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span x-show="sidebarOpen">Customers</span>
                </a>
                <a href="/sales" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/sales') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-receipt w-5 text-center"></i>
                    <span x-show="sidebarOpen">Sales</span>
                </a>
                <a href="/reports" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/reports') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-chart-bar w-5 text-center"></i>
                    <span x-show="sidebarOpen">Reports</span>
                </a>
                <hr class="border-indigo-700 my-2">
                <a href="/stores" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-indigo-700 <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/stores') ? 'bg-indigo-700' : '' ?>">
                    <i class="fas fa-store w-5 text-center"></i>
                    <span x-show="sidebarOpen">Stores</span>
                </a>
                <a href="/logout" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-red-600">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i>
                    <span x-show="sidebarOpen">Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto p-6">
            <?php if (isset($content) && is_callable($content)): ?>
                <?php $content(); ?>
            <?php else: ?>
                <?php
                $viewPath = __DIR__ . '/../' . ($_view ?? '') . '.php';
                if (isset($content) && is_string($content) && file_exists(__DIR__ . '/../views/' . $content . '.php')):
                    require __DIR__ . '/../views/' . $content . '.php';
                endif;
                ?>
            <?php endif; ?>
        </main>
    </div>

    <script src="/assets/js/app.js"></script>
    <script>
    function storeIndicator() {
        return {
            storeName: '',
            async load() {
                const res = await apiFetch('/api/stores/current');
                const data = await res.json();
                if (data.success && data.data) this.storeName = 'Store: ' + data.data.name;
                else this.storeName = '';
            }
        }
    }
    </script>
</body>
</html>
