<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '../api'
import { setUser } from '../session'

const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const pending = ref(false)

async function submit() {
  error.value = ''
  pending.value = true
  try {
    setUser(await login(email.value, password.value))
    await router.push({ name: 'projects' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="auth">
    <p class="brand"><span class="brand-mark" aria-hidden="true" />Watchdog</p>
    <form class="panel" @submit.prevent="submit">
      <h1>Zaloguj się</h1>
      <p v-if="error" class="alert" role="alert">{{ error }}</p>
      <div class="field">
        <label for="email">Adres e-mail</label>
        <input id="email" v-model="email" type="email" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="password">Hasło</label>
        <input id="password" v-model="password" type="password" autocomplete="current-password" required>
      </div>
      <button type="submit" :disabled="pending">{{ pending ? 'Logowanie…' : 'Zaloguj się' }}</button>
    </form>
  </div>
</template>
