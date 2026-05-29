const Login               = () => import('../pages/Login.vue');
const VerifyEmail         = () => import('../pages/VerifyEmail.vue');
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
const PerformanceTargets  = () => import('../pages/PerformanceTargets.vue');
const ContactList         = () => import('../pages/ContactList.vue');
const ContactView         = () => import('../pages/ContactView.vue');
const ContactAdd          = () => import('../pages/ContactAdd.vue');
const ContactEdit         = () => import('../pages/ContactEdit.vue');
const TaskAdd             = () => import('../pages/TaskAdd.vue');
const TodoList            = () => import('../pages/TodoList.vue');
const TodoAdd             = () => import('../pages/TodoAdd.vue');
const TaskEdit            = () => import('../pages/TodoEdit.vue');
const AdminPanel          = () => import('../pages/AdminPanel.vue');
const RbacPanel           = () => import('../pages/RbacPanel.vue');
const CrmList             = () => import('../pages/CrmList.vue');
const CrmView             = () => import('../pages/CrmView.vue');
const DataHealth          = () => import('../pages/DataHealth.vue');
const Import              = () => import('../pages/Import.vue');
const Reminders           = () => import('../pages/Reminders.vue');
const MyProfile           = () => import('../pages/MyProfile.vue');
const Reports             = () => import('../pages/Reports.vue');
const Webhooks            = () => import('../pages/Webhooks.vue');
const LeadForm            = () => import('../pages/LeadForm.vue');
const Settings                = () => import('../pages/Settings.vue');
const SocialMediaReminder     = () => import('../pages/SocialMediaReminder.vue');
const PostingCalendar         = () => import('../pages/PostingCalendar.vue');
const MarketingEmail          = () => import('../pages/EmailCampaigns.vue');
const ProductAvailability     = () => import('../pages/ProductAvailability.vue');
const ContactAnalysis         = () => import('../pages/ContactAnalysis.vue');
const PredictiveInsights      = () => import('../pages/PredictiveInsights.vue');

const routes = [
    { path: '/login',        component: Login,       name: 'login',        meta: { public: true } },
    { path: '/verify-email', component: VerifyEmail, name: 'verify-email', meta: { public: true } },
    { path: '/lead',         component: LeadForm,    name: 'lead-form',    meta: { public: true } },
    { path: '/',                           component: Dashboard,   name: 'home' },
    { path: '/list',                       component: ContactList,     name: 'list' },
    { path: '/contact-analysis',           component: ContactAnalysis,    name: 'contact-analysis' },
    { path: '/predictive-insights',        component: PredictiveInsights, name: 'predictive-insights' },
    { path: '/contacts/add',               component: ContactAdd,  name: 'contact-add' },
    { path: '/contacts/:id',               component: ContactView, name: 'contact-view' },
    { path: '/contacts/:id/edit',          component: ContactEdit, name: 'contact-edit' },
    { path: '/contacts/:id/task/add',      component: TaskAdd,     name: 'task-add' },
    { path: '/todos',                      component: TodoList,    name: 'todos' },
    { path: '/todos/add',                  component: TodoAdd,     name: 'todo-add' },
    { path: '/todos/:id/edit',             component: TaskEdit,    name: 'task-edit' },
    { path: '/followups',                  component: FollowUpList, name: 'followups' },
    { path: '/followups/add',              component: FollowUpAdd,  name: 'followup-add' },
    { path: '/followups/:id/edit',         component: FollowUpEdit, name: 'followup-edit' },
    { path: '/projects',                   component: ProjectList,        name: 'projects' },
    { path: '/projects/add',               component: ProjectAdd,         name: 'project-add' },
    { path: '/projects/:id/edit',          component: ProjectEdit,        name: 'project-edit' },
    { path: '/deals',                      component: DealList,           name: 'deals' },
    { path: '/deals/add',                  component: DealAdd,            name: 'deal-add' },
    { path: '/deals/:id/edit',             component: DealEdit,           name: 'deal-edit' },
    { path: '/forecasts',                  component: ForecastList,        name: 'forecasts' },
    { path: '/forecasts/summary',          component: ForecastSummary,     name: 'forecast-summary' },
    { path: '/performance',                component: Performance,        name: 'performance' },
    { path: '/admin/performance-targets',  component: PerformanceTargets, name: 'perf-targets', meta: { adminOnly: true } },
    { path: '/admin',                      component: AdminPanel,  name: 'admin',       meta: { adminOnly: true } },
    { path: '/admin/rbac',                 component: RbacPanel,   name: 'rbac',        meta: { adminOnly: true } },
    { path: '/crm',                        component: CrmList,     name: 'crm' },
    { path: '/crm/:id',                    component: CrmView,     name: 'crm-view' },
{ path: '/data-health',                component: DataHealth,   name: 'data-health' },
    { path: '/import',                     component: Import,       name: 'import' },
    { path: '/reminders',                  component: Reminders,    name: 'reminders' },
    { path: '/profile',                    component: MyProfile,    name: 'profile' },
    { path: '/reports',                    component: Reports,      name: 'reports' },
    { path: '/admin/webhooks',             component: Webhooks,     name: 'webhooks',     meta: { adminOnly: true } },
    { path: '/settings',                   component: Settings,          name: 'settings' },
    { path: '/social-media',               component: SocialMediaReminder, name: 'social-media' },
    { path: '/posting-calendar',           component: PostingCalendar,     name: 'posting-calendar' },
    { path: '/marketing-email',            component: MarketingEmail,      name: 'marketing-email' },
    { path: '/product-availability',       component: ProductAvailability, name: 'product-availability' },
];

export default routes;

export function setupGuard(router) {
    router.beforeEach((to, from, next) => {
        const token    = localStorage.getItem('crm_token');
        const user     = JSON.parse(localStorage.getItem('crm_user') || 'null');
        const isPublic = to.meta?.public === true;

        // No token: allow public pages, redirect others to login
        if (!token) {
            return isPublic ? next() : next({ name: 'login' });
        }

        // Has token + verified: redirect away from verify-email to dashboard
        if (to.name === 'verify-email' && user?.email_verified) {
            return next({ name: 'home' });
        }

        // Has token + unverified: only allow public routes and verify-email
        if (!isPublic && !user?.email_verified && to.name !== 'verify-email') {
            return next({ name: 'verify-email' });
        }

        if (to.meta?.adminOnly) {
            const roles = user?.roles ?? [];
            if (!roles.includes('admin') && !roles.includes('super-admin')) {
                return next({ name: 'home' });
            }
        }

        next();
    });
}
