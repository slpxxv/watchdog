<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { logout } from './api'
import { loadUser, setUser, user } from './session'

const route = useRoute()
const router = useRouter()
// Hidden on the login page and while the first route is still resolving.
const showShell = computed(() => route.name !== undefined && route.name !== 'login')
const roleLabel = computed(() => (user.value?.roles.includes('super_admin') ? 'Super admin' : 'Użytkownik'))

watch(showShell, (shown) => {
  if (shown) loadUser().catch(() => {}) // views report load errors themselves
}, { immediate: true })

async function signOut() {
  await logout()
  setUser(null)
  await router.push({ name: 'login' })
}
</script>

<template>
  <div v-if="showShell" class="shell">
    <aside class="sidebar glass">
      <RouterLink :to="{ name: 'projects' }" class="brand">
        <span class="brand-mark" aria-hidden="true" />Watchdog
      </RouterLink>
      <nav aria-label="Główna nawigacja">
        <RouterLink :to="{ name: 'projects' }">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></svg>
          Projekty
        </RouterLink>
        <RouterLink :to="{ name: 'debug' }">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 9h8M8 13h8M8 17h5M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" /></svg>
          Debug
        </RouterLink>
      </nav>
    </aside>

    <div class="shell-main">
      <header class="topbar glass">
        <span v-if="user" class="avatar" :title="`${user.email} (${roleLabel})`">
          <span aria-hidden="true">{{ user.email.charAt(0).toUpperCase() }}</span>
          <span class="visually-hidden">Zalogowano jako {{ user.email }}, {{ roleLabel }}</span>
        </span>
        <button type="button" class="ghost" @click="signOut">Wyloguj</button>
      </header>
      <main><RouterView /></main>
    </div>
  </div>
  <main v-else><RouterView /></main>
</template>
