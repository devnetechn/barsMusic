import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: 'all',
    proxy: {
      '/bars/api': {
        target: 'http://localhost',
        changeOrigin: true
      },
      '/bars/music': {
        target: 'http://localhost',
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
