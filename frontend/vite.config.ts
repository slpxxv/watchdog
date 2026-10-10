/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Dev only: Vite serves the SPA and proxies the backend (`symfony serve`, see .symfony.local.yaml),
// so the browser sees one origin: session cookie, no CORS.
const backend = 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': backend,
      '/_profiler': backend,
      '/_wdt': backend,
    },
  },
})
