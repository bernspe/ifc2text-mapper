// vite.config.ts
import { defineConfig } from 'vite'
import vue              from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      // '~/...' zeigt auf src/ – analog zu Nuxt-Konvention
      '~': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  // ── Dev-Server Proxy ──────────────────────────────────────────────────────
  // Im Entwicklungsmodus leitet Vite /api/* an den lokalen PHP-Server weiter.
  // Dadurch kein CORS-Problem und kein separater Proxy-Server nötig.
  //
  // Voraussetzung: PHP läuft lokal z.B. via
  //   php -S localhost:8080 -t .          (eingebaut, kein Apache nötig)
  //   oder MAMP / Laragon / Herd
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target:      'http://localhost:8080',
        changeOrigin: true,
        // Optional: Anfragen loggen für Debugging
        configure: (proxy) => {
          proxy.on('error',    (err) => console.error('[proxy error]', err))
          proxy.on('proxyReq', (_, req) => console.log('[proxy →]', req.url))
        },
      },
    },
  },

  // ── Build-Output ──────────────────────────────────────────────────────────
  // Statische Dateien landen in dist/ → auf PHP-Server deployen
  build: {
    outDir:   'dist',
    emptyOutDir: true,
  },
})
