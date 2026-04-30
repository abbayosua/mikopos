// ── IndexedDB wrapper ──
const DB_NAME = 'mikopos';
const DB_VERSION = 1;

const dbStores = {
    products: { keyPath: 'id' },
    categories: { keyPath: 'id' },
    customers: { keyPath: 'id' },
    stores: { keyPath: 'id' },
    sales_queue: { keyPath: 'localId' },
    sync_meta: { keyPath: 'key' },
};

let _db = null;

function openDB() {
    return new Promise((resolve, reject) => {
        if (_db) return resolve(_db);
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            for (const [name, opts] of Object.entries(dbStores)) {
                if (!db.objectStoreNames.contains(name)) {
                    db.createObjectStore(name, opts);
                }
            }
        };
        req.onsuccess = (e) => { _db = e.target.result; resolve(_db); };
        req.onerror = (e) => reject(e.target.error);
    });
}

const db = {
    async put(store, data) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readwrite');
            tx.objectStore(store).put(data);
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    },
    async putMany(store, items) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readwrite');
            const os = tx.objectStore(store);
            for (const item of items) os.put(item);
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    },
    async getAll(store) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readonly');
            const req = tx.objectStore(store).getAll();
            req.onsuccess = () => resolve(req.result);
            req.onerror = (e) => reject(e.target.error);
        });
    },
    async get(store, id) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readonly');
            const req = tx.objectStore(store).get(id);
            req.onsuccess = () => resolve(req.result);
            req.onerror = (e) => reject(e.target.error);
        });
    },
    async delete(store, id) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readwrite');
            tx.objectStore(store).delete(id);
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    },
    async clear(store) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readwrite');
            tx.objectStore(store).clear();
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    },
    async count(store) {
        const d = await openDB();
        return new Promise((resolve, reject) => {
            const tx = d.transaction(store, 'readonly');
            const req = tx.objectStore(store).count();
            req.onsuccess = () => resolve(req.result);
            req.onerror = (e) => reject(e.target.error);
        });
    },
};
