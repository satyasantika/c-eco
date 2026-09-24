# Ringkasan: Simulasi Demo & Manual Pengguna

Branch: `feature/simulasi-dan-manual-panduan` · 24 September 2026

Dua fitur dibangun di atas mekanisme gelombang simulasi yang sudah ada
(`ExamSimulation` + `ExamSimulationBuilder` + `ExamSimulationPurger`), bukan
sistem pelacak kedua yang berjalan sejajar.

## Commit

| Commit | Isi |
|---|---|
| `db3367d` | test(seed): bekukan jam sebelum jadwal preset simulasi — perbaikan tes yang **sudah gagal sebelum pekerjaan ini** (tanggal preset 21 Sep sudah lewat) |
| `dbf179d` | feat(simulasi): simulasi demo sekali tombol, akun tiap peran, bisa dihapus utuh |
| `5340de1` | feat(panduan): manual pengguna publik per peran dengan tangkapan layar asli |
| (commit terakhir) | docs: berkas ringkasan ini, di-push menyusul dengan cara yang sama |

## Status push

**Berhasil.** `git push origin feature/simulasi-dan-manual-panduan` (tanpa force)
membuat branch baru di `git@github.com:satyasantika/c-eco.git`. Yang ada di
branch itu dan belum ada di `main` hanya tiga commit di atas. Workflow deploy
hanya terpicu oleh push ke `main`, jadi push ini tidak men-deploy apa pun.
PR bisa dibuat di
https://github.com/satyasantika/c-eco/pull/new/feature/simulasi-dan-manual-panduan
(belum dibuat, belum di-merge).

## Peran di sistem

`App\Enums\UserRole`: **admin, operator, pengawas, peneliti**. Tidak ada
"superadmin"; admin adalah peran tertinggi. **Siswa** tidak punya akun (masuk
lewat token `/t/{token}`), tetapi tetap diberi simulasi dan manual sendiri.

---

## 1. Fitur simulasi demo

### Cara kerja

- Kolom baru `exam_simulations.is_demo` menandai gelombang demo. Setiap baris
  yang dibuat demo (akun, sekolah, paket ujian, rombongan, kursi, peserta,
  sesi, jawaban) terikat ke `exam_simulation_id` gelombang itu, jadi hapusnya
  memakai `ExamSimulationPurger` yang sudah ada.
- Isi demo: 24 kursi di 2 ruang. Kira-kira separuh sesi **selesai**,
  seperempat **sedang mengerjakan**, sisanya **menunggu** dipindai. Jawaban
  dimasukkan lewat `CatSession` asli dengan kemampuan siswa acak dan peluang
  benar dari model IRT butir. Jadi `session_items`, theta/SE, eksposur,
  monitor, dan ekspor terisi seperti tes nyata, termasuk `item_parameter_id`
  (R4).
- Akun demo: 1 admin, 1 operator, 2 pengawas, 1 peneliti, dengan email
  `sim<id>.<ad|op|pw|pn>NN@c-eco.test`. Sandi **acak setiap kali demo dibuat**
  dan hanya tampil di lembar gelombang (khusus admin) serta keluaran CLI.
- **Idempoten:** kalau demo sudah aktif, generate mengembalikan demo itu tanpa
  membuat duplikat. Reset tanpa demo aktif tidak melakukan apa-apa. Kunci
  cache mencegah klik ganda atau CLI dan panel berjalan bersamaan.
- **Yang tidak tersentuh:** bank soal, parameter, peserta dan sesi asli, akun
  asli, dan gelombang simulasi yang ditulis admin sendiri (tanpa `is_demo`).
  Semua ini diuji di `DemoSimulationTest` dan diverifikasi manual (lihat
  bawah).

### Trigger dari UI (hanya admin)

1. Masuk panel sebagai admin → menu **Simulasi**.
2. Baris status di atas denah menunjukkan *Simulasi demo aktif / tidak aktif*.
3. **Buat Simulasi** → konfirmasi. Tombol ini hanya tampil kalau demo tidak
   aktif.
4. **Hapus Simulasi** → konfirmasi. Tombol ini hanya tampil kalau demo aktif.
5. Tautan *Lihat akun dan sandi* membuka lembar gelombang demo.

Akses dibatasi Gate `manage-simulation` (= `User::canManageExamSimulations()`,
yaitu admin). Operator, pengawas, dan peneliti mendapat 403 di halaman itu.

### Trigger dari CLI

```bash
php artisan simulation:generate   # buat demo; kalau sudah aktif, cetak statusnya saja
php artisan simulation:status     # aktif/tidak, jumlah sesi, akun + sandi
php artisan simulation:reset      # hapus demo; kalau tidak ada, tidak berbuat apa-apa
```

Lokal (Docker): `docker exec c-eco-php php artisan simulation:generate`.

### Verifikasi idempoten (dijalankan 24 Sep 2026, DB lokal MySQL)

