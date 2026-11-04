import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// In development the React app runs on :5173 and forwards API calls and the
// shared photos to the Go API (default :8080), so the browser sees one origin.
const api = process.env.API_URL ?? 'http://localhost:8080'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    proxy: {
      '/api': { target: api, changeOrigin: true },
      '/images': { target: api, changeOrigin: true },
    },
  },
  build: {
    // three.js is split into its own chunk and only loads on desktop package pages.
    chunkSizeWarningLimit: 1200,
  },
})
