import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// 开发环境把 /api 代理到 Laravel，避免跨域；生产环境交给 Nginx 反向代理
export default defineConfig({
  plugins: [vue()],
  build: {
    // Element Plus 全量引入体积较大，Demo 放宽告警阈值
    chunkSizeWarningLimit: 1200,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
