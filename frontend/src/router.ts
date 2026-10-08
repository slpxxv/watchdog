import { createRouter, createWebHistory } from 'vue-router'
import LoginView from './views/LoginView.vue'
import DebugView from './views/DebugView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/debug' },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/debug', name: 'debug', component: DebugView },
  ],
})