```
before: {"users":["admin@c-eco.tech"],"items":120,"params":120,"sims":0,"sessions":0}
simulation:generate exit=0 :: Simulasi demo dibuat: Simulasi demo · 24 siswa · 2 kelas.
simulation:generate exit=0 :: Simulasi demo sudah aktif sejak ... Tidak ada yang dibuat ulang.
simulation:reset    exit=0 :: Simulasi demo dihapus (1 gelombang). Bank soal dan tes asli tidak berubah.
simulation:reset    exit=0 :: Tidak ada simulasi demo aktif. Tidak ada yang dihapus.
simulation:generate exit=0 :: Simulasi demo dibuat: ...
simulation:reset    exit=0 :: Simulasi demo dihapus (1 gelombang). ...
after:  {"users":["admin@c-eco.tech"],"items":120,"params":120,"sims":0,"sessions":0}
```

### Perbaikan bug yang ditemukan di jalan

Halaman denah simulasi memakai tampilan kustom yang tidak pernah merender
`$this->table`, padahal lewat situlah Filament merender modal aksi. Akibatnya
modal **tidak pernah terbuka** di halaman itu, termasuk tombol **Ubah jam**
yang sudah ada sebelumnya. Sekarang `<x-filament-actions::modals />` dirender
eksplisit di `index.blade.php`, dan tes memastikan penampung modalnya ada.

---

## 2. Manual pengguna per peran

### Akses

- Halaman depan (`/`) kini punya tautan **Panduan Pengguna**, tanpa login.
- `/panduan` → daftar semua peran: Siswa, Pengawas, Operator, Peneliti, Admin.
- `/panduan/{peran}` → langkah berurutan per halaman utama, dengan tangkapan
  layar asli. Tab di atas berpindah antarperan. Peran yang tidak dikenal → 404.
- Isi manual dan daftar tangkapan punya satu sumber:
  `app/Support/UserManual.php`.

Diverifikasi dengan curl: tautan di `/` menuju `/panduan` (200). Kelima
halaman peran 200, dengan 6/4/6/4/7 langkah dan 24 gambar. Gambar tersaji
sebagai `image/png` (200). Di lebar 360 px tidak ada geser horizontal (R8).

### Tangkapan layar

- **Asli, bukan mockup:** Playwright membuka aplikasi yang berjalan
  (`APP_URL`, lokal `http://localhost:8019`), masuk memakai akun **simulasi
  demo** tiap peran lewat formulir login Filament, lalu memotret. Alur siswa
  dijalankan sungguhan: isi token, identitas, persetujuan, soal latihan,
  butir tes pertama, dan halaman selesai dari sesi demo yang sudah selesai.
- **Lokasi:** `public/manual/<peran>/NN-nama.png` (disk `manual`), URL
  `/manual/...`. **Masuk Git** dan ikut ter-deploy bersama kode, tanpa
  `storage:link` atau artisan di server. Total 24 berkas, sekitar 2,3 MB.
- **Sandi demo disamarkan** pada gambar lembar akun demo.
- Tangkapan yang berkasnya belum ada diganti catatan "belum dibuat", bukan
  gambar rusak.
- Skrip **gagal** kalau halaman yang dibuka mengembalikan 4xx/5xx, jadi
  halaman galat tidak bisa masuk manual diam-diam. Saat gagal, potret
  keadaannya disimpan di `storage/app/manual-capture-failures/` (di luar
  folder publik).

### Regenerasi

```bash
# 1. siapkan job (membuat simulasi demo kalau belum aktif)
docker exec c-eco-php php artisan manual:capture-screenshots
# 2. kontainer PHP tidak punya Node, jadi perintah di atas mencetak baris ini untuk dijalankan di host:
node scripts/capture-manual.mjs storage/app/manual-capture.json
```

Di mesin yang punya PHP dan Node sekaligus, cukup
`php artisan manual:capture-screenshots` (opsi: `--base-url=`, `--node=`,
`--prepare-only`, `--reset-after`). Berkas job memuat sandi demo, jadi
dihapus otomatis setelah pemotretan.

### Halaman yang sengaja tidak dipotret

| Langkah | Alasan |
|---|---|
| Operator → Sekolah, Peserta | `canManageRoster()` menutup menu ini bagi **semua** akun simulasi, admin demo juga, karena memuat data siswa asli. Aturan itu dipertahankan. |
| Operator → Paket Ujian | Hanya terbuka bagi operator tes asli (`canManagePackages()`). |

Langkah-langkah ini tetap ada sebagai teks, dengan catatan alasannya di
halaman.

---

## Daftar berkas

**Baru**

- `database/migrations/2026_09_24_000012_add_is_demo_to_exam_simulations.php`
- `app/Services/DemoSimulation.php`
- `app/Console/Commands/SimulationGenerateCommand.php`
- `app/Console/Commands/SimulationStatusCommand.php`
- `app/Console/Commands/SimulationResetCommand.php`
- `app/Console/Commands/CaptureManualScreenshotsCommand.php`
- `app/Support/UserManual.php`
- `app/Http/Controllers/ManualController.php`
- `resources/views/manual/layout.blade.php`, `index.blade.php`, `show.blade.php`
- `scripts/capture-manual.mjs`
- `tests/Feature/DemoSimulationTest.php`, `tests/Feature/UserManualTest.php`
- `SIMULASI_MANUAL_SUMMARY.md`

