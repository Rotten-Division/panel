import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import { globSync } from 'glob';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/console.css',
                'resources/css/monaco-editor.css',
                'resources/css/filament/app/theme.css',
                'resources/css/components/overview/overview.css',
                ...globSync('resources/js/**/*.js'),

                ...globSync('plugins/*/resources/css/**/*.css'),
                ...globSync('plugins/*/resources/js/**/*.js'),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
