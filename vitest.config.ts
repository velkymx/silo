import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        include: ['tests/js/**/*.spec.ts'],
        setupFiles: ['tests/js/setup.ts'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.{ts,vue}'],
            reporter: ['text', 'html'],
        },
    },
});
