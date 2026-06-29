const Login               = () => import('../pages/Login.vue');
const DealList            = () => import('../pages/DealList.vue');
const DealAdd             = () => import('../pages/DealAdd.vue');
const DealEdit            = () => import('../pages/DealEdit.vue');
const Dashboard           = () => import('../pages/Dashboard.vue');
const FollowUpList        = () => import('../pages/FollowUpList.vue');
const FollowUpAdd         = () => import('../pages/FollowUpAdd.vue');
const FollowUpEdit        = () => import('../pages/FollowUpEdit.vue');
const ForecastList        = () => import('../pages/ForecastList.vue');
const ForecastSummary     = () => import('../pages/ForecastSummary.vue');
const ProjectList         = () => import('../pages/ProjectList.vue');
const ProjectAdd          = () => import('../pages/ProjectAdd.vue');
const ProjectEdit         = () => import('../pages/ProjectEdit.vue');
const Performance         = () => import('../pages/Performance.vue');
const ContactList         = () => import('../pages/ContactList.vue');
const ContactView         = () => import('../pages/ContactView.vue');
const ContactAdd          = () => import('../pages/ContactAdd.vue');
const ContactEdit         = () => import('../pages/ContactEdit.vue');
const TaskAdd             = () => import('../pages/TaskAdd.vue');
const TodoList            = () => import('../pages/TodoList.vue');
const TodoAdd             = () => import('../pages/TodoAdd.vue');
const TaskEdit            = () => import('../pages/TodoEdit.vue');
const TodoDetail          = () => import('../pages/TodoDetail.vue');
const AdminPanel          = () => import('../pages/AdminPanel.vue');
const RbacPanel           = () => import('../pages/RbacPanel.vue');
const DataHealth          = () => import('../pages/DataHealth.vue');
const Import              = () => import('../pages/Import.vue');
const Reminders           = () => import('../pages/Reminders.vue');
const MyProfile           = () => import('../pages/MyProfile.vue');
const Reports             = () => import('../pages/Reports.vue');
const LeadForm            = () => import('../pages/LeadForm.vue');
const Settings                = () => import('../pages/Settings.vue');
const SocialMediaReminder     = () => import('../pages/SocialMediaReminder.vue');
const PostingCalendar         = () => import('../pages/PostingCalendar.vue');
const MarketingEmail          = () => import('../pages/EmailCampaigns.vue');
const SiteAvailability        = () => import('../pages/SiteAvailability.vue');
const ContactAnalysis         = () => import('../pages/ContactAnalysis.vue');
const PredictiveInsights      = () => import('../pages/PredictiveInsights.vue');
const SystemSettings          = () => import('../pages/SystemSettings.vue');
const UserActivity            = () => import('../pages/UserActivity.vue');
const Forbidden               = () => import('../pages/Forbidden.vue');
const AuditLog                = () => import('../pages/AuditLog.vue');
const DeptTaskManager         = () => import('../pages/DeptTaskManager.vue');
const ContactDuplicates       = () => import('../pages/ContactDuplicates.vue');
const Announcements           = () => import('../pages/Announcements.vue');
const Noticeboard             = () => import('../pages/Noticeboard.vue');
const XPanel                  = () => import('../pages/XPanel.vue');

