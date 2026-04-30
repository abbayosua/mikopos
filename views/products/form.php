<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<div x-data="productForm()" x-init="init(<?= $productId ?? 'null' ?>)">
    <div class="mb-4">
        <h2 class="text-2xl font-bold text-gray-800" x-text="editing ? 'Edit Product' : 'Add Product'"></h2>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <form @submit.prevent="save">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Product Name *</label>
                    <input type="text" x-model="form.name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">SKU</label>
                    <input type="text" x-model="form.sku" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Barcode</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="form.barcode" @input.debounce.500ms="manualBarcodeLookup" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button @click="openCamera" type="button" class="bg-indigo-100 text-indigo-800 px-3 py-2 rounded-lg hover:bg-indigo-200 text-sm flex items-center gap-1" title="Scan barcode with camera">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    <template x-if="barcodeStatus">
                        <p class="text-xs mt-1" :class="barcodeStatus.type === 'success' ? 'text-green-600' : barcodeStatus.type === 'error' ? 'text-red-600' : 'text-indigo-600'" x-text="barcodeStatus.text"></p>
                    </template>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Category</label>
                    <select x-model="form.category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No Category</option>
                        <template x-for="cat in categories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Price *</label>
                    <input type="number" step="0.01" x-model="form.price" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Cost</label>
                    <input type="number" step="0.01" x-model="form.cost" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Stock</label>
                    <input type="number" x-model="form.stock" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Min Stock</label>
                    <input type="number" x-model="form.min_stock" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="col-span-2">
                    <label class="block text-gray-700 text-sm font-medium mb-2">Description</label>
                    <textarea x-model="form.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="col-span-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="form.is_active" class="rounded">
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>

            <template x-if="error">
                <div class="mt-4 text-red-600 text-sm" x-text="error"></div>
            </template>

            <div class="mt-6 flex gap-3">
                <button type="submit" :disabled="loading" class="bg-indigo-800 text-white px-6 py-2 rounded-lg hover:bg-indigo-900 disabled:opacity-50" x-text="loading ? 'Saving...' : 'Save Product'"></button>
                <a href="/products" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Camera Scanner Modal -->
    <div x-show="showCamera" class="fixed inset-0 bg-gray-900 bg-opacity-80 z-50 flex items-center justify-center" @click.self="closeCamera">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4 overflow-hidden">
            <div class="p-3 border-b flex items-center justify-between bg-indigo-800 text-white">
                <h3 class="font-semibold"><i class="fas fa-camera mr-2"></i>Scan Barcode</h3>
                <button @click="closeCamera" class="text-white hover:text-gray-300"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-0 relative">
                <div id="product-camera-scanner" class="w-full" style="min-height: 300px;"></div>
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
            <div class="p-3 text-center text-sm text-gray-500 border-t">
                <i class="fas fa-spinner fa-pulse" x-show="!cameraError && !lookupLoading"></i>
                <span x-text="cameraError ? 'Camera unavailable' : 'Point your camera at a barcode'"></span>
            </div>
        </div>
    </div>

    <!-- Lookup loading overlay -->
    <div x-show="lookupLoading" class="fixed inset-0 bg-gray-600 bg-opacity-30 z-40 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg p-6 text-center">
            <i class="fas fa-spinner fa-pulse text-3xl text-indigo-800 mb-3"></i>
            <p class="text-sm text-gray-600">Looking up product...</p>
        </div>
    </div>
</div>

<script>
function productForm() {
    return {
        editing: false,
        productId: null,
        form: { name: '', sku: '', barcode: '', category_id: '', price: '', cost: '', stock: 0, min_stock: 0, description: '', is_active: true },
        categories: [],
        loading: false,
        error: '',
        barcodeStatus: null,
        showCamera: false,
        scanner: null,
        cameraError: '',
        lookupLoading: false,

        async init(id) {
            if (id) {
                this.editing = true;
                this.productId = id;
                const res = await apiFetch('/api/products/' + id);
                const data = await res.json();
                if (data.success) this.form = data.data;
            }
            const catRes = await apiFetch('/api/categories');
            const catData = await catRes.json();
            if (catData.success) this.categories = catData.data;
        },

        async manualBarcodeLookup() {
            const bc = this.form.barcode;
            if (!bc || bc.length < 8 || this.editing) return;
            this.barcodeStatus = { type: 'info', text: 'Looking up...' };
            await this.doLookup(bc);
        },

        async doLookup(barcode) {
            this.lookupLoading = true;
            try {
                const res = await apiFetch('/api/products/lookup?barcode=' + encodeURIComponent(barcode));
                const data = await res.json();
                if (data.success && data.data) {
                    const p = data.data;

                    if (p.source === undefined) {
                        this.barcodeStatus = { type: 'success', text: 'Found in your inventory! Name copied.' };
                        if (!this.editing) this.form.name = p.name || this.form.name;
                        return;
                    }

                    this.form.name = p.name || this.form.name;
                    this.barcodeStatus = { type: 'success', text: 'Found: ' + (p.brand ? p.brand + ' - ' : '') + p.name };

                    if (p.category_name && this.categories.length) {
                        const found = this.categories.find(c =>
                            p.category_name.toLowerCase().includes(c.name.toLowerCase()) ||
                            c.name.toLowerCase().includes(p.category_name.toLowerCase())
                        );
                        if (found) this.form.category_id = found.id;
                    }
                } else {
                    this.barcodeStatus = { type: 'error', text: 'Product not found online' };
                    setTimeout(() => this.barcodeStatus = null, 4000);
                }
            } catch(e) {
                this.barcodeStatus = { type: 'error', text: 'Lookup failed' };
            } finally {
                this.lookupLoading = false;
            }
        },

        async openCamera() {
            if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                this.cameraError = 'Camera requires HTTPS or localhost.';
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
            const el = document.getElementById('product-camera-scanner');
            if (!el) { this.cameraError = 'Scanner element not found'; return; }
            el.innerHTML = '';
            try {
                this.scanner = new Html5Qrcode("product-camera-scanner");
                this.scanner.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 150 } },
                    (decodedText) => this.onScanSuccess(decodedText),
                    () => {}
                ).catch((err) => {
                    const msg = String(err);
                    if (msg.includes('NotAllowedError')) {
                        this.cameraError = 'Camera permission denied. Allow camera access in your browser settings.';
                    } else if (msg.includes('NotFoundError')) {
                        this.cameraError = 'No camera found on this device.';
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
            this.form.barcode = barcode;
            this.barcodeStatus = { type: 'info', text: 'Looking up barcode...' };
            await this.doLookup(barcode);
        },

        async closeCamera() {
            this.showCamera = false;
            if (this.scanner) {
                try { await this.scanner.stop(); } catch(e) {}
                this.scanner = null;
            }
        },

        async save() {
            this.loading = true;
            this.error = '';
            try {
                const url = this.editing ? '/api/products/' + this.productId : '/api/products';
                const method = this.editing ? 'PUT' : 'POST';
                const res = await apiFetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = '/products';
                } else {
                    this.error = data.message || 'Save failed';
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
