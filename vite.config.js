import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Laptop operator lama (Firefox ESR 115 / Chrome 109): jangan pakai sintaks terlalu baru.
    build: {
        target: ['es2018', 'firefox115', 'chrome109'],
        cssTarget: ['firefox115', 'chrome109'],
    },
});
