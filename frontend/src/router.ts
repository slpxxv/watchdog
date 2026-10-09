import { createRouter, createWebHistory } from 'vue-router'
import LoginView from './views/LoginView.vue'
import DebugView from './views/DebugView.vue'
import ProjectsView from './views/ProjectsView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/projects' },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/projects', name: 'projects', component: ProjectsView },
    { path: '/debug', name: 'debug', component: DebugView },
  ],
})
