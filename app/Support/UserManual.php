<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SeatReleaser;
use Illuminate\Support\Facades\Storage;

/**
 * Isi manual pengguna per peran, sekaligus daftar tangkapan layar.
 *
 * Satu sumber untuk dua pemakai: halaman publik /panduan membaca teksnya,
 * manual:capture-screenshots membaca 'shot' untuk tahu halaman mana yang
 * harus dibuka Playwright dan sebagai siapa. Tambah langkah di sini, lalu
 * jalankan ulang perintah itu.
 *
 * Kunci 'shot':
 *   as      admin|operator|pengawas|peneliti (akun demo), guest, atau student
 *   path    jalur relatif; {demo}, {qr}, {slips}, {token_seat}, {token_scheduled}, {token_done}, {student_code} diisi saat tangkap
 *   mobile  viewport HP 390 px (sisi siswa); selain itu 1366 px
 *   steps   aksi Playwright sebelum memotret: [aksi, selektor, nilai?]
 */
final class UserManual
{
    public const DIRECTORY = 'manual';

    /** @return array<string, array<string, mixed>> */
    public static function roles(): array
    {
        return [
            'siswa' => [
                'label' => 'Siswa',
                'tagline' => 'Mengerjakan tes di HP sendiri, tanpa akun.',
                'access' => 'Tidak perlu akun. Cukup kode QR dari pengawas, atau slip QR bertoken delapan huruf yang dibagikan sekolah.',
                'sections' => [
                    [
                        'title' => 'Buka tes dari halaman depan',
                        'steps' => [
                            'Pindai kode QR yang disodorkan pengawas atau yang tercetak di slipmu. Tautan langsung membuka tes di browser HP.',
                            'Kalau kamera bermasalah, buka halaman depan C-ECO dan ketik token delapan huruf yang tertulis di bawah QR.',
                            'Tekan <strong>Buka tes</strong>.',
                        ],
                        'shot' => ['file' => 'siswa/01-token.png', 'as' => 'student', 'path' => '/', 'mobile' => true,
                            'steps' => [['fill', '#token', '{token_seat}']]],
                    ],
                    [
                        'title' => 'Kalau muncul "Tes belum dimulai"',
                        'steps' => [
                            'Slip QR boleh dibagikan sebelum hari-H, tetapi tes baru terbuka pada jam yang ditetapkan operator. Jamnya memakai waktu server, bukan jam HP.',
                            'Sebelum jam itu, halaman hanya menampilkan jam mulai. Tokenmu belum terpakai dan belum terikat ke HP mana pun.',
                            'Biarkan halaman terbuka: halaman memuat ulang sendiri dan langsung lanjut ke isian identitas begitu jamnya tiba.',
                        ],
                        'shot' => ['file' => 'siswa/02-belum-dimulai.png', 'as' => 'student', 'path' => '/t/{token_scheduled}', 'mobile' => true],
                    ],
                    [
                        'title' => 'Isi identitas',
                        'steps' => [
                            'Tulis nomor induk, nama lengkap, jenjang (X, XI, atau XII), dan kelas.',
                            'Periksa sekali lagi, lalu tekan <strong>Lanjut</strong>. Identitas mengikat token ke HP ini.',
                            'Satu token hanya untuk satu siswa. Kalau token dibuka di HP lain, layar menampilkan <strong>Token sudah dipakai</strong>. Jangan memakai token teman; kalau itu tokenmu sendiri dan HP-mu bermasalah, minta pengawas mengizinkan pindah HP.',
                        ],
                        'shot' => ['file' => 'siswa/03-identitas.png', 'as' => 'student', 'path' => '/', 'mobile' => true,
                            // Mulai lagi dari halaman depan: tangkapan sebelumnya membuka ruang lain.
                            'steps' => [['fill', '#token', '{token_seat}'], ['click', 'button.primary']]],
                    ],
                    [
                        'title' => 'Baca petunjuk dan beri persetujuan',
                        'steps' => [
                            'Baca aturan singkat: soal tampil satu per satu dan tidak bisa kembali ke soal sebelumnya.',
                            'Centang persetujuan, lalu tekan <strong>Mulai</strong>.',
                        ],
                        'shot' => ['file' => 'siswa/04-persetujuan.png', 'as' => 'student', 'mobile' => true,
                            'steps' => [
                                ['fill', '#student_code', '{student_code}'],
                                ['fill', '#display_name', 'Siswa Contoh'],
                                ['select', '#grade', 'XI'],
                                ['fill', '#class_name', 'IPS 1'],
                                ['click', 'button[type=submit]'],
                            ]],
                    ],
                    [
                        'title' => 'Coba soal latihan',
                        'steps' => [
                            'Soal latihan tidak dinilai. Pakai untuk mencoba cara memilih jawaban.',
                            'Pilih satu jawaban, lalu tekan <strong>Lanjut ke tes</strong>.',
                        ],
                        'shot' => ['file' => 'siswa/05-latihan.png', 'as' => 'student', 'mobile' => true,
                            'steps' => [['check', 'input[name=consent]'], ['click', 'button[type=submit]']]],
                    ],
                    [
                        'title' => 'Kerjakan soal satu per satu',
                        'steps' => [
                            'Baca soal sampai habis; tabel yang lebar bisa digeser ke samping.',
                            'Sentuh satu pilihan. Tombol <strong>Lanjut</strong> baru aktif setelah ada pilihan.',
                            'Jawaban terkirim saat menekan Lanjut. Kalau sinyal putus, jawaban dikirim ulang otomatis; jangan tutup halaman.',
                            'Jumlah soal bisa berbeda antarsiswa: tes berhenti sendiri setelah kemampuanmu cukup terukur.',
                        ],
                        'shot' => ['file' => 'siswa/06-soal.png', 'as' => 'student', 'mobile' => true,
                            'steps' => [
                                ['click', 'input[name=latihan] >> nth=0'],
                                ['click', 'button:has-text("Lanjut ke tes")'],
                                ['waitFor', '#butir'],
                                ['click', '#butir input[name=option] >> nth=1'],
                            ]],
                    ],
                    [
                        'title' => 'Selesai',
                        'steps' => [
                            'Halaman selesai muncul sendiri. Tunjukkan ke pengawas bila diminta.',
                            'Kalau HP mati di tengah tes, buka lagi tautan yang sama di HP yang sama: tes berlanjut dari soal terakhir.',
                            'Kalau harus pindah ke HP lain, angkat tangan. Setelah pengawas mengizinkan pindah HP, buka token yang sama di HP baru dalam '.SeatReleaser::RELEASE_MINUTES.' menit; tes berlanjut dari soal terakhir.',
                        ],
                        'shot' => ['file' => 'siswa/07-selesai.png', 'as' => 'student', 'path' => '/t/{token_done}', 'mobile' => true],
                    ],
                ],
            ],
            'pengawas' => [
                'label' => 'Pengawas',
                'tagline' => 'Menjaga satu ruang dan membagikan kode QR ke siswa: lewat kartu QR di layar atau slip cetak.',
                'access' => 'Akun dibuat admin. Masuk dari tautan <em>Masuk panel</em> di halaman depan.',
                'sections' => [
                    [
                        'title' => 'Masuk panel',
                        'steps' => [
                            'Di halaman depan, tekan <strong>Masuk panel</strong>.',
                            'Isi email dan kata sandi dari admin, lalu tekan <strong>Masuk</strong>.',
                        ],
                        'shot' => ['file' => 'pengawas/01-masuk.png', 'as' => 'guest', 'path' => '/admin/login'],
                    ],
                    [
                        'title' => 'Lihat ruang yang Anda jaga',
                        'steps' => [
                            'Buka menu <strong>Jadwal</strong>. Baris yang tampil adalah ruang dengan nama Anda sebagai pengawas.',
                            'Perhatikan jam mulai: kartu QR baru terbuka setelah jam server melewati jam itu.',
                            'Setiap baris punya dua tombol: <strong>Kartu QR</strong> (satu QR bergilir di layar) dan <strong>Cetak QR</strong> (slip kertas per kursi).',
                        ],
                        'shot' => ['file' => 'pengawas/02-jadwal.png', 'as' => 'pengawas', 'path' => '/admin/exam-groups'],
                    ],
                    [
                        'title' => 'Sodorkan kartu QR',
                        'steps' => [
                            'Buka <strong>Kartu QR</strong> ruang Anda di laptop atau tablet yang menghadap siswa.',
                            'Satu QR untuk satu siswa. Kartu berganti sendiri begitu HP siswa membuka tautannya.',
                            'Kalau pemindaian lambat, ketuk <strong>Token berikutnya</strong> untuk langsung menampilkan QR siswa berikutnya tanpa menunggu.',
                            'Siswa yang kameranya bermasalah boleh mengetik token yang tercetak di bawah QR.',
                            'Token terikat ke HP pertama yang mengisi identitas. Siswa yang melihat <strong>Token sudah dipakai</strong> diberi QR berikutnya, bukan token temannya.',
                        ],
                        'shot' => ['file' => 'pengawas/03-kartu-qr.png', 'as' => 'pengawas', 'path' => '{qr}'],
                    ],
                    [
                        'title' => 'Atau bagikan slip QR cetak',
                        'steps' => [
                            'Tekan <strong>Cetak QR</strong> di baris ruang, lalu <strong>Cetak</strong>. Satu lembar A4 memuat delapan slip, satu slip per kursi.',
                            'Slip boleh dicetak dan dibagikan kapan saja sebelum hari-H. Siswa yang memindainya sebelum jam mulai hanya melihat "Tes belum dimulai"; kursinya tetap kosong.',
                            'Di satu ruang, pakai salah satu saja: slip cetak <em>atau</em> Kartu QR. Kartu QR menampilkan kursi kosong berikutnya, yang mungkin sudah tercetak di slip siswa lain.',
                            'Slip kursi yang sudah dibuka tampil pudar saat dicetak ulang. Jangan dibagikan lagi.',
                        ],
                        'shot' => ['file' => 'pengawas/04-slip-qr.png', 'as' => 'pengawas', 'path' => '{slips}'],
                    ],
                    [
                        'title' => 'Pantau kemajuan ruang',
                        'steps' => [
                            'Menu <strong>Monitor</strong> menunjukkan siswa di ruang yang Anda jaga: status, jumlah butir, dan kabar terakhir.',
                            'Cari siswa lewat kotak pencarian: nama, NIS, kelas, atau token di slip/QR (boleh diketik dengan spasi, misalnya <code>ABCD EFGH</code>). Filter <strong>Ruang</strong>, <strong>Status</strong>, dan <strong>Tersendat</strong> mempersempit daftar.',
                            'Siswa yang lama tidak terlihat biasanya kehabisan kuota atau layarnya mati; datangi dan minta membuka tautan yang sama.',
                        ],
                        'shot' => ['file' => 'pengawas/05-monitor.png', 'as' => 'pengawas', 'path' => '/admin/monitor',
                            'steps' => [
                                ['fill', 'input[placeholder="Nama, NIS, kelas, atau token"]', 'Putri'],
                                ['waitFor', 'td:has-text("Putri Ayu")'],
                                ['scrollTo', 'text=Cari nama siswa'],
                            ]],
                    ],
                    [
                        'title' => 'Siswa perlu pindah HP',
                        'steps' => [
                            'Kalau HP siswa mati atau rusak di tengah tes, HP lain yang membuka tokennya akan melihat <strong>Token sudah dipakai</strong>. Ini disengaja agar token tidak dipakai orang lain.',
                            'Pastikan siswanya benar ada di depan Anda. Di Monitor, cari namanya atau tokennya, lalu tekan <strong>Pindah HP</strong> di awal barisnya, lalu <strong>Izinkan pindah</strong>. Alasan boleh diisi.',
                            'Siswa membuka tautan atau token yang sama di HP baru dalam '.SeatReleaser::RELEASE_MINUTES.' menit. Tes berlanjut dari soal terakhir; jawaban sebelumnya tidak hilang.',
                            'Begitu HP baru terbuka, token terkunci ke HP itu dan HP lama ditolak. Kalau izin tidak dipakai dalam '.SeatReleaser::RELEASE_MINUTES.' menit, token kembali ke HP lama.',
                            'Kolom <strong>HP</strong> menunjukkan Terkunci, Belum terikat, atau Boleh pindah s.d. jam tertentu. Setiap izin tercatat: siapa, kapan, dan alasannya.',
                        ],
                        'shot' => ['file' => 'pengawas/06-pindah-hp.png', 'as' => 'pengawas', 'path' => '/admin/monitor',
                            'steps' => [
                                ['click', 'button:has-text("Pindah HP")'],
                                ['waitFor', 'text=pindah HP?'],
                                ['scrollTo', 'text=Cari nama siswa'],
                            ]],
                    ],
                ],
            ],
            'operator' => [
                'label' => 'Operator',
                'tagline' => 'Menyiapkan sekolah, peserta, paket ujian, jadwal ruang, dan slip QR.',
                'access' => 'Akun dibuat admin. Akun operator dari simulasi demo hanya bisa melihat: sekolah, peserta, paket ujian, dan pembuatan jadwal asli tertutup baginya.',
                'sections' => [
                    [
                        'title' => 'Masuk dan buka Monitor',
                        'steps' => [
                            'Masuk dari <strong>Masuk panel</strong> di halaman depan. Halaman pertama adalah Monitor.',
                            'Kartu di atas merangkum sesi berjalan, selesai, dan laju galat; tabel di bawah memperbarui diri sendiri.',
                        ],
                        'shot' => ['file' => 'operator/01-monitor.png', 'as' => 'operator', 'path' => '/admin/monitor'],
                    ],
                    [
                        'title' => 'Daftarkan sekolah',
                        'steps' => [
                            'Buka menu <strong>Sekolah</strong>, lalu <strong>Buat</strong> untuk satu sekolah atau <strong>Impor</strong> untuk banyak sekaligus.',
                            'Nama sekolah dipakai di slip dan ekspor; tulis sesuai data resmi.',
                        ],
                        'shot' => null,
                        // Akun simulasi (termasuk admin demo) sengaja tidak boleh membuka data siswa asli.
                        'shot_note' => 'Menu Sekolah memuat data siswa asli, jadi tertutup bagi semua akun simulasi dan tidak dipotret.',
                    ],
                    [
                        'title' => 'Masukkan peserta',
                        'steps' => [
                            'Buka menu <strong>Peserta</strong>. Impor berkas CSV untuk satu kelas penuh, atau tambah satu per satu.',
                            'Peserta yang datang lewat QR ruang tidak perlu didaftarkan dulu: kursinya terisi saat siswa mengisi identitas.',
                        ],
                        'shot' => null,
                        // Akun simulasi (termasuk admin demo) sengaja tidak boleh membuka data siswa asli.
                        'shot_note' => 'Menu Peserta memuat data siswa asli, jadi tertutup bagi semua akun simulasi dan tidak dipotret.',
                    ],
                    [
                        'title' => 'Siapkan paket ujian',
                        'steps' => [
                            'Buka menu <strong>Paket Ujian</strong>, lalu <strong>Buat</strong>.',
                            'Bawaannya sama dengan simulasi: <strong>Gabungan X + XI + XII dengan porsi</strong>. Isi persen tiap jenjang sampai 100 (0% berarti jenjang itu tidak dipakai) dan jumlah butir paket; hitungan butir per jenjang tampil di bawahnya.',
                            'Pilih <strong>Seluruh butir satu bank jenjang</strong> hanya bila paket memang untuk satu jenjang saja.',
                            'Paket dipakai jadwal ruang; ubah paket sebelum jam mulai, bukan saat tes berjalan.',
                        ],
                        'shot' => null,
                        'shot_note' => 'Menu ini hanya terbuka untuk operator tes asli, jadi tidak bisa dipotret dari akun demo.',
                    ],
                    [
                        'title' => 'Atur jadwal dan ruang',
                        'steps' => [
                            'Buka menu <strong>Jadwal</strong>, lalu <strong>Buat</strong>.',
                            'Isi nama ruang, jam mulai, kapasitas kursi, paket ujian, dan pengawasnya.',
                            'Setelah disimpan, kursi dan token terbit sendiri; pengawas membuka Kartu QR dari barisnya.',
                            'Sampai jam mulai, jadwal masih bisa disunting: ganti paket ujian (semua kursi ikut paket baru; token dan slip tetap berlaku), ubah jam, atau ubah jumlah kursi.',
                            'Jadwal yang batal dihapus dengan <strong>Hapus jadwal</strong>. Kursi dan tokennya ikut hilang, tidak lagi terhitung di Monitor, dan slip yang sudah dicetak tidak berlaku.',
                            'Begitu jam mulai lewat, paket terkunci. Jadwal yang lewat tetapi tidak dipakai siswa sama sekali tetap bisa dihapus; begitu ada satu kursi dipakai, jadwal tidak bisa dihapus supaya jawaban siswa tidak hilang.',
                        ],
                        'shot' => ['file' => 'operator/02-jadwal.png', 'as' => 'operator', 'path' => '/admin/exam-groups'],
                        'shot_note' => 'Tangkapan dari akun operator demo, karena itu kosong: daftar ini hanya memuat jadwal tes asli (ruang simulasi ada di menu Simulasi milik admin), dan tombol Buat hanya muncul untuk operator tes asli.',
                    ],
                    [
                        'title' => 'Cetak slip QR sebelum hari-H',
                        'steps' => [
                            'Di menu <strong>Jadwal</strong>, tekan <strong>Cetak QR</strong> pada baris ruang. Halaman cetak terbuka di tab baru; tekan <strong>Cetak</strong>.',
                            'Satu lembar A4 memuat delapan slip: nomor kursi, sekolah dan ruang, token, QR, serta jam mulai.',
                            'Mencetak tidak membuka kursi. Sebelum jam mulai, siswa yang memindai slip hanya melihat "Tes belum dimulai" dan tidak bisa mengisi identitas atau mengerjakan tes. Tepat pada jam mulai (waktu server) slip yang sama langsung bisa dipakai.',
                            'Simpan slip di tempat aman sampai dibagikan: satu slip untuk satu siswa, dan token terkunci ke HP pertama yang mengisi identitas.',
                            'Beri tahu pengawas ruang itu agar tidak memakai Kartu QR bersamaan dengan slip cetak.',
                        ],
                        'shot' => ['file' => 'operator/03-slip-qr.png', 'as' => 'operator', 'path' => '{slips}'],
                        'shot_note' => 'Contoh dari ruang simulasi demo yang dijadwalkan besok pagi.',
                    ],
                    [
                        'title' => 'Ekspor data',
                        'steps' => [
                            'Di halaman Monitor, tekan <strong>Ekspor CSV</strong> di kanan atas.',
                            'Berkas CSV berisi sesi, jawaban per butir, peserta, dan peristiwa koneksi.',
                        ],
                        'shot' => ['file' => 'operator/04-ekspor.png', 'as' => 'operator', 'path' => '/admin/monitor', 'clip' => 'header'],
                    ],
                ],
            ],
            'peneliti' => [
                'label' => 'Peneliti',
                'tagline' => 'Membaca bank soal, memantau tes, dan mengambil data untuk analisis.',
                'access' => 'Akun dibuat admin. Peneliti hanya membaca; butir tidak bisa diubah dari akun ini.',
                'sections' => [
                    [
                        'title' => 'Pantau tes di Monitor',
                        'steps' => [
                            'Masuk panel; halaman pertama adalah Monitor.',
                            'Sebaran kemajuan dan jenis koneksi membantu menilai apakah hambatan datang dari jaringan atau dari soal.',
                        ],
                        'shot' => ['file' => 'peneliti/01-monitor.png', 'as' => 'peneliti', 'path' => '/admin/monitor'],
                    ],
                    [
                        'title' => 'Telusuri bank soal',
                        'steps' => [
                            'Buka menu <strong>Bank Soal</strong>. Setiap butir dikenali dari kodenya (misalnya XI-27), bukan nomor urut.',
                            'Pakai filter jenjang dan dimensi untuk melihat sebaran butir.',
                        ],
                        'shot' => ['file' => 'peneliti/02-bank-soal.png', 'as' => 'peneliti', 'path' => '/admin/items'],
                    ],
                    [
                        'title' => 'Lihat paket soal per jenjang',
                        'steps' => [
                            'Menu <strong>Paket Soal</strong> merangkum bank per jenjang dan jumlah butirnya.',
                        ],
                        'shot' => ['file' => 'peneliti/03-paket-soal.png', 'as' => 'peneliti', 'path' => '/admin/item-banks'],
                    ],
                    [
                        'title' => 'Ambil data untuk analisis',
                        'steps' => [
                            'Di Monitor, tekan <strong>Ekspor CSV</strong>. Setiap jawaban membawa versi parameter butir yang dipakai saat itu, jadi data bisa dianalisis ulang setelah kalibrasi final.',
                            'Sesi dari gelombang simulasi ikut terekspor. Pisahkan memakai kolom test_config_id: paket simulasi bernama "Simulasi #…" (tanyakan nomornya ke operator).',
                        ],
                        'shot' => ['file' => 'peneliti/04-ekspor.png', 'as' => 'peneliti', 'path' => '/admin/monitor', 'clip' => 'header'],
                    ],
                ],
            ],
            'admin' => [
                'label' => 'Admin',
                'tagline' => 'Mengelola akun, bank soal, dan simulasi.',
                'access' => 'Akun admin pertama dibuat saat pemasangan (php artisan make:filament-user).',
                'sections' => [
                    [
                        'title' => 'Masuk panel',
                        'steps' => [
                            'Tekan <strong>Masuk panel</strong> di halaman depan, lalu isi email dan kata sandi admin.',
                        ],
                        'shot' => ['file' => 'admin/01-masuk.png', 'as' => 'guest', 'path' => '/admin/login'],
                    ],
                    [
                        'title' => 'Kelola akun panel',
                        'steps' => [
                            'Buka menu <strong>Pengguna</strong>. Buat akun operator, pengawas, atau peneliti; atau impor banyak akun dari CSV.',
                            'Nonaktifkan akun yang tidak lagi bertugas alih-alih menghapusnya, supaya jejaknya tetap ada.',
                        ],
                        'shot' => ['file' => 'admin/02-pengguna.png', 'as' => 'admin', 'path' => '/admin/users'],
                    ],
                    [
                        'title' => 'Periksa dan perbaiki butir',
                        'steps' => [
                            'Buka menu <strong>Bank Soal</strong>, cari butir berdasarkan kode, lalu sunting stem atau opsinya.',
                            'Parameter butir tidak diubah dari sini: kalibrasi baru selalu menjadi versi baru.',
                        ],
                        'shot' => ['file' => 'admin/03-bank-soal.png', 'as' => 'admin', 'path' => '/admin/items'],
                    ],
                    [
                        'title' => 'Unggah soal baru',
                        'steps' => [
                            'Buka menu <strong>Unggah soal</strong>, pilih berkas JSON hasil ekstraksi, lalu ikuti empat syarat di layar.',
                            'Butir dengan kode yang sudah ada tidak ditimpa.',
                        ],
                        'shot' => ['file' => 'admin/04-unggah-soal.png', 'as' => 'admin', 'path' => '/admin/unggah-soal'],
                    ],
                    [
                        'title' => 'Buat simulasi demo',
                        'steps' => [
                            'Buka menu <strong>Simulasi</strong>. Baris status di atas menunjukkan apakah simulasi demo sedang aktif.',
                            'Tekan <strong>Buat Simulasi</strong>, lalu konfirmasi. Sistem menyiapkan akun demo untuk setiap peran, dua ruang, dan sesi tes contoh.',
                            'Menekan tombol itu lagi tidak membuat duplikat: selama demo aktif, tombolnya diganti <strong>Hapus Simulasi</strong>.',
                        ],
                        'shot' => ['file' => 'admin/05-simulasi.png', 'as' => 'admin', 'path' => '/admin/exam-simulations'],
                    ],
                    [
                        'title' => 'Bagikan akun demo',
                        'steps' => [
                            'Buka lembar gelombang <em>Simulasi demo</em>. Daftar jaga berisi email akun demo per peran dan sandi bersamanya.',
                            'Sandi dibuat acak setiap kali simulasi dibuat, dan semua akun demo hilang saat simulasi dihapus.',
                        ],
                        'shot' => ['file' => 'admin/06-akun-demo.png', 'as' => 'admin', 'path' => '{demo}', 'mask' => '.ceco-roll-passphrase'],
                    ],
                    [
                        'title' => 'Hapus simulasi demo',
                        'steps' => [
                            'Tekan <strong>Hapus Simulasi</strong> dan konfirmasi. Yang terhapus hanya baris yang dibuat simulasi: akun demo, ruang, kursi, jawaban, dan paketnya.',
                            'Bank soal, parameter, peserta asli, dan gelombang yang Anda tulis sendiri tidak tersentuh.',
                            'Dari terminal: <code>php artisan simulation:generate</code>, <code>simulation:status</code>, dan <code>simulation:reset</code>.',
                        ],
                        'shot' => ['file' => 'admin/07-hapus-simulasi.png', 'as' => 'admin', 'path' => '/admin/exam-simulations',
                            'steps' => [['click', 'button:has-text("Hapus Simulasi")'], ['waitFor', 'text=Hapus simulasi demo?']]],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function role(string $key): ?array
    {
        return self::roles()[$key] ?? null;
    }

    /** @return list<array<string, mixed>> semua spesifikasi tangkapan, urut sesuai manual */
    public static function shots(): array
    {
        $shots = [];

        foreach (self::roles() as $role) {
            foreach ($role['sections'] as $section) {
                if (($section['shot'] ?? null) !== null) {
                    $shots[] = $section['shot'];
                }
            }
        }

        return $shots;
    }

    public static function path(string $file): string
    {
        return self::DIRECTORY.'/'.$file;
    }

    public static function exists(string $file): bool
    {
        return Storage::disk('manual')->exists($file);
    }

    public static function url(string $file): string
    {
        $version = @filemtime(Storage::disk('manual')->path($file)) ?: 0;

        return asset(self::path($file)).'?v='.$version;
    }
}
