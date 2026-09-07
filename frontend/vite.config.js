import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { vitePluginForArco } from '@arco-plugins/vite-vue'

export default defineConfig({
  plugins: [
    vue(),
    vitePluginForArco({
      theme: '@arco-themes/vue-tycdn',
      style: 'css',
    }),
  ],
  server: {
    host: '127.0.0.1',
    port: 5177,
    strictPort: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8013',
        changeOrigin: true,
      },
      '/sanctum': {
        target: 'http://127.0.0.1:8013',
        changeOrigin: true,
      },
    },
  },
})
