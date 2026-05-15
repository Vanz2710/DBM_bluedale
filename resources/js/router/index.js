const Login               = () => import('../pages/Login.vue');
const DealList            = () => import('../pages/DealList.vue');
const DealAdd             = () => import('../pages/DealAdd.vue');
const DealEdit            = () => import('../pages/DealEdit.vue');
const Dashboard           = () => import('../pages/Dashboard.vue');
const FollowUpList        = () => import('../pages/FollowUpList.vue');
const FollowUpAdd         = () => import('../pages/FollowUpAdd.vue');
const FollowUpEdit        = () => import('../pages/FollowUpEdit.vue');
const ProjectList         = () => import('../pages/ProjectList.vue');
const ProjectAdd          = () => import('../pages/ProjectAdd.vue');
const ProjectEdit         = () => import('../pages/ProjectEdit.vue');
const Performance         = () => import('../pages/Performance.vue');
const PerformanceTargets  = () => import('../pages/PerformanceTargets.vue');
const Summary             = () => import('../pages/Summary.vue');
const DailyList           = () => import('../pages/DailyList.vue');
const ContactView         = () => import('../pages/ContactView.vue');
const ContactAdd          = () => import('../pages/ContactAdd.vue');
const ContactEdit         = () => import('../pages/ContactEdit.vue');
const TaskAdd             = () => import('../pages/TaskAdd.vue');
const TodoList            = () => import('../pages/TodoList.vue');
const TodoAdd             = () => import('../pages/TodoAdd.vue');
const TaskEdit            = () => import('../pages/TaskEdit.vue');
const AdminPanel          = () => import('../pages/AdminPanel.vue');
const RbacPanel           = () => import('../pages/RbacPanel.vue');
const CrmList             = () => import('../pages/CrmList.vue');
const CrmView             = () => import('../pages/CrmView.vue');
const DataHealth          = () => import('../pages/DataHealth.vue');
const Import              = () => import('../pages/Import.vue');
const Reminders           = () => import('../pages/Reminders.vue');

const routes = [
    { path: '/login',                      component: Login,       name: 'login',        meta: { public: true } },
    { path: '/',                           component: Dashboard,   name: 'home' },
    { path: '/summary',                    component: Summary,     name: 'summary' },
    { path: '/list',                       component: DailyList,   name: 'list' },
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
    { path: '/performance',                component: Performance,        name: 'performance' },
    { path: '/admin/performance-targets',  component: PerformanceTargets, name: 'perf-targets', meta: { adminOnly: true } },
    { path: '/admin',                      component: AdminPanel,  name: 'admin',       meta: { adminOnly: true } },
    { path: '/admin/rbac',                 component: RbacPanel,   name: 'rbac',        meta: { adminOnly: true } },
    { path: '/crm',                        component: CrmList,     name: 'crm' },
    { path: '/crm/:id',                    component: CrmView,     name: 'crm-view' },
{ path: '/data-health',                component: DataHealth,   name: 'data-health' },
    { path: '/import',                     component: Import,       name: 'import' },
    { path: '/reminders',                  component: Reminders,    name: 'reminders' },
];

export default routes;

export function setupGuard(router) {
    router.beforeEach((to, from, next) => {
        const isPublic = to.meta?.public === true;
        const token    = localStorage.getItem('crm_token');
        if (!isPublic && !token) {
            return next({ name: 'login' });
        }
        if (to.meta?.adminOnly) {
            const user  = JSON.parse(localStorage.getItem('crm_user') || 'null');
            const roles = user?.roles ?? [];
            if (!roles.includes('admin') && !roles.includes('super-admin')) {
                return next({ name: 'home' });
            }
        }
        next();
    });
}
