const apiFetch = async (url, options = {}) => {
    options.headers = options.headers || {};
    const token = localStorage.getItem('token');
    if (token) options.headers['Authorization'] = 'Bearer ' + token;
    const storeId = localStorage.getItem('store_id');
    if (storeId) options.headers['X-Store-Id'] = storeId;
    return fetch(url, options);
};

const apiPost = (url, data) => apiFetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
});

const apiPut = (url, data) => apiFetch(url, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
});

const apiDelete = (url) => apiFetch(url, { method: 'DELETE' });

const apiJson = async (url, options = {}) => {
    const res = await apiFetch(url, options);
    let text;
    try { text = await res.text(); } catch(e) { return { success: false, message: 'Network error' }; }
    try { return JSON.parse(text); } catch(e) { return { success: false, message: 'Invalid response', raw: text }; }
};
