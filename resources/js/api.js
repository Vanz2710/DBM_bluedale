import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
});

// Short-lived GET response cache — prevents duplicate requests fired by multiple
// components mounting at the same time (dashboard widgets, notification bell, etc.)
const _cache      = new Map(); // key → { data, exp }
const _TTL        = 30_000;    // 30 s default
const _LONG_TTL   = 300_000;   // 5 min for stable reference data that never changes mid-session
const LONG_TTL_URLS = new Set(['/v1/lookups', '/v1/me/settings']);
const SKIP_CACHE  = new Set(['/v1/auth/login']); // never cache these

function _cacheKey(config) {
    const p = config.params ? JSON.stringify(config.params) : '';
    return (config.url ?? '') + p;
}

export function bustCache(urlPrefix) {
    for (const k of _cache.keys()) {
        if (k.startsWith(urlPrefix)) _cache.delete(k);
    }
}

// Attach token + serve cached GET responses where available.
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('crm_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
        delete config.headers.common?.['Content-Type'];
        delete config.headers.post?.['Content-Type'];
    }

    const method = (config.method ?? 'get').toLowerCase();
    if (method === 'get' && !config._noCache && !SKIP_CACHE.has(config.url)) {
        const hit = _cache.get(_cacheKey(config));
        if (hit && Date.now() < hit.exp) {
            // Return cached data via a custom adapter — no HTTP request made
            config.adapter = () => Promise.resolve({
                data: hit.data, status: 200, statusText: 'OK (cached)',
                headers: {}, config, request: {},
            });
        }
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
    (res) => {
        const method = (res.config?.method ?? 'get').toLowerCase();
        if (method === 'get' && res.status === 200 && !res.config?._noCache
            && !SKIP_CACHE.has(res.config?.url)) {
            // Populate cache for successful GET responses
            const ttl = LONG_TTL_URLS.has(res.config?.url) ? _LONG_TTL : _TTL;
            _cache.set(_cacheKey(res.config), { data: res.data, exp: Date.now() + ttl });
        } else if (method !== 'get') {
            // Any successful write (POST/PUT/PATCH/DELETE) invalidates every cached
            // read. Without this, a page's own re-fetch right after a save can hit
            // the pre-mutation cache entry and silently show stale data until a hard
            // refresh — a stale list is worse than a few extra re-fetches.
            _cache.clear();
        }
        return res;
    },
    (err) => {
        if (err.response?.status === 503 && err.response?.data?.error === 'maintenance') {
            window.dispatchEvent(new CustomEvent('crm-maintenance', {
                detail: { message: err.response.data.message ?? 'System is under maintenance.' },
            }));
            return Promise.reject(err);
        }
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
