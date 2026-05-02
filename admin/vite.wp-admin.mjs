import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/** One admin screen per build so Rollup can inline everything into a classic script (no ESM chunks). */
const entries = {
  dashboard: path.resolve(__dirname, 'src/dashboard.jsx'),
  people: path.resolve(__dirname, 'src/people.jsx'),
  settings: path.resolve(__dirname, 'src/settings.jsx'),
};

const watchMode = process.env.LEGACITI_VITE_WATCH === '1';

export default defineConfig(({ mode }) => {
  const key = mode && entries[mode] ? mode : 'settings';
  return {
    root: __dirname,
    base: '',
    plugins: [react()],
    build: {
      // Parallel `vite build --watch` runs must not empty dist from another process.
      emptyOutDir: ! watchMode && key === 'dashboard',
      outDir: path.resolve(__dirname, '../assets/dist'),
      manifest: false,
      cssCodeSplit: false,
      rollupOptions: {
        input: entries[key],
        output: {
          format: 'iife',
          inlineDynamicImports: true,
          entryFileNames: `assets/legaciti-${key}.js`,
          assetFileNames: `assets/legaciti-${key}.[ext]`,
        },
      },
    },
  };
});
