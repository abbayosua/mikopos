<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<div x-data="pos()" x-init="init()" class="h-full">
    <!-- Loading -->
    <template x-if="pageLoading && !currentStore">
        <div class="flex items-center justify-center h-[calc(100vh-8rem)]">
            <i class="fas fa-spinner fa-pulse text-5xl text-indigo-800"></i>
        </div>
    </template>

    <template x-if="!pageLoading || currentStore">
    <!-- Store Required -->
    <template x-if="!currentStore">
        <div class="flex items-center justify-center h-[calc(100vh-8rem)]">
            <div class="bg-white rounded-lg shadow-lg p-8 text-center max-w-md">
                <i class="fas fa-store text-6xl text-indigo-300 mb-4"></i>
                <h2 class="text-xl font-bold mb-2">Select a Store</h2>
                <p class="text-gray-500 mb-6">You need to select a store before using the POS.</p>
                <div class="space-y-2">
                    <template x-for="store in stores" :key="store.id">
                        <button @click="selectStore(store.id)" class="w-full bg-indigo-800 text-white py-3 rounded-lg hover:bg-indigo-900 font-medium">
                            <i class="fas fa-store mr-2"></i>
                            <span x-text="store.name"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- POS Interface -->
    <template x-if="currentStore">
    <div class="flex gap-4 h-[calc(100vh-8rem)]">
        <!-- Products Panel -->
        <div class="flex-1 flex flex-col">
            <!-- Store Badge -->
            <div class="mb-3 flex items-center gap-2">
                <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                    <i class="fas fa-store text-xs"></i>
                    <span x-text="currentStore.name"></span>
                </span>
                <button @click="pickStore" class="text-xs text-indigo-600 hover:underline">(change)</button>
            </div>

            <!-- Barcode Scanner -->
            <div class="mb-2">
                <div class="flex gap-2">
                    <div class="flex-1 flex gap-2 items-center bg-white border-2 border-indigo-300 rounded-lg px-3 py-1 focus-within:border-indigo-600 transition" @click="$refs.barcode.focus()">
                        <i class="fas fa-barcode text-indigo-400 text-lg"></i>
                        <input type="text" x-ref="barcode" x-model="barcode" @keydown.enter.prevent="scanBarcode" @keydown.debounce.1000ms="autoScanBarcode" placeholder="Scan barcode..." class="flex-1 py-2 outline-none text-lg" autofocus>
                    </div>
                    <button @click="openCamera" type="button" class="bg-indigo-100 text-indigo-800 px-3 py-2 rounded-lg hover:bg-indigo-200 text-sm flex items-center gap-1">
                        <i class="fas fa-camera"></i>
                        <span class="hidden sm:inline">Camera</span>
                    </button>
                </div>
                <template x-if="barcodeError">
                    <p class="text-red-600 text-xs mt-1" x-text="barcodeError"></p>
                </template>
            </div>

            <!-- Search & Categories -->
            <div class="mb-3 flex gap-2">
                <input type="text" x-model="search" @input.debounce.500ms="searchProducts" placeholder="Search product by name or SKU..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <select x-model="categoryFilter" @change="searchProducts" class="px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All Categories</option>
                    <template x-for="cat in categories" :key="cat.id">
                        <option :value="cat.id" x-text="cat.name"></option>
                    </template>
                </select>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <template x-for="p in products" :key="p.id">
                        <div @click="addToCart(p)" class="bg-white rounded-lg shadow p-3 cursor-pointer hover:shadow-md transition border-2 hover:border-indigo-300" :class="inCart(p.id) ? 'border-indigo-500' : 'border-transparent'">
                            <div class="bg-gray-100 h-20 rounded flex items-center justify-center text-gray-400 mb-2">
                                <i class="fas fa-box text-3xl"></i>
                            </div>
                            <p class="font-medium text-sm truncate" x-text="p.name"></p>
                            <p class="text-indigo-800 font-bold" x-text="formatMoney(p.price)"></p>
                            <p class="text-xs text-gray-500" x-text="'Stock: ' + p.stock"></p>
                        </div>
                    </template>
                </div>
                <template x-if="!products.length">
                    <p class="text-center text-gray-500 mt-8">No products found</p>
                </template>
            </div>
        </div>

        <!-- Cart Panel -->
        <div class="w-96 bg-white rounded-lg shadow flex flex-col">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-lg">Current Sale</h3>
            </div>

            <!-- Customer Selection -->
            <div class="p-3 border-b">
                <select x-model="customer_id" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                    <option value="">Walk-in Customer</option>
                    <template x-for="c in customers" :key="c.id">
                        <option :value="c.id" x-text="c.name + (c.phone ? ' - ' + c.phone : '')"></option>
                    </template>
                </select>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <template x-for="(item, idx) in cart" :key="idx">
                    <div class="flex items-center gap-2 bg-gray-50 rounded p-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate" x-text="item.name"></p>
                            <p class="text-xs text-gray-500" x-text="formatMoney(item.price) + ' x ' + item.quantity"></p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="updateQty(idx, -1)" class="w-6 h-6 bg-gray-200 rounded-full text-sm hover:bg-gray-300">-</button>
                            <span class="w-8 text-center text-sm font-bold" x-text="item.quantity"></span>
                            <button @click="updateQty(idx, 1)" class="w-6 h-6 bg-gray-200 rounded-full text-sm hover:bg-gray-300">+</button>
                        </div>
                        <p class="text-sm font-bold w-20 text-right" x-text="formatMoney(item.subtotal)"></p>
                        <button @click="removeFromCart(idx)" class="text-red-500 hover:text-red-700 text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </template>
                <template x-if="!cart.length">
                    <div class="text-center text-gray-400 mt-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <p>Cart is empty</p>
                        <p class="text-sm">Click products to add</p>
                    </div>
                </template>
            </div>

            <!-- Summary -->
            <div class="border-t p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span>Subtotal</span>
                    <span x-text="formatMoney(subtotal)"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span>Discount</span>
                    <input type="number" x-model="discount" @input="calcTotals" class="w-24 px-2 py-1 border border-gray-300 rounded text-right text-sm" placeholder="0">
                </div>
                <div class="flex justify-between text-sm">
                    <span>Tax</span>
                    <input type="number" x-model="tax" @input="calcTotals" class="w-24 px-2 py-1 border border-gray-300 rounded text-right text-sm" placeholder="0">
                </div>
                <div class="flex justify-between font-bold text-lg border-t pt-2">
                    <span>Total</span>
                    <span x-text="formatMoney(total)"></span>
                </div>

                <div class="flex gap-2">
                    <select x-model="payment_method" class="flex-1 px-2 py-2 border border-gray-300 rounded text-sm">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="transfer">Transfer</option>
                    </select>
                    <input type="number" x-model="amount_paid" @input="calcTotals" placeholder="Amount paid" class="w-32 px-2 py-2 border border-gray-300 rounded text-right text-sm">
                </div>

                <div class="flex justify-between text-sm" x-show="change > 0">
                    <span>Change</span>
                    <span class="text-green-600 font-bold" x-text="formatMoney(change)"></span>
                </div>

                <template x-if="error">
                    <div class="text-red-600 text-sm" x-text="error"></div>
                </template>

                <button @click="checkout" :disabled="loading || !cart.length" class="w-full bg-indigo-800 text-white py-3 rounded-lg font-bold hover:bg-indigo-900 disabled:opacity-50 disabled:cursor-not-allowed text-lg" x-text="loading ? 'Processing...' : 'Charge ' + formatMoney(total)"></button>
            </div>
        </div>
    </div>
    </template>

    <!-- Camera Scanner Modal -->
    <div x-show="showCamera" class="fixed inset-0 bg-gray-900 bg-opacity-80 z-50 flex items-center justify-center" @click.self="closeCamera">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 overflow-hidden">
            <div class="p-3 border-b flex items-center justify-between bg-indigo-800 text-white">
                <h3 class="font-semibold"><i class="fas fa-camera mr-2"></i>Scan Barcode</h3>
                <button @click="closeCamera" class="text-white hover:text-gray-300"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-0 relative">
                <div id="camera-scanner" class="w-full" style="min-height: 300px;"></div>
                <template x-if="cameraError">
                    <div class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-90 p-6">
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-3"></i>
                            <p class="text-red-600 text-sm font-medium" x-text="cameraError"></p>
                            <p class="text-gray-500 text-xs mt-2">Make sure camera access is allowed in your browser settings.</p>
                            <button @click="closeCamera" class="mt-4 bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">Close</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="p-3 text-center text-sm text-gray-500 border-t flex items-center justify-center gap-2">
                <i class="fas fa-spinner fa-pulse" x-show="!cameraError"></i>
                <span x-text="cameraError ? 'Camera unavailable' : 'Point your camera at a barcode to scan automatically'"></span>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div x-show="showReceipt" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center" @click.self="showReceipt = false">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
            <div class="text-center mb-4">
                <h3 class="font-bold text-lg">MIKO Pos</h3>
                <p class="text-sm text-gray-500">Sale Completed</p>
            </div>
            <div class="border-t border-b py-3 mb-3 space-y-1 text-sm">
                <p><strong>Invoice:</strong> <span x-text="receipt.invoice_no"></span></p>
                <p><strong>Date:</strong> <span x-text="receipt.created_at"></span></p>
                <p><strong>Cashier:</strong> <span x-text="receipt.cashier_name"></span></p>
                <template x-if="receipt.customer_name">
                    <p><strong>Customer:</strong> <span x-text="receipt.customer_name"></span></p>
                </template>
            </div>
            <div class="mb-3 space-y-1 text-sm">
                <template x-for="item in receipt.items" :key="item.id">
                    <div class="flex justify-between">
                        <span x-text="item.product_name + ' x' + item.quantity"></span>
                        <span x-text="formatMoney(item.subtotal)"></span>
                    </div>
                </template>
            </div>
            <div class="border-t pt-2 font-bold flex justify-between">
                <span>Total</span>
                <span x-text="formatMoney(receipt.total)"></span>
            </div>
            <div class="text-sm flex justify-between">
                <span>Paid</span>
                <span x-text="formatMoney(receipt.amount_paid)"></span>
            </div>
            <div class="text-sm flex justify-between" x-show="receipt.change_amount > 0">
                <span>Change</span>
                <span x-text="formatMoney(receipt.change_amount)"></span>
            </div>
            <div class="mt-4 flex gap-2">
                <button @click="showReceipt = false; resetCart()" class="flex-1 bg-indigo-800 text-white py-2 rounded-lg hover:bg-indigo-900">New Sale</button>
                <button @click="window.print()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300"><i class="fas fa-print"></i></button>
            </div>
        </div>
    </div>
    </template>
