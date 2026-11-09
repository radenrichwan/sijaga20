# SIJAGA

**SIJAGA (Sistem Informasi Pelanggaran dan Catatan Kedisiplinan Siswa)** adalah aplikasi web untuk mengelola catatan kedisiplinan, transaksi poin, jadwal piket, penghargaan, dan pengajuan keberatan siswa secara terpusat.

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-supported-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Status](https://img.shields.io/badge/Status-Academic_Assessment_(USK_PPLG)-blue)](#status-proyek)

| Informasi | Keterangan |
|---|---|
| Nama | SIJAGA — Sistem Informasi Pelanggaran dan Catatan Kedisiplinan Siswa |
| Pengembang | Raden Richwan — Pengembangan Perangkat Lunak dan Gim (PPLG) |
| Status | Siap untuk evaluasi akademik/USK PPLG |

---

## Fitur

### Guru

- Mengelola data siswa, kategori pelanggaran, dan apresiasi.
- Mencatat pelanggaran, keterlambatan, poin tambahan, dan penghargaan.
- Mengelola jadwal serta penggantian petugas piket sesuai hak akses.
- Meninjau pengajuan keberatan siswa.
- Melihat laporan, ekspor data, dan jejak aktivitas sistem.

### Operasional Piket

- Mencatat keterlambatan dan penghargaan pagi.
- Melihat jadwal piket serta mencatat kehadiran anggota yang berhak.
- Mengajukan penggantian anggota piket.
- Mengelola jadwal dan memutuskan penggantian jika akun memiliki izin pengelolaan jadwal.

### Siswa

- Melihat dashboard, akumulasi poin, dan riwayat transaksi pribadi.
- Melihat kategori pelanggaran dan apresiasi.
- Mengajukan keberatan terhadap transaksi poin miliknya.
- Melihat notifikasi dan status peninjauan keberatan.

---

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP `^8.3`, Laravel `^13.0` |
| Tampilan | Laravel Blade, Bootstrap 5, Tailwind CSS 3 |
| Interaktivitas | Alpine.js, Bootstrap JavaScript, Select2 |
| Grafik | Chart.js |
| Asset bundler | Vite, Laravel Vite Plugin |
| Basis data | PostgreSQL direkomendasikan; konfigurasi juga mendukung MySQL dan SQLite |
| Pengujian otomatis | PHPUnit melalui Laravel Test Runner |

> **Catatan versi:** Versi di atas mengikuti `composer.json`, lockfile, dan konfigurasi frontend repository. Proyek ini menggunakan Laravel 13, bukan Laravel 11. CSS menggunakan direktif Tailwind CSS 3; Bootstrap 5 juga digunakan pada antarmuka. Meski `@tailwindcss/vite` tercantum sebagai dependensi, konfigurasi Vite saat ini tidak mendaftarkannya sebagai plugin.

---

## Arsitektur dan Struktur Direktori

SIJAGA menggunakan arsitektur monolitik Laravel dengan pemisahan logika bisnis ke dalam service, validasi form melalui Form Request, serta kontrol akses berbasis role dan izin.

```text
app/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   │   └── CheckRole.php
│   └── Requests/
├── Models/
│   ├── Role.php
│   └── User.php
└── Services/
    ├── ActivityLogService.php
    ├── DutyScheduleService.php
    ├── PointTransactionService.php
    ├── StudentObjectionService.php
    └── ViolationService.php

resources/
├── css/
├── js/
└── views/

database/
├── factories/
├── migrations/
└── seeders/

routes/
├── auth.php
├── console.php
└── web.php
```

Komponen akses dan skema role utama:

- `app/Models/Role.php` dan `app/Models/User.php`
- `app/Http/Middleware/CheckRole.php`
- `database/migrations/2026_11_01_194430_create_roles_table.php`
- `database/migrations/2026_11_02_000004_add_roles_to_users_table.php`

Role yang digunakan aplikasi adalah `guru`, `operasional`, dan `siswa`. Pengelolaan beberapa fitur piket juga dibatasi dengan izin `can_manage_schedule`.

---

## Persyaratan

- PHP 8.3 atau lebih baru dengan ekstensi yang dibutuhkan Laravel.
- Composer.
- Node.js dan npm.
- PostgreSQL untuk konfigurasi database yang direkomendasikan. MySQL dan SQLite juga tersedia sebagai driver.

---

## Instalasi Lokal

1. Kloning repository:

   ```bash
   git clone https://github.com/radenrichwan/sijaga20.git
   cd sijaga20
   ```

2. Pasang dependensi PHP dan JavaScript:

   ```bash
   composer install
   npm install
   ```

3. Salin `.env.example` menjadi `.env`.

   PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

   Atur koneksi database pada `.env`. Untuk PostgreSQL, misalnya:

   ```dotenv
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=sijaga
   DB_USERNAME=postgres
   DB_PASSWORD=ubah_dengan_password_database
   ```

   Buat database `sijaga` di server PostgreSQL terlebih dahulu. Driver dapat diganti melalui `DB_CONNECTION` sesuai konfigurasi yang tersedia di `config/database.php` (`pgsql`, `mysql`, atau `sqlite`).

4. Buat application key dan jalankan migrasi beserta data awal:

   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```

5. Jalankan aplikasi untuk pengembangan. Buka dua terminal di direktori proyek:

   ```bash
   npm run dev
   ```

   ```bash
   php artisan serve
   ```

   Aplikasi tersedia secara default di `http://127.0.0.1:8000`.

Untuk membangun asset tanpa Vite development server:

```bash
npm run build
```

---

## Akun Demo

Seeder membuat akun demo dengan **username sebagai password awal**. Aplikasi menggunakan username, bukan alamat email, untuk login. Akun berikut hanya untuk lingkungan lokal setelah menjalankan `php artisan migrate --seed`.

| Role | Username | Password awal | Hak akses ringkas |
|---|---|---|---|
| Guru | `guru0001` | `guru0001` | Administrasi data siswa dan kategori, transaksi kedisiplinan, laporan, peninjauan keberatan, dan activity log |
| Operasional Piket | `piket001` | `piket001` | Operasional piket dan pencatatan harian; pengelolaan jadwal bergantung pada izin akun |
| Siswa | `12345` | `12345` | Dashboard dan riwayat pribadi serta pengajuan keberatan |

Seeder juga menyediakan akun guru `guru0002`–`guru0003`, akun piket `piket002`–`piket006`, dan akun siswa dengan username NIS `12345`–`12364`. Kata sandi awal untuk setiap akun tersebut sama dengan username masing-masing. **Ganti kata sandi akun demo sebelum menempatkan aplikasi pada lingkungan yang dapat diakses pihak lain.**

---

## Pengujian

Jalankan pengujian otomatis dengan:

```bash
php artisan test
```

Suite pengujian mencakup fitur autentikasi dan area seperti dashboard, pembatasan role, pengelolaan siswa, ledger poin, keterlambatan, jadwal/kehadiran piket, keberatan, laporan, notifikasi, dan activity log.

Metodologi pengujian sistem mencakup **Black-Box Testing** untuk menguji alur dari sudut pandang pengguna pada setiap role, termasuk input valid/tidak valid, batas akses, pencatatan transaksi, dan status keberatan. Evaluasi kemudahan penggunaan dilakukan melalui **Usability Evaluation** menggunakan Google Forms. Catat skenario, responden, hasil, dan tindak lanjut pada laporan evaluasi; README ini tidak menyatakan skor atau jumlah responden.

---

## Status Proyek

SIJAGA disiapkan untuk kebutuhan evaluasi akademik USK PPLG. Sebelum digunakan pada lingkungan produksi, lakukan peninjauan konfigurasi keamanan dan deployment, ubah seluruh kredensial demo, siapkan backup database, serta sesuaikan pengaturan penyimpanan dan layanan operasional dengan infrastruktur tujuan.

---

## Lisensi

Silakan merujuk pada berkas lisensi atau kebijakan distribusi repository. Dependensi pihak ketiga tetap tunduk pada lisensi masing-masing.
