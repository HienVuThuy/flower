import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';

/* Vite chỉ đóng gói tài nguyên nào được tham chiếu từ một entry. */
const imagesIn = (dir) =>
    fs
        .readdirSync(`resources/images/${dir}`)
        .filter((f) => /\.(jpe?g|png|webp)$/i.test(f))
        .map((f) => `resources/images/${dir}/${f}`);

const catalogImages = [...imagesIn('catalog'), ...imagesIn('hero')];

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/js/app.js',
                'resources/images/editorial/story-studio.jpg',
                ...catalogImages,
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
