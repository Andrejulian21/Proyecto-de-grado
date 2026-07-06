import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * Vite configuration for the UNAB project-grade SPA.
 *
 * - laravel-vite-plugin: discovers the entry points under
 *   resources/ and emits hashed assets into public/build.
 *   The `refresh: true` option enables Hot Module Replacement
 *   for Blade views (used for the auth stub in app.blade.php).
 * - @vitejs/plugin-react: Fast Refresh + automatic JSX runtime.
 *   Required for the TSX entry point.
 * - server.proxy: /api/*, /sanctum/*, /auth/* forwarded to the
 *   Laravel dev server. This lets the Vite SPA at :5173 talk to
 *   the Laravel API at :8000 without CORS preflight noise during
 *   dev (still backed by the real CORS config in production).
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    server: {
        port: 5173,
        host: 'localhost',
        strictPort: true,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
            '/sanctum': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
            '/auth': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
});
