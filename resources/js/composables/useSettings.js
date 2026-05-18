import { reactive } from 'vue';
import api from '../api.js';

const LS_KEY = 'crm_settings';

const DEFAULTS = {
    theme:             'system',
    timezone:          '',
    date_format:       'DD/MM/YYYY',
    time_format:       '12h',
    first_day_of_week: 'monday',
    notifications: {
        crm_reminders:   true,
        whatsapp_alerts: true,
        deal_updates:    true,
        task_reminders:  true,
    },
    crm: {
        default_landing:       '/',
        contact_list_density:  'comfortable',
        records_per_page:      20,
        show_completed_tasks:  false,
        pipeline_view:         'list',
    },
};

function deepMerge(target, source) {
    const result = { ...target };
    for (const key of Object.keys(source ?? {})) {
        if (source[key] !== null && typeof source[key] === 'object' && !Array.isArray(source[key])) {
            result[key] = deepMerge(target[key] ?? {}, source[key]);
        } else {
            result[key] = source[key];
        }
    }
    return result;
}

function loadFromLS() {
    try {
        const raw = localStorage.getItem(LS_KEY);
        if (raw) return deepMerge({ ...DEFAULTS }, JSON.parse(raw));
    } catch (_) {}
    return null;
}

// Singleton reactive settings object — shared across all useSettings() calls
const settings = reactive(loadFromLS() ?? deepMerge({}, DEFAULTS));

function systemIsDark() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

export function applyTheme(theme) {
    const isDark = theme === 'dark' || (theme === 'system' && systemIsDark());
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
}

// Apply theme immediately from localStorage (runs at module load, before first render)
applyTheme(settings.theme);

// Re-apply if the OS preference changes while theme is set to 'system'
window.matchMedia?.('(prefers-color-scheme: dark)')
    .addEventListener('change', () => {
        if (settings.theme === 'system') applyTheme('system');
    });

export function useSettings() {
    function persistToLS() {
        localStorage.setItem(LS_KEY, JSON.stringify(settings));
    }

    async function loadFromServer() {
        try {
            if (!localStorage.getItem('crm_token')) return;
            const res = await api.get('/v1/me/settings');
            const remote = res.data.settings;
            if (remote) {
                const merged = deepMerge(deepMerge({}, DEFAULTS), remote);
                Object.assign(settings, merged);
                settings.notifications = { ...merged.notifications };
                settings.crm           = { ...merged.crm };
                persistToLS();
                applyTheme(settings.theme);
            }
        } catch (_) {}
    }

    async function saveSettings() {
        persistToLS();
        applyTheme(settings.theme);
        try {
            await api.put('/v1/me/settings', {
                theme:             settings.theme,
                timezone:          settings.timezone,
                date_format:       settings.date_format,
                time_format:       settings.time_format,
                first_day_of_week: settings.first_day_of_week,
                notifications:     { ...settings.notifications },
                crm:               { ...settings.crm },
            });
        } catch (_) {
            // Silently fail — localStorage already persisted
        }
    }

    return { settings, loadFromServer, saveSettings };
}