</div>

<script>
function pos() {
    return {
        pageLoading: true,
        products: [],
        categories: [],
        customers: [],
        stores: [],
        currentStore: null,
        cart: [],
        search: '',
        barcode: '',
        barcodeError: '',
        showCamera: false,
        scanner: null,
        cameraError: '',
        categoryFilter: '',
        customer_id: '',
        discount: 0,
        tax: 0,
        subtotal: 0,
        total: 0,
        amount_paid: 0,
        change: 0,
        payment_method: 'cash',
        loading: false,
        error: '',
        showReceipt: false,
        receipt: {},

        async init() {
            const [meRes, initRes] = await Promise.all([
                apiFetch('/api/auth/me'),
                apiFetch('/api/pos/init'),
            ]);
            const me = await meRes.json();
            const init = await initRes.json();

            if (me.success) {
                this.stores = me.data.stores || [];
                this.currentStore = me.data.store;
            }
            if (init.success) {
                const d = init.data;
                this.products = d.products || [];
                this.categories = d.categories || [];
                this.customers = d.customers || [];
                cache.set('categories', this.categories, 30 * 60 * 1000);
                cache.set('customers', this.customers, 10 * 60 * 1000);
            }
            this.pageLoading = false;
        },

        async selectStore(storeId) {
            const res = await apiFetch('/api/stores/switch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ store_id: storeId })
            });
            const data = await res.json();
            if (data.success) {
                this.currentStore = data.data.store;
                localStorage.setItem('store_id', storeId);
                cache.remove('products');
                cache.remove('categories');
                cache.remove('customers');
                const initRes = await apiFetch('/api/pos/init');
                const init = await initRes.json();
                if (init.success) {
                    this.products = init.data.products || [];
                    this.categories = init.data.categories || [];
                    this.customers = init.data.customers || [];
                }
            }
        },

        pickStore() {
            this.currentStore = null;
        },

        async openCamera() {
            if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                this.cameraError = 'Camera requires HTTPS or localhost. Use https://mikopos.test or localhost.';
                this.showCamera = true;
                return;
            }
            this.cameraError = '';
            this.showCamera = true;
            await new Promise(r => setTimeout(r, 300));
            this.startScanner();
        },

        startScanner() {
            if (this.scanner) { try { this.scanner.clear(); } catch(e) {} this.scanner = null; }
            const el = document.getElementById('camera-scanner');
            if (!el) { this.cameraError = 'Scanner element not found'; return; }
            el.innerHTML = '';
            try {
                this.scanner = new Html5Qrcode("camera-scanner");
                this.scanner.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 150 } },
                    (decodedText) => this.onScanSuccess(decodedText),
                    () => {}
                ).catch((err) => {
                    const msg = String(err);
                    if (msg.includes('NotAllowedError') || msg.includes('Permission')) {
                        this.cameraError = 'Camera permission denied. Allow camera access in your browser settings.';
                    } else if (msg.includes('NotFoundError')) {
                        this.cameraError = 'No camera found on this device.';
                    } else if (msg.includes('NotReadableError')) {
                        this.cameraError = 'Camera is busy. Close other apps using the camera.';
                    } else {
                        this.cameraError = 'Camera error: ' + msg;
                    }
                });
            } catch(e) {
                this.cameraError = 'Failed to start camera: ' + e.message;
            }
        },

        async onScanSuccess(barcode) {
            await this.closeCamera();
            this.barcode = barcode;
            this.scanBarcode();
        },

        async closeCamera() {
            this.showCamera = false;
            if (this.scanner) {
                try { await this.scanner.stop(); } catch(e) {}
                this.scanner = null;
            }
        },

        async scanBarcode() {
            if (!this.barcode.trim()) return;
            const res = await apiFetch('/api/products/search?q=' + encodeURIComponent(this.barcode.trim()));
            const data = await res.json();
            if (data.success && data.data.length === 1) {
                this.addToCart(data.data[0]);
                this.barcode = '';
                this.barcodeError = '';
                this.$refs.barcode.focus();
            } else {
                this.barcodeError = 'Product not found for barcode: ' + this.barcode;
                this.barcode = '';
                setTimeout(() => this.barcodeError = '', 3000);
            }
        },

        autoScanBarcode() {
            if (this.barcode.length >= 8) this.scanBarcode();
        },

        async loadCategories() {
            let cats = cache.get('categories');
            if (cats) { this.categories = cats; return; }
            const res = await apiFetch('/api/categories');
            const data = await res.json();
            if (data.success) {
                this.categories = data.data;
                cache.set('categories', this.categories, 30 * 60 * 1000);
            }
        },

        async loadCustomers() {
            let custs = cache.get('customers');
            if (custs) { this.customers = custs; return; }
            const res = await apiFetch('/api/customers');
            const data = await res.json();
            if (data.success) {
                this.customers = data.data;
                cache.set('customers', this.customers, 10 * 60 * 1000);
            }
        },

        async searchProducts() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.categoryFilter) params.set('category_id', this.categoryFilter);
            const res = await apiFetch('/api/products?' + params);
            const data = await res.json();
            if (data.success) {
                this.products = data.data;
                cache.set('products', data.data, 5 * 60 * 1000);
            }
        },

        addToCart(product) {
            const existing = this.cart.find(i => i.product_id === product.id);
            if (existing) {
                if (existing.quantity < product.stock) {
                    existing.quantity++;
                    existing.subtotal = existing.price * existing.quantity;
                }
            } else {
                this.cart.push({
                    product_id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    quantity: 1,
                    subtotal: parseFloat(product.price)
                });
            }
            this.calcTotals();
        },

        updateQty(idx, delta) {
            const item = this.cart[idx];
            item.quantity = Math.max(1, item.quantity + delta);
            item.subtotal = item.price * item.quantity;
            if (item.quantity < 1) this.cart.splice(idx, 1);
            this.calcTotals();
        },

        removeFromCart(idx) {
            this.cart.splice(idx, 1);
            this.calcTotals();
        },

        inCart(id) {
            return this.cart.some(i => i.product_id === id);
        },

        calcTotals() {
            this.subtotal = this.cart.reduce((sum, i) => sum + i.subtotal, 0);
            this.total = this.subtotal - parseFloat(this.discount || 0) + parseFloat(this.tax || 0);
            this.change = Math.max(0, parseFloat(this.amount_paid || 0) - this.total);
        },

        async checkout() {
            if (!this.cart.length) return;
            if (parseFloat(this.amount_paid || 0) < this.total) {
                this.error = 'Amount paid is less than total';
                return;
            }
            this.loading = true;
            this.error = '';
            try {
                const res = await apiFetch('/api/sales', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: this.cart.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price })),
                        customer_id: this.customer_id || null,
                        discount: this.discount,
                        tax: this.tax,
                        payment_method: this.payment_method,
                        amount_paid: this.amount_paid
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.receipt = data.data;
                    this.showReceipt = true;
                    cache.removePattern('products');
                } else {
                    this.error = data.message;
                }
            } catch (e) {
                this.error = 'Connection error';
            } finally {
                this.loading = false;
            }
        },

        resetCart() {
            this.cart = [];
            this.customer_id = '';
            this.discount = 0;
            this.tax = 0;
            this.amount_paid = 0;
            this.change = 0;
            this.subtotal = 0;
            this.total = 0;
            this.error = '';
            cache.removePattern('products');
            this.searchProducts();
        },

        formatMoney(n) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0);
        }
    }
}
</script>
