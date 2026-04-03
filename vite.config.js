import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: true,
    proxy: {
      '/bars/api': {
        target: 'http://127.0.0.1',
        changeOrigin: true,
        timeout: 120000
      },
      '/bars/music': {
        target: 'http://127.0.0.1',
        changeOrigin: true
      },
      '/bars/covers': {
        target: 'http://127.0.0.1',
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets'
  },
  base: './'
})
