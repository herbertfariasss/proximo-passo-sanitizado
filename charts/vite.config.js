import { defineConfig } from 'vite';

export default defineConfig({
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  build: {
    outDir: '../public/js/admin-charts',
    emptyOutDir: true,
    lib: { entry: 'src/main.jsx', formats: ['es'], fileName: 'charts' }
  }
});
