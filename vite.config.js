import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/habitaciones/index.js',
                'resources/js/huespedes/index.js',
                'resources/js/reportes/index.js',
                'resources/js/reservas/index.js',
                'resources/js/usuarios/index.js',
            ],
            refresh: true,
        }),
    ],
});