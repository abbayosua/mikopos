window.apiFetch = async (url, options = {}) => {
    options.headers = options.headers || {};

    const token = localStorage.getItem('token');
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }

    const storeId = localStorage.getItem('store_id');
    if (storeId) {
        options.headers['X-Store-Id'] = storeId;
    }

    return fetch(url, options);
};

window.apiFetchJson = async (url, options = {}) => {
    options.headers = options.headers || {};
    options.headers['Content-Type'] = 'application/json';
    const res = await apiFetch(url, options);
    return res.json();
};

// Client-side cache with TTL
window.cache = {
    _prefix: 'miko_',

    set(key, data, ttlMs) {
        try {
            localStorage.setItem(this._prefix + key, JSON.stringify({
                data, ts: Date.now(), ttl: ttlMs || 0
            }));
        } catch(e) {}
    },

    get(key) {
        try {
            const raw = localStorage.getItem(this._prefix + key);
            if (!raw) return null;
            const entry = JSON.parse(raw);
            if (entry.ttl > 0 && Date.now() - entry.ts > entry.ttl) {
                localStorage.removeItem(this._prefix + key);
                return null;
            }
            return entry.data;
        } catch(e) { return null; }
    },

    remove(key) {
        try { localStorage.removeItem(this._prefix + key); } catch(e) {}
    },

    removePattern(pattern) {
        try {
            const prefix = this._prefix;
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const k = localStorage.key(i);
                if (k && k.startsWith(prefix) && k.includes(pattern)) {
                    localStorage.removeItem(k);
                }
            }
        } catch(e) {}
    }
};
