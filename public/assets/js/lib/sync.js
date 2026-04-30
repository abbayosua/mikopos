// ── Sync Engine ──
const sync = {
    pendingCount: 0,
    online: navigator.onLine,
    _listeners: [],

    onChange(fn) { this._listeners.push(fn); this._notify(); },

    _notify() { for (const fn of this._listeners) fn(this); },

    // ── Initial sync (seed IndexedDB from server) ──
    async init() {
        this.updateOnlineStatus();
        window.addEventListener('online', () => { this.updateOnlineStatus(); this.pushSales(); this.pullUpdates(); });
        window.addEventListener('offline', () => this.updateOnlineStatus());
        await this.pullUpdates(true);
        await this.updatePendingCount();
    },

    updateOnlineStatus() {
        this.online = navigator.onLine;
        this._notify();
    },

    // ── Pull updates from server ──
    async pullUpdates(isInitial = false) {
        if (!this.online) return;
        try {
            const meta = await db.get('sync_meta', 'lastSync');
            const since = meta ? '?since=' + encodeURIComponent(meta.ts) : '';
            const url = isInitial ? '/api/sync/init' : '/api/sync/products' + since;

            const res = await apiFetch(url);
            const data = await res.json();
            if (!data.success) return;

            const d = data.data;
            if (d.products) await db.putMany('products', d.products);
            if (d.categories) await db.putMany('categories', d.categories);
            if (d.customers) await db.putMany('customers', d.customers);
            if (d.stores) await db.putMany('stores', d.stores);

            await db.put('sync_meta', { key: 'lastSync', ts: new Date().toISOString() });
        } catch(e) { console.warn('Sync pull failed:', e); }
    },

    // ── Push offline sales ──
    async pushSales() {
        if (!this.online) return;
        const queue = await db.getAll('sales_queue');
        if (!queue.length) return;

        const results = [];
        for (const sale of queue) {
            try {
                const res = await apiPost('/api/sync/sales', { items: sale.items, customer_id: sale.customer_id, discount: sale.discount, tax: sale.tax, payment_method: sale.payment_method, amount_paid: sale.amount_paid });
                const data = await res.json();
                if (data.success) {
                    await db.delete('sales_queue', sale.localId);
                    results.push({ localId: sale.localId, status: 'synced', invoice: data.data.invoice_no });
                } else {
                    results.push({ localId: sale.localId, status: 'error', message: data.message });
                }
            } catch(e) {
                results.push({ localId: sale.localId, status: 'error', message: 'Network error' });
            }
        }

        await this.updatePendingCount();
        return results;
    },

    // ── Queue offline sale ──
    async queueSale(saleData) {
        const localId = 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        await db.put('sales_queue', { localId, ...saleData, createdAt: new Date().toISOString() });
        await this.updatePendingCount();
        return localId;
    },

    // ── Update pending count ──
    async updatePendingCount() {
        this.pendingCount = await db.count('sales_queue');
        this._notify();
    },

    // ── Check sync status ──
    async getStatus() {
        return {
            online: this.online,
            pending: this.pendingCount,
            lastSync: (await db.get('sync_meta', 'lastSync'))?.ts || null,
            products: await db.count('products'),
            categories: await db.count('categories'),
            customers: await db.count('customers'),
            stores: await db.count('stores'),
        };
    },
};
