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

## Jalur mundur hari-H

```bash
sed -i 's/^CAT_MODE=.*/CAT_MODE=linear/' .env
docker compose -f ../docker-compose.yml exec c-eco-php php artisan config:cache
docker compose -f ../docker-compose.yml restart c-eco-php c-eco-worker
```
