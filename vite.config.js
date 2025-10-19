import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'resources/ts/scheduler/main.tsx',
                
            ],
            refresh: true,
        }),
    react(),
    ],
    build: {
      // ne base64-ozza be a fontokat
      assetsInlineLimit: 0,
      rollupOptions: {
        output: {
          assetFileNames: (assetInfo) => {
            if (/\.(woff2?|ttf|otf|eot)$/.test(assetInfo.name ?? '')) {
              return 'assets/fonts/[name][extname]'
            }
            return 'assets/[name][extname]'
          },
        },
      },
    },
    resolve: {
    alias: {
      vendor: path.resolve(process.cwd(), 'vendor'),
    },
  },
});