const routes = [
    { path: '/login',        component: Login,       name: 'login',        meta: { public: true } },
    { path: '/lead',         component: LeadForm,    name: 'lead-form',    meta: { public: true } },
    { path: '/',                           component: Dashboard,   name: 'home' },
    { path: '/list',                       component: ContactList,     name: 'list' },
    { path: '/contact-analysis',           component: ContactAnalysis,    name: 'contact-analysis' },
    { path: '/predictive-insights',        component: PredictiveInsights, name: 'predictive-insights' },
    { path: '/contacts/add',               component: ContactAdd,  name: 'contact-add' },
    { path: '/contacts/:id',               component: ContactView, name: 'contact-view' },
    { path: '/contacts/:id/edit',          component: ContactEdit, name: 'contact-edit' },
    { path: '/contacts/:id/task/add',      component: TaskAdd,     name: 'task-add' },
    { path: '/todos',                      redirect: (to) => ({ path: '/list', query: { tab: 'tasks', ...to.query } }) },
    { path: '/todos/add',                  component: TodoAdd,     name: 'todo-add' },
    { path: '/todos/:id/edit',             component: TaskEdit,    name: 'task-edit' },
    { path: '/todos/:id',                  component: TodoDetail,  name: 'todo-view' },
    { path: '/followups',                  redirect: (to) => ({ path: '/list', query: { tab: 'followups', ...to.query } }) },
    { path: '/followups/add',              component: FollowUpAdd,  name: 'followup-add' },
    { path: '/followups/:id/edit',         component: FollowUpEdit, name: 'followup-edit' },
    { path: '/projects',                   component: ProjectList,        name: 'projects' },
    { path: '/projects/add',               component: ProjectAdd,         name: 'project-add' },
    { path: '/projects/:id/edit',          component: ProjectEdit,        name: 'project-edit' },
    { path: '/deals',                      component: DealList,           name: 'deals' },
    { path: '/deals/add',                  component: DealAdd,            name: 'deal-add' },
    { path: '/deals/:id/edit',             component: DealEdit,           name: 'deal-edit' },
    { path: '/forecasts',                  redirect: () => ({ path: '/list', query: { tab: 'forecast' } }) },
    { path: '/forecasts/summary',          component: ForecastSummary,     name: 'forecast-summary' },
    { path: '/performance',                component: Performance,        name: 'performance' },
    { path: '/admin',                      component: AdminPanel,       name: 'admin',        meta: { adminOnly: true, permission: 'manage lookups' } },
    { path: '/admin/rbac',                 component: RbacPanel,        name: 'rbac',         meta: { adminOnly: true, permission: 'manage users' } },
{ path: '/data-health',                component: DataHealth,   name: 'data-health' },
    { path: '/import',                     component: Import,       name: 'import' },
    { path: '/reminders',                  component: Reminders,    name: 'reminders' },
    { path: '/notice-board',              component: Noticeboard,  name: 'notice-board' },
    { path: '/profile',                    component: MyProfile,    name: 'profile' },
    { path: '/reports',                    component: Reports,      name: 'reports' },
    { path: '/admin/system-settings',     component: SystemSettings,  name: 'system-settings', meta: { adminOnly: true, permission: 'manage users' } },
    { path: '/admin/user-activity',       component: UserActivity,    name: 'user-activity',   meta: { adminOnly: true, permission: 'manage users' } },
    { path: '/admin/audit-log',           component: AuditLog,        name: 'audit-log',       meta: { adminOnly: true, permission: 'manage users' } },
    { path: '/admin/contact-duplicates', component: ContactDuplicates, name: 'contact-duplicates', meta: { adminOnly: true } },
    { path: '/admin/announcements',      component: Announcements,     name: 'announcements',      meta: { adminOnly: true } },
    { path: '/settings',                   component: Settings,          name: 'settings' },
    { path: '/social-media',               component: SocialMediaReminder, name: 'social-media',          meta: { permission: 'manage social-media' } },
    { path: '/posting-calendar',           component: PostingCalendar,     name: 'posting-calendar',      meta: { permission: 'manage posting-calendar' } },
    { path: '/marketing-email',            component: MarketingEmail,      name: 'marketing-email',       meta: { permission: 'manage email-campaigns' } },
    { path: '/site-availability',          component: SiteAvailability,    name: 'site-availability',     meta: { permission: 'manage site-availability' } },
    { path: '/dept-tasks',                 component: DeptTaskManager,     name: 'dept-tasks',            meta: { permission: 'manage dept-tasks' } },
    { path: '/forbidden',                  component: Forbidden,           name: 'forbidden' },
    { path: '/xp',                         component: XPanel,              name: 'xp',        meta: { public: true, standalone: true } },
];

export default routes;

export function setupGuard(router) {
    router.beforeEach((to, from, next) => {
        const token    = localStorage.getItem('crm_token');
        const user     = JSON.parse(localStorage.getItem('crm_user') || 'null');
        const isPublic     = to.meta?.public     === true;
        const isStandalone = to.meta?.standalone === true;

        // No token: allow public/standalone pages, redirect others to login
        if (!token) {
            return (isPublic || isStandalone) ? next() : next({ name: 'login' });
        }

        if (to.meta?.adminOnly) {
            const roles = user?.roles ?? [];
            if (!roles.includes('admin') && !roles.includes('super-admin')) {
                return next({ name: 'home' });
            }
        }

        if (to.meta?.permission) {
            const roles = user?.roles ?? [];
            const permissions = user?.permissions ?? [];
            if (!roles.includes('super-admin') && !permissions.includes(to.meta.permission)) {
                return next({ name: 'forbidden', query: { permission: to.meta.permission } });
            }
        }

        next();
    });
}
