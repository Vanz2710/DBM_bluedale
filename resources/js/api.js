import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
});

// Attach token from localStorage on every request.
// Also strip the default JSON Content-Type for FormData requests so axios
// can compute the correct multipart/form-data boundary.
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('crm_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
        delete config.headers.common?.['Content-Type'];
        delete config.headers.post?.['Content-Type'];
    }
    return config;
});

let _router = null;
let _redirecting = false;

export function setRouter(router) {
    _router = router;
}

// Redirect to login on 401 (deduplicated — multiple concurrent 401s only redirect once)
api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401 && !_redirecting) {
            _redirecting = true;
            localStorage.removeItem('crm_token');
            localStorage.removeItem('crm_user');
            if (_router) {
                _router.push('/login').finally(() => { _redirecting = false; });
            } else {
                window.location.href = '/login';
            }
        }
        return Promise.reject(err);
    }
);

export default api;
