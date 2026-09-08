<?php

declare(strict_types=1);

return [

    /*
    | Penimpa global mode tes (aturan R10). Bila diisi 'linear', seluruh sesi
    | BARU berjalan linear apa pun isi test_configs. Sesi yang sedang berjalan
    | tidak berubah di tengah jalan.
    */
    'mode' => env('CAT_MODE', 'adaptive'),

    /*
    | SEMENTARA. Rubrik "Kategori Tingkat Kreativitas Ekonomi" yang sudah ada
    | belum tersedia di repositori ini, jadi ambang di bawah memakai konvensi
    | skor-T biasa. Ganti dengan ambang dari rubrik asli sebelum hasil dipakai
    | untuk pelaporan ke sekolah atau publikasi.
    |
    | Dibaca dari atas: kategori pertama yang ambang bawahnya terpenuhi menang.
    */
    'categories' => [
        ['min_t' => 65.0, 'label' => 'Sangat Kreatif'],
        ['min_t' => 55.0, 'label' => 'Kreatif'],
        ['min_t' => 45.0, 'label' => 'Cukup Kreatif'],
        ['min_t' => 35.0, 'label' => 'Kurang Kreatif'],
        ['min_t' => 0.0, 'label' => 'Sangat Kurang Kreatif'],
    ],

    'categories_are_provisional' => true,

    /*
    | Dump basis data terjadwal tiap 15 menit (routes/console.php). Dimatikan
    | secara bawaan supaya mesin pengembangan tidak menumpuk berkas; dinyalakan
    | pada server pelaksanaan.
    */
    'backup' => [
        'enabled' => (bool) env('CAT_BACKUP_ENABLED', false),
        'keep' => (int) env('CAT_BACKUP_KEEP', 48),
    ],

];