**Diubah**

- `app/Models/ExamSimulation.php`: cast `is_demo`, scope `demo()`
- `app/Services/ExamSimulationBuilder.php`: `accounts()` menjadi public (untuk akun admin/peneliti demo)
- `app/Providers/AppServiceProvider.php`: Gate `manage-simulation`
- `app/Filament/Resources/ExamSimulations/Pages/ListExamSimulations.php`: aksi Buat/Hapus Simulasi
- `resources/views/filament/resources/exam-simulations/index.blade.php`: baris status + render modal
- `resources/views/filament/resources/exam-simulations/view.blade.php`: teks sandi untuk demo
- `resources/views/filament/hooks/admin-assets.blade.php`: cache-bust CSS (`?v=8`)
- `public/css/exam-simulation.css`: gaya baris status
- `resources/views/welcome.blade.php`: tautan Panduan Pengguna
- `resources/css/site.css`: gaya halaman panduan
- `routes/web.php`: `GET /panduan`, `GET /panduan/{role}` (hanya dua rute ini yang di-commit)
- `package.json`, `package-lock.json`: devDependency `playwright` 1.63.0 (tanpa unduh browser)
- `tests/Feature/SimulationSeederTest.php`: bekukan jam (perbaikan tes lama)

## Uji

- Suite penuh di working tree: **229 passed, 1 incomplete** (incomplete sudah ada sebelumnya).
- Suite penuh di HEAD bersih (worktree sementara, **tanpa** pekerjaan SeatGuard yang belum di-commit): **227 passed, 1 incomplete**.
- Sebelum pekerjaan ini, suite punya 1 kegagalan (`SimulationSeederTest`,
  tanggal preset sudah lewat). Kegagalan itu terbukti juga muncul di HEAD
  bersih, lalu diperbaiki di `db3367d`.
- Pint: bersih untuk semua berkas PHP yang disentuh.

## Yang perlu dijalankan

**Lokal / server mana pun setelah pull**

```bash
php artisan migrate                 # kolom exam_simulations.is_demo
npm ci && npm run build             # CSS halaman panduan (site.css)
```

Lokal, symlink dibuat relatif (`public/storage -> ../storage/app/public`)
karena `storage:link --relative` gagal di kontainer. `public/build/assets`
milik root, sehingga build lokal dijalankan di kontainer `laravel-node22`
(`docker exec -w /var/www/html/c-eco laravel-node22 npx vite build`).

Untuk memotret di mesin baru: `npx playwright install chromium`. Browser
sudah terpasang di mesin ini.

**Produksi**

- Tangkapan layar ada di `public/manual/` dan ikut Git, jadi rsync deploy
  (yang hanya mengecualikan `storage/`) membawanya ke VPS. Tidak perlu
  artisan, `storage:link`, Node, atau Playwright di server.
- Untuk memperbarui gambar: potret ulang di laptop, commit `public/manual/`,
  lalu push/deploy seperti biasa.

## Catatan dan risiko yang perlu diputuskan

1. **Admin demo adalah admin penuh.** Admin demo bisa membuka halaman
   Pengguna (membuat/menonaktifkan akun asli), menyunting butir, dan
   menghapus gelombang simulasi lain. Mitigasinya:
   - sandinya acak per demo dan hanya terlihat oleh admin;
   - akunnya hilang saat reset;
   - data siswa asli (Sekolah/Peserta) tetap tertutup.

   Tetapi akun yang **dibuat** admin demo tidak bertanda simulasi, jadi tidak
   ikut terhapus. Kalau akun demo akan dibagikan ke peserta pelatihan,
   pertimbangkan membatasi `canManageStaff()`/`canEditItems()` bagi akun
   simulasi. Konsekuensinya: tangkapan admin untuk Pengguna dan Unggah soal
   tidak bisa dibuat lagi.
2. **Ekspor dan Monitor ikut memuat sesi simulasi** (demo maupun gelombang
   tulisan admin). Ini perilaku lama dan tidak diubah. Manual peneliti
   menjelaskan cara memisahkannya lewat `test_config_id`.
3. **Pekerjaan SeatGuard/resume-token yang belum di-commit tidak disentuh**
   dan tetap unstaged: `BindStudentSeat`, `SeatGuard`, migrasi `..._000011`,
   `occupied.blade.php`, perubahan ProctorQr, dan dua hunk di
   `routes/web.php`. Tangkapan alur siswa diambil dari working tree, jadi
   sudah mencerminkan perubahan itu.
4. DB lokal diisi bank soal (`cat:seed-items`,
   `cat:seed-provisional-parameters`, `TestConfigSeeder`), karena demo
   membutuhkan butir. Simulasi demo **dibiarkan aktif** di DB lokal (dibuat
   ulang saat pemotretan terakhir) supaya bisa langsung dicoba. Hapus dengan
   `simulation:reset`.
