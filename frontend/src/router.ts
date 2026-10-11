import { createRouter, createWebHistory } from 'vue-router'
import LoginView from './views/LoginView.vue'
import DebugView from './views/DebugView.vue'
import ProjectsView from './views/ProjectsView.vue'
import ProjectView from './views/ProjectView.vue'
import ProjectLogsView from './views/ProjectLogsView.vue'
import SourcesView from './views/SourcesView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/projects' },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/projects', name: 'projects', component: ProjectsView },
    {
      path: '/projects/:id',
      component: ProjectView,
      props: true,
      children: [
        { path: '', redirect: { name: 'project-logs' } },
        { path: 'logs', name: 'project-logs', component: ProjectLogsView, props: true },
        { path: 'sources', name: 'project-sources', component: SourcesView, props: true },
      ],
    },
    { path: '/debug', name: 'debug', component: DebugView },
  ],
})
