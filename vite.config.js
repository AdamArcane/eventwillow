import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Under DDEV the dev server runs in the container, so the browser must reach it
// through the project's HTTPS URL and the port exposed in .ddev/config.yaml.
const ddevUrl = process.env.IS_DDEV_PROJECT === 'true' && process.env.DDEV_PRIMARY_URL
    ? process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, '')
    : null;

export default defineConfig({
    server: {
        ...(ddevUrl ? { host: '0.0.0.0', port: 5173, strictPort: true, origin: `${ddevUrl}:5173` } : {}),
        /*
        hmr: {
            host: "192.168.10.10",
        },
        host: "192.168.10.10",
        */
        cors: {
            origin: '*',
        },
        watch: {
            // Native file notifications avoid continuously polling the project.
            usePolling: false,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/marketing.js',
                'resources/js/marketing-home.js',
                'resources/js/docs.js',
                'resources/js/newsletter-builder.js',
                'resources/js/color-picker.js',
                'resources/js/list-animation-picker.js',
                'resources/js/seating-designer.js',
                'resources/js/seating-picker.js',
                'resources/js/seating-box-office.js',
                //'resources/js/leaflet.js',
                'resources/css/app.css',
                'resources/css/marketing-app.css',
                'resources/css/marketing.css',
                'resources/css/docs.css',
                //'resources/css/leaflet.css',
            ],
            refresh: true,
        }),
        vue(),
    ],
});
