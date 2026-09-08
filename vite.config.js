import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // Bundel sisi siswa dipisah dari app.*: ia tidak boleh ikut membawa
            // Tailwind atau apa pun milik halaman lain. Anggaran R1 dihitung
            // dari berkas-berkas student.* saja.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/site.css',
                'resources/css/admin-brand.css',
                'resources/css/student.css',
                'resources/js/student.js',
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
