# C-ECO

Sistem Computerized Adaptive Test berbasis Item Response Theory untuk asesmen
*creative thinking* dalam pembelajaran ekonomi. Penelitian Unggulan Universitas
Siliwangi 2026.

**Uji fisibilitas: Senin, 21 September 2026 — 300 siswa SMA serentak, HP dan kuota pribadi.**

Spesifikasi, `AGENTS.md`, bank soal (`data/`), skrip ekstraksi (`tools/`),
dan kit `persiapan/` sengaja tidak masuk Git. Salin dari mesin pengembang
sebelum seeder langkah 02.

## Jalankan

Sementara memakai stack Docker bersama di `~/code/docker-compose.yml`
(jaringan `code_laranet`, MariaDB host, Redis `redis`).

```bash
cp .env.example .env
make up
make test
```

- Aplikasi: http://localhost:8019
- Admin Filament: http://localhost:8019/admin
- MariaDB host: `127.0.0.1:3306` · `db_ceco` · user `app`

## Perintah

| Perintah | Kegunaan |
|---|---|
| `cat:seed-items` | Memuat bank soal dari `data/items-all.json` |
| `cat:seed-provisional-parameters` | Parameter butir sementara untuk uji fisibilitas |
| `cat:import-parameters` | Parameter hasil kalibrasi, sebagai versi baru (R5) |
| `cat:import-participants` | Daftar peserta dari CSV |
| `cat:issue-tokens` | Menerbitkan token akses |
| `cat:simulate`, `cat:simulate-report` | Monte Carlo dan ringkasannya |
| `cat:export` | Empat CSV untuk analisis di R |
| `cat:backup` | Dump basis data terkompresi |
| `cat:print-form` | Lembar soal dan lembar jawaban untuk lapis mundur L3 |
| `cat:seed-simulation` | Akun panel semua peran + 20 siswa beserta token (lokal) |

## Jalur mundur hari-H

```bash
sed -i 's/^CAT_MODE=.*/CAT_MODE=linear/' .env
docker compose -f ../docker-compose.yml exec c-eco-php php artisan config:cache
```

Sesi baru langsung linear; sesi yang sedang berjalan tetap adaptif sampai
selesai (aturan R10). Prosedur lengkap L1–L3 dan checklist pra-terbang ada di
`docs/RUNBOOK.md` — **tidak ikut Git**, jadi salin ke server sebelum hari-H.
