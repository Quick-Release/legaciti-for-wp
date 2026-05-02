import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite-plus';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Vite+ — https://viteplus.dev/ — unified dev/build (`vp`) + Oxlint/Oxfmt (`vp check`, Oxc https://oxc.rs/)
 */
export default defineConfig({
  root: __dirname,
  base: '',
  plugins: [react()],
  build: {
    outDir: path.resolve(__dirname, '../assets/dist'),
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: {
        dashboard: path.resolve(__dirname, 'src/dashboard.jsx'),
        people: path.resolve(__dirname, 'src/people.jsx'),
        settings: path.resolve(__dirname, 'src/settings.jsx'),
      },
    },
  },
  lint: {
    ignorePatterns: ['node_modules', '../assets/dist'],
  },
  fmt: {
    singleQuote: true,
  },
});
