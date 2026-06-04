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
let _last403At = 0;

export function setRouter(router) {
    _router = router;
}

// Redirect to login on 401; show toast on 403 (deduplicated — one toast per 3s)
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
        if (err.response?.status === 403) {
            // Only toast for mutating requests — GET 403s are page-load sub-resource
            // failures that shouldn't interrupt the user with an action-framed message.
            const method = (err.config?.method ?? 'get').toUpperCase();
            if (method !== 'GET') {
                const now = Date.now();
                if (now - _last403At > 3000) {
                    _last403At = now;
                    const raw = err.response?.data?.message ?? '';
                    const isGeneric = !raw || raw.toLowerCase().includes('unauthorized') || raw.toLowerCase().includes('forbidden');
                    let message;
                    if (isGeneric) {
                        const routePerm = _router?.currentRoute?.value?.meta?.permission ?? null;
                        const feature = routePerm
                            ? routePerm.replace(/^(manage|view|create|edit|delete)\s+/i, '').replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
                            : null;
                        message = feature
                            ? `You don't have permission to do this in ${feature}. Contact your admin.`
                            : "You don't have permission to perform this action. Contact your admin.";
                    } else {
                        message = raw;
                    }
                    window.dispatchEvent(new CustomEvent('crm-toast', {
                        detail: { message, type: 'error' },
                    }));
                }
            }
        }
        return Promise.reject(err);
    }
);

export default api;
