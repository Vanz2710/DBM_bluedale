import { computed } from 'vue';
import { getStoredUser } from '../utils/storage.js';

export function usePermissions() {
    const user = computed(() => getStoredUser());

    // null permissions = super-admin (full access via Gate::before, no DB rows).
    // Also handle stale localStorage where super-admin has an empty array instead of null.
    function can(perm) {
        const u = user.value;
        if (!u) return false;
        if (u.permissions === null) return true;
        if ((u.roles ?? []).includes('super-admin')) return true;
        return (u.permissions ?? []).includes(perm);
    }

    const isAdmin = computed(() => {
        const roles = user.value?.roles ?? [];
        return roles.includes('admin') || roles.includes('super-admin');
    });

    return { can, isAdmin };
}
