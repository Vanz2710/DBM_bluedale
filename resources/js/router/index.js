import Login        from '../pages/Login.vue';
import Dashboard    from '../pages/Dashboard.vue';
import Summary      from '../pages/Summary.vue';
import DailyList    from '../pages/DailyList.vue';
import ContactView  from '../pages/ContactView.vue';
import ContactAdd   from '../pages/ContactAdd.vue';
import ContactEdit  from '../pages/ContactEdit.vue';
import TaskAdd      from '../pages/TaskAdd.vue';
import TodoList     from '../pages/TodoList.vue';
import TodoAdd      from '../pages/TodoAdd.vue';
import TaskEdit     from '../pages/TaskEdit.vue';
import AdminPanel   from '../pages/AdminPanel.vue';
import CrmList      from '../pages/CrmList.vue';
import CrmView      from '../pages/CrmView.vue';
import Exhibitions  from '../pages/Exhibitions.vue';
import TravelHub    from '../pages/TravelHub.vue';
import TravelView   from '../pages/TravelView.vue';
import DataHealth   from '../pages/DataHealth.vue';
import Import       from '../pages/Import.vue';
import SocialMediaReminder from '../pages/SocialMediaReminder.vue';
import PostingCalendar from '../pages/PostingCalendar.vue';
import AiWorkflowDemo from '../pages/AiWorkflowDemo.vue';
import MarketingAiDemo from '../pages/MarketingAiDemo.vue';
import MarketingEmailDemo from '../pages/MarketingEmailDemo.vue';
import ProductAvailability from '../pages/ProductAvailability.vue';
import DeptTaskManager from '../pages/DeptTaskManager.vue';
import ProductionSupport from '../pages/ProductionSupport.vue';

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
    { path: '/admin',                      component: AdminPanel,  name: 'admin' },
    { path: '/crm',                        component: CrmList,     name: 'crm' },
    { path: '/crm/:id',                    component: CrmView,     name: 'crm-view' },
    { path: '/exhibitions',                component: Exhibitions,  name: 'exhibitions' },
    { path: '/travel',                     component: TravelHub,    name: 'travel' },
    { path: '/travel/:id',                 component: TravelView,   name: 'travel-view' },
    { path: '/data-health',                component: DataHealth,   name: 'data-health' },
    { path: '/import',                     component: Import,       name: 'import' },
    { path: '/social-media-reminders',     component: SocialMediaReminder, name: 'social-media-reminders' },
    { path: '/posting-calendar',           component: PostingCalendar, name: 'posting-calendar' },
    { path: '/ai-workflow-demo',           component: AiWorkflowDemo, name: 'ai-workflow-demo' },
    { path: '/marketing-ai-demo',          component: MarketingAiDemo, name: 'marketing-ai-demo' },
    { path: '/marketing-email',            component: MarketingEmailDemo, name: 'marketing-email' },
    { path: '/product-availability',       component: ProductAvailability, name: 'product-availability' },
    { path: '/dept-tasks',                 component: DeptTaskManager,     name: 'dept-tasks' },
    { path: '/production-support',         component: ProductionSupport,   name: 'production-support' },
];

export default routes;

export function setupGuard(router) {
    router.beforeEach((to, from, next) => {
        const isPublic = to.meta?.public === true;
        const token    = localStorage.getItem('crm_token');
        if (!isPublic && !token) {
            next({ name: 'login' });
        } else {
            next();
        }
    });
}
