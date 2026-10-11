<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue'
import { useRouter } from 'vue-router'
import { createProject, deleteProject, listProjects, renameProject, type Project } from '../api'
import { loadUser, user } from '../session'

const MAX_NAME_LENGTH = 100 // mirrors Project::MAX_NAME_LENGTH

const router = useRouter()
const projects = ref<Project[] | null>(null)
const error = ref('')
const pending = ref(false)
const newName = ref('')
const editingId = ref<string | null>(null)
const editName = ref('')
const toDelete = ref<Project | null>(null)
const confirmDialog = useTemplateRef<HTMLDialogElement>('confirm-dialog')

const plural = new Intl.PluralRules('pl')
const projectForms: Partial<Record<Intl.LDMLPluralRule, string>> = { one: 'projekt', few: 'projekty' }
const countLabel = computed(() => {
  const n = projects.value?.length ?? 0
  return `${n} ${projectForms[plural.select(n)] ?? 'projektów'}`
})
const dateFormat = new Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' })

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

onMounted(() =>
  run(async () => {
    if (!(await loadUser())) {
      await router.replace({ name: 'login' })
      return
    }
    if (!user.value?.permissions.includes('project.view')) throw new Error('Brak uprawnień do przeglądania projektów.')
    projects.value = await listProjects()
  }),
)

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

function askToDelete(project: Project) {
  toDelete.value = project
  if (!confirmDialog.value) return
  confirmDialog.value.returnValue = '' // Esc keeps the previous value; never let it count as a confirm
  confirmDialog.value.showModal()
}

function onConfirmClose() {
  const project = toDelete.value
  const confirmed = confirmDialog.value?.returnValue === 'confirm'
  toDelete.value = null
  if (!project || !confirmed) return
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
  <div class="page-head">
    <div>
      <h1>Projekty</h1>
      <p v-if="projects" class="muted">{{ countLabel }}</p>
    </div>
  </div>

  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <section v-if="projects" class="panel" aria-label="Lista projektów">
    <form v-if="canManage" class="panel-head inline" @submit.prevent="create">
      <label for="new-project" class="visually-hidden">Nazwa nowego projektu</label>
      <input
        id="new-project"
        v-model="newName"
        placeholder="Nazwa nowego projektu"
        required
        pattern=".*\S.*"
        :maxlength="MAX_NAME_LENGTH"
      />
      <button type="submit" :disabled="pending">Dodaj projekt</button>
    </form>

    <p v-if="!projects.length" class="empty">
      {{
        canManage
          ? 'Nie masz jeszcze projektów. Wpisz nazwę powyżej, żeby dodać pierwszy.'
          : 'Nie ma jeszcze żadnych projektów.'
      }}
    </p>

    <ul v-else class="rows">
      <li v-for="project in projects" :key="project.id" class="row">
        <form
          v-if="editingId === project.id"
          class="inline"
          @submit.prevent="rename(project)"
          @keydown.esc="editingId = null"
        >
          <label :for="`rename-${project.id}`" class="visually-hidden">Nowa nazwa projektu „{{ project.name }}”</label>
          <input
            :id="`rename-${project.id}`"
            v-model="editName"
            required
            pattern=".*\S.*"
            :maxlength="MAX_NAME_LENGTH"
            autofocus
          />
          <button type="submit" :disabled="pending">Zapisz</button>
          <button type="button" class="ghost" @click="editingId = null">Anuluj</button>
        </form>
        <template v-else>
          <RouterLink :to="{ name: 'project-logs', params: { id: project.id } }" class="row-name row-link">{{
            project.name
          }}</RouterLink>
          <time class="row-meta" :datetime="project.createdAt">{{
            dateFormat.format(new Date(project.createdAt))
          }}</time>
          <span v-if="canManage" class="row-actions">
            <button type="button" class="ghost" @click="startEditing(project)">Zmień nazwę</button>
            <button type="button" class="ghost danger" :disabled="pending" @click="askToDelete(project)">Usuń</button>
          </span>
        </template>
      </li>
    </ul>
  </section>
  <p v-else-if="!error" class="muted">Ładowanie…</p>

  <dialog ref="confirm-dialog" aria-labelledby="confirm-title" @close="onConfirmClose">
    <form method="dialog">
      <h2 id="confirm-title">Usunąć projekt?</h2>
      <p class="muted">Projekt „{{ toDelete?.name }}” zostanie usunięty na stałe.</p>
      <div class="dialog-actions">
        <button value="cancel" class="ghost" autofocus>Anuluj</button>
        <button value="confirm" class="destructive">Usuń projekt</button>
      </div>
    </form>
  </dialog>
</template>
