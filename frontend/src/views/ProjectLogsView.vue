<script setup lang="ts">
defineProps<{ id: string }>()
</script>

<template>
  <section class="panel">
    <p class="empty">
      Logi pojawią się tutaj, gdy źródło wyśle pierwsze linie.
      <RouterLink :to="{ name: 'project-sources', params: { id } }">Dodaj źródło</RouterLink>, żeby dostać token.
    </p>
  </section>
</template>
