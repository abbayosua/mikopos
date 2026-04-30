// ── Sync Engine ──
const sync = {
    pendingCount: 0,
    online: navigator.onLine,
    _listeners: [],
    _interval: null,

    onChange(fn) { this._listeners.push(fn); this._notify(); },
    _notify() { for (const fn of this._listeners) fn(this); },

    // ── Start background sync ──
    async start() {
        this.updateOnlineStatus();
        window.addEventListener('online', () => { this.updateOnlineStatus(); this.syncNow(); });
        window.addEventListener('offline', () => this.updateOnlineStatus());
        await this.updatePendingCount();
        if (this._interval) clearInterval(this._interval);
        this._interval = setInterval(() => this.syncNow(), 30000);
        this.syncNow();
    },

    async stop() {
        if (this._interval) { clearInterval(this._interval); this._interval = null; }
    },

    updateOnlineStatus() {
        this.online = navigator.onLine;
        this._notify();
    },

    // ── Sync now: push unsynced sales, then pull updates ──
    async syncNow() {
        if (!this.online) return;
        await this.pushUnsyncedSales();
        await this.pullUpdates();
    },

    // ── Push only unsynced sales ──
    async pushUnsyncedSales() {
        if (!this.online) return;
        const all = await db.getAll('sales_queue');
        const unsynced = all.filter(s => !s.synced);
        if (!unsynced.length) return;

        for (const sale of unsynced) {
            try {
                const res = await apiPost('/api/sync/sales', {
                    items: sale.items, customer_id: sale.customer_id,
                    discount: sale.discount, tax: sale.tax,
                    payment_method: sale.payment_method, amount_paid: sale.amount_paid,
                });
                const data = await res.json();
                if (data.success) {
                    await db.put('sales_queue', { ...sale, synced: true, invoice_no: data.data.invoice_no });
                }
            } catch(e) { /* will retry next interval */ }
        }
        await this.updatePendingCount();
    },

    // ── Save sale offline-first ──
    async saveSale(saleData) {
        const localId = 'sale_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        const sale = {
            localId, synced: false,
            invoice_no: 'PENDING',
            created_at: new Date().toISOString(),
            ...saleData,
        };
        await db.put('sales_queue', sale);
        await this.updatePendingCount();
        // Try push immediately if online
        if (this.online) await this.pushUnsyncedSales();
        return sale;
    },

    // ── Pull incremental updates from server ──
    async pullUpdates() {
        if (!this.online) return;
        try {
            const meta = await db.get('sync_meta', 'lastSync');
            const since = meta ? '?since=' + encodeURIComponent(meta.ts) : '';
            const res = await apiFetch('/api/sync/products' + since);
            const data = await res.json();
            if (!data.success) return;
            if (data.data.products?.length) await db.putMany('products', data.data.products);
            await db.put('sync_meta', { key: 'lastSync', ts: data.data.synced_at || new Date().toISOString() });
        } catch(e) { /* will retry */ }
    },

    // ── Seed all data from sync/init (called after store select) ──
    async seedAll() {
        const res = await apiFetch('/api/sync/init');
        const data = await res.json();
        if (!data.success) throw new Error('Sync init failed');
        const d = data.data;
        if (d.products) await db.putMany('products', d.products);
        if (d.categories) await db.putMany('categories', d.categories);
        if (d.customers) await db.putMany('customers', d.customers);
        if (d.stores) await db.putMany('stores', d.stores);
        await db.put('sync_meta', { key: 'lastSync', ts: d.synced_at || new Date().toISOString() });
        return d;
    },

    async updatePendingCount() {
        const all = await db.getAll('sales_queue');
        this.pendingCount = all.filter(s => !s.synced).length;
        this._notify();
    },

    async getStatus() {
        return {
            online: this.online,
            pending: this.pendingCount,
            lastSync: (await db.get('sync_meta', 'lastSync'))?.ts || null,
            products: await db.count('products'),
            categories: await db.count('categories'),
            customers: await db.count('customers'),
        };
    },
};
