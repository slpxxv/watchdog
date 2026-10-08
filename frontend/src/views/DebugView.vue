<script setup lang="ts">
import { onMounted, ref, version } from 'vue'
import { useRouter } from 'vue-router'
import { logout, me, type Me } from '../api'

const router = useRouter()
const user = ref<Me | null>(null)
const error = ref('')
const mode = import.meta.env.MODE

onMounted(async () => {
  try {
    user.value = await me()
    if (!user.value) await router.replace({ name: 'login' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
})

async function signOut() {
  await logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <h1>Debug</h1>
  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <template v-if="user">
    <h2>Użytkownik</h2>
    <table><tbody>
      <tr><th>ID</th><td><code>{{ user.id }}</code></td></tr>
      <tr><th>E-mail</th><td>{{ user.email }}</td></tr>
      <tr><th>Role</th><td>{{ user.roles.join(', ') }}</td></tr>
      <tr><th>Uprawnienia</th><td>{{ user.permissions.join(', ') || '—' }}</td></tr>
      <tr><th>Utworzony</th><td>{{ new Date(user.createdAt).toLocaleString('pl-PL') }}</td></tr>
    </tbody></table>

    <h2>Środowisko</h2>
    <table><tbody>
      <tr><th>Vue</th><td>{{ version }}</td></tr>
      <tr><th>Tryb Vite</th><td>{{ mode }}</td></tr>
      <tr><th>Symfony Profiler</th><td><a href="/_profiler" target="_blank">/_profiler</a></td></tr>
    </tbody></table>

    <h2>GET /api/me</h2>
    <pre>{{ JSON.stringify(user, null, 2) }}</pre>

    <button type="button" @click="signOut">Wyloguj</button>
  </template>
  <p v-else-if="!error" class="muted">Ładowanie…</p>
</template>
