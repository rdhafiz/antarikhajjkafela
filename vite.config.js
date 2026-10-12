import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/admin/main.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@admin': fileURLToPath(new URL('./resources/admin', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            output: {
                // Keep the public site's dependencies (Alpine) apart from the
                // admin SPA's, so visitors never download Vue.
                manualChunks(id) {
                    if (!id.includes('node_modules')) return undefined;
                    if (id.includes('alpinejs')) return 'site-vendor';
                    if (id.includes('cropperjs')) return 'cropper';
                    // Loaded only when a phone or date field is shown.
                    if (id.includes('intl-tel-input')) return id.includes('utils') ? 'phone-utils' : 'phone-input';
                    if (id.includes('flatpickr')) return 'date-picker';
                    return 'admin-vendor';
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
