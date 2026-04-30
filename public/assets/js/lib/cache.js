const mikoCache = {
    set(key, data, ttlMs) {
        try { localStorage.setItem('miko_' + key, JSON.stringify({ data, ts: Date.now(), ttl: ttlMs || 0 })); } catch(e) {}
    },
    get(key) {
        try {
            const raw = localStorage.getItem('miko_' + key);
            if (!raw) return null;
            const entry = JSON.parse(raw);
            if (entry.ttl > 0 && Date.now() - entry.ts > entry.ttl) { localStorage.removeItem('miko_' + key); return null; }
            return entry.data;
        } catch(e) { return null; }
    },
    remove(key) { try { localStorage.removeItem('miko_' + key); } catch(e) {} },
    removePattern(pattern) {
        try {
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const k = localStorage.key(i);
                if (k && k.startsWith('miko_') && k.includes(pattern)) localStorage.removeItem(k);
            }
        } catch(e) {}
    },
};
