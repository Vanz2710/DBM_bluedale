import { ref } from 'vue';
import api from '../api.js';

// Module-level singleton — one fetch per session regardless of how many components call useLookups()
let cache = null;
let pending = null;

export function useLookups() {
    const lookups = ref(cache ?? {});
    const loading = ref(!cache);

    async function load() {
        if (cache) {
            lookups.value = cache;
            loading.value = false;
            return;
        }
        if (!pending) {
            pending = api.get('/v1/lookups').then(({ data }) => {
                cache = data;
                pending = null;
                return data;
            });
        }
        try {
            lookups.value = await pending;
        } finally {
            loading.value = false;
        }
    }

    function invalidate() {
        cache = null;
        pending = null;
    }

    return { lookups, loading, load, invalidate };
}
