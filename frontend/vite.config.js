import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
  },
  build: { outDir: 'dist' },
  // Variables exclusivas de Docker; el arranque nativo conserva sus valores.
  server: {
    watch: process.env.VITE_DOCKER_POLLING === 'true'
      ? { usePolling: true, interval: 300 }
      : undefined,
    hmr: process.env.VITE_HMR_CLIENT_PORT
      ? { clientPort: Number(process.env.VITE_HMR_CLIENT_PORT) }
      : undefined,
  },
})
