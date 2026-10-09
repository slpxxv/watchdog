<template>
  <header class="topbar">
    <strong>Watchdog</strong>
    <nav>
      <RouterLink :to="{ name: 'projects' }">Projekty</RouterLink>
      <RouterLink :to="{ name: 'debug' }">Debug</RouterLink>
    </nav>
  </header>
  <main><RouterView /></main>
</template>
