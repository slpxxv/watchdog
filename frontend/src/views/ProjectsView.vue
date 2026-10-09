<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createProject, deleteProject, listProjects, me, renameProject, type Me, type Project } from '../api'

const MAX_NAME_LENGTH = 100 // mirrors Project::MAX_NAME_LENGTH

const router = useRouter()
const user = ref<Me | null>(null)
const projects = ref<Project[] | null>(null)
const error = ref('')
const pending = ref(false)
const newName = ref('')
const editingId = ref<string | null>(null)
const editName = ref('')

const canManage = computed(() => user.value?.permissions.includes('project.manage') ?? false)

async function run(action: () => Promise<void>) {
  error.value = ''
  pending.value = true
  try {
    await action()
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    pending.value = false
  }
}

onMounted(() => run(async () => {
  user.value = await me()
  if (!user.value) {
    await router.replace({ name: 'login' })
    return
  }
  if (!user.value.permissions.includes('project.view')) throw new Error('Brak uprawnień do przeglądania projektów.')
  projects.value = await listProjects()
}))

function create() {
  return run(async () => {
    projects.value?.push(await createProject(newName.value.trim()))
    newName.value = ''
  })
}

function startEditing(project: Project) {
  editingId.value = project.id
  editName.value = project.name
}

function remove(project: Project) {
  if (!confirm(`Usunąć projekt „${project.name}”? Tej operacji nie można cofnąć.`)) return
  return run(async () => {
    await deleteProject(project.id)
    projects.value = projects.value?.filter((p) => p.id !== project.id) ?? null
  })
}

function rename(project: Project) {
  return run(async () => {
    Object.assign(project, await renameProject(project.id, editName.value.trim()))
    editingId.value = null
  })
}
</script>

<template>
  <h1>Projekty</h1>
  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <form v-if="projects && canManage" class="inline" @submit.prevent="create">
    <label for="new-project" class="visually-hidden">Nazwa nowego projektu</label>
    <input id="new-project" v-model="newName" placeholder="Nazwa nowego projektu" required pattern=".*\S.*" :maxlength="MAX_NAME_LENGTH">
    <button type="submit" :disabled="pending">Dodaj projekt</button>
  </form>

  <template v-if="projects">
    <p v-if="!projects.length" class="muted">Nie ma jeszcze żadnych projektów.</p>
    <table v-else class="list">
      <thead>
        <tr><th>Nazwa</th><th>Utworzony</th><th v-if="canManage"><span class="visually-hidden">Akcje</span></th></tr>
      </thead>
      <tbody>
        <tr v-for="project in projects" :key="project.id">
          <td v-if="editingId === project.id" :colspan="canManage ? 3 : 2">
            <form class="inline" @submit.prevent="rename(project)" @keydown.esc="editingId = null">
              <label :for="`rename-${project.id}`" class="visually-hidden">Nowa nazwa projektu</label>
              <input :id="`rename-${project.id}`" v-model="editName" required pattern=".*\S.*" :maxlength="MAX_NAME_LENGTH" autofocus>
              <button type="submit" :disabled="pending">Zapisz</button>
              <button type="button" class="secondary" @click="editingId = null">Anuluj</button>
            </form>
          </td>
          <template v-else>
            <td>{{ project.name }}</td>
            <td>{{ new Date(project.createdAt).toLocaleString('pl-PL') }}</td>
            <td v-if="canManage" class="actions">
              <button type="button" class="secondary" @click="startEditing(project)">Zmień nazwę</button>
              <button type="button" class="secondary danger" :disabled="pending" @click="remove(project)">Usuń</button>
            </td>
          </template>
        </tr>
      </tbody>
    </table>
  </template>
  <p v-else-if="!error" class="muted">Ładowanie…</p>
</template>
