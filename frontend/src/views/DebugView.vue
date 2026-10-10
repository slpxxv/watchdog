<script setup lang="ts">
import { onMounted, ref, version } from 'vue'
import { useRouter } from 'vue-router'
import { loadUser, user } from '../session'

const router = useRouter()
const error = ref('')
const mode = import.meta.env.MODE

onMounted(async () => {
  try {
    if (!(await loadUser())) await router.replace({ name: 'login' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
})
</script>

<template>
  <div class="page-head"><h1>Debug</h1></div>
  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <template v-if="user">
    <h2>Użytkownik</h2>
    <div class="panel">
      <table class="kv">
        <tbody>
          <tr>
            <th>ID</th>
            <td>
              <code>{{ user.id }}</code>
            </td>
          </tr>
          <tr>
            <th>E-mail</th>
            <td>{{ user.email }}</td>
          </tr>
          <tr>
            <th>Role</th>
            <td>{{ user.roles.join(', ') }}</td>
          </tr>
          <tr>
            <th>Uprawnienia</th>
            <td>{{ user.permissions.join(', ') || '—' }}</td>
          </tr>
          <tr>
            <th>Utworzony</th>
            <td>{{ new Date(user.createdAt).toLocaleString('pl-PL') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <h2>Środowisko</h2>
    <div class="panel">
      <table class="kv">
        <tbody>
          <tr>
            <th>Vue</th>
            <td>{{ version }}</td>
          </tr>
          <tr>
            <th>Tryb Vite</th>
            <td>{{ mode }}</td>
          </tr>
          <tr>
            <th>Symfony Profiler</th>
            <td><a href="/_profiler" target="_blank">/_profiler</a></td>
          </tr>
        </tbody>
      </table>
    </div>

    <h2>GET /api/me</h2>
    <pre>{{ JSON.stringify(user, null, 2) }}</pre>
  </template>
  <p v-else-if="!error" class="muted">Ładowanie…</p>
</template>
