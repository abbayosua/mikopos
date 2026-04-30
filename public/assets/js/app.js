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
