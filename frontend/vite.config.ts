import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const environment = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [react()],
    build: {
      // The HK526 terminal's installed WebView uses Chromium 66.
      target: 'chrome66',
      cssTarget: 'chrome66',
    },
    server: {
      proxy: {
        '/api': environment.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8000',
      },
    },
  }
})
