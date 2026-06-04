import { computed } from 'vue';

export function usePermissions() {
    const user = computed(() => JSON.parse(localStorage.getItem('crm_user') || 'null'));

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
