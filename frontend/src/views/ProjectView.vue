<script setup lang="ts">
import { ref, watch } from 'vue'
import { getProject, type Project } from '../api'

const props = defineProps<{ id: string }>()
const project = ref<Project | null>(null)
const error = ref('')

watch(
  () => props.id,
  async (id) => {
    project.value = null
    error.value = ''
    try {
      project.value = await getProject(id)
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e)
    }
  },
  { immediate: true },
)
</script>

<template>
  <RouterLink :to="{ name: 'projects' }" class="back-link">Projekty</RouterLink>
  <div class="page-head">
    <h1>{{ project?.name ?? ' ' }}</h1>
  </div>
  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <template v-if="project">
    <nav class="tabs" aria-label="Sekcje projektu">
      <RouterLink :to="{ name: 'project-logs', params: { id } }">Logi</RouterLink>
      <RouterLink :to="{ name: 'project-sources', params: { id } }">Źródła</RouterLink>
    </nav>
    <RouterView />
  </template>
</template>
