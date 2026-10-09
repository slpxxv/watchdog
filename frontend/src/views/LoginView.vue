<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '../api'

const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const pending = ref(false)

async function submit() {
  error.value = ''
  pending.value = true
  try {
    await login(email.value, password.value)
    await router.push({ name: 'projects' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <form class="card" @submit.prevent="submit">
    <h1>Logowanie</h1>
    <p v-if="error" class="alert" role="alert">{{ error }}</p>
    <label for="email">Adres e-mail</label>
    <input id="email" v-model="email" type="email" autocomplete="username" required autofocus>
    <label for="password">Hasło</label>
    <input id="password" v-model="password" type="password" autocomplete="current-password" required>
    <button type="submit" :disabled="pending">Zaloguj się</button>
  </form>
</template>
