# Product Requirement Document (PRD) — SIJAGA 20

## Metadata

| Atribut | Nilai |
|---|---|
| Nama aplikasi | **SIJAGA 20 — Sistem Informasi Jaga Gerbang dan Kedisiplinan Siswa** |
| Instansi | SMKN 20 Jakarta |
| Versi dokumen | 2.0 Final |
| Tanggal | 10 November 2026 |
| Status | Final — spesifikasi disusun dari source code dan konfigurasi proyek yang disediakan |

> **Catatan akurasi:** versi aplikasi pada metadata dokumen mengikuti permintaan pemilik proyek. Versi dependensi, perilaku fitur, hak akses, dan konfigurasi di bawah ini merujuk pada source code final. Dokumen ini tidak menganggap fitur atau hasil pengujian yang tidak terimplementasi sebagai fakta.

## 1. Pendahuluan dan Latar Belakang

Pencatatan kedisiplinan dan poin siswa mencakup beberapa proses yang saling terkait: pelanggaran, keterlambatan, apresiasi, jadwal serta kehadiran piket, dan peninjauan keberatan. SIJAGA 20 menyediakan aplikasi web untuk mengelola catatan tersebut, menghitung saldo dari transaksi poin, menampilkan data sesuai peran, dan menyimpan jejak perubahan penting.

Sistem menggunakan satu tabel transaksi poin sebagai sumber perubahan saldo. Transaksi yang dibuat oleh pencatatan pelanggaran, keterlambatan, penghargaan, atau piket dikaitkan dengan sumbernya. Ketika catatan yang berdampak pada saldo dikoreksi, service terkait mencatat transaksi koreksi, alih-alih mengubah transaksi lama untuk menyesuaikan saldo. Siswa dapat memantau data miliknya dan mengajukan keberatan terhadap transaksi yang menjadi miliknya.

### Tujuan produk

- Memusatkan data siswa, kategori poin, catatan kedisiplinan, apresiasi, piket, dan keberatan.
- Menghitung saldo dari poin awal dan transaksi aktif siswa.
- Memisahkan akses Guru, Operasional, dan Siswa dengan pemeriksaan akses di server.
- Memungkinkan peninjauan transaksi, aktivitas, notifikasi, dan laporan sesuai hak akses.

## 2. Analisis Kebutuhan

### 2.1 Kebutuhan pengguna per peran

| Pengguna | Kebutuhan |
|---|---|
| Guru | Melihat ringkasan kedisiplinan; mengelola data siswa dan kategori; mencatat serta mengoreksi pelanggaran, keterlambatan, poin, dan penghargaan; mengelola jadwal piket; meninjau keberatan; melihat laporan dan activity log. |
| Operasional | Mengakses dashboard operasional; mencatat pelanggaran, keterlambatan, penghargaan pagi, poin tambahan, dan kehadiran piket; melihat data siswa dan laporan; mengajukan penggantian piket atau mengelolanya bila memiliki izin pengelola jadwal; meninjau keberatan. |
| Siswa | Melihat dashboard, saldo, grafik tren, transaksi dan transkrip miliknya; melihat katalog kategori; menerima notifikasi; mengajukan keberatan atas transaksi miliknya serta memantau statusnya. |

### 2.2 Kebutuhan sistem dan keamanan

- Autentikasi berbasis session Laravel dengan **username dan kata sandi**. Tabel `users` tidak memiliki kolom email setelah migrasi perubahan identitas.
- Akun harus aktif dan mempunyai salah satu dari tiga role yang dikenali sistem agar dapat melewati middleware role.
- Permintaan login dibatasi setelah lima percobaan gagal per kombinasi username dan alamat IP.
- Pemeriksaan izin dilakukan di server melalui middleware `role`, Laravel Policies, Gate `can_manage_schedule`, dan Form Request pada alur terkait.
- Akses siswa ke profil, transaksi, dan keberatan dibatasi pada data siswa yang terhubung ke akunnya.
- Input divalidasi pada Form Request atau controller; perubahan transaksi bisnis utama dibungkus dalam transaksi basis data.
- Bukti keberatan disimpan pada disk privat `local`, bukan tautan publik langsung, dan diunduh melalui route yang memerlukan autentikasi serta role Guru/Operasional.
- Activity log menyimpan aktor, role, jenis tindakan, subjek, nilai sebelum/sesudah, metadata, dan waktu. Model log menolak operasi pembaruan dan penghapusan melalui Eloquent.
- Konfigurasi contoh memakai SQLite, session/cache/queue berbasis database, dan filesystem lokal. Konfigurasi Laravel juga mendefinisikan driver DB lain; deployment harus mengatur dan memvalidasi lingkungan yang dipilih.

## 3. Ruang Lingkup

### 3.1 In-Scope — fitur yang tersedia pada source

- Login, logout, pembatasan percobaan login, profil, dan dashboard berbeda per role.
- Pengelolaan data siswa: pencarian/filter, tambah dan ubah oleh Guru, aktivasi/nonaktivasi, impor CSV/TXT/XLSX, ekspor data CSV/XLSX, dan unduh template CSV.
- Katalog serta pengelolaan kategori pelanggaran/reward.
- Pencatatan pelanggaran, keterlambatan, transaksi ledger manual, poin tambahan, dan penghargaan pagi.
- Jadwal piket hari kerja, pratinjau pembagian, konfirmasi, pengajuan/keputusan penggantian, presensi, dan rekap presensi.
- Pengajuan dan peninjauan keberatan, termasuk catatan serta file bukti.
- Dashboard, notifikasi internal berbasis database, activity log, laporan poin, ekspor laporan CSV, PDF laporan, dan transkrip siswa PDF.

### 3.2 Out-of-Scope — tidak ditemukan implementasi aktif di source yang ditinjau

- Role administrator terpisah atau antarmuka untuk mengelola akun staf dan role.
- Pendaftaran mandiri pengguna melalui route web yang aktif.
- Login dengan email, integrasi dengan sistem informasi akademik eksternal, API publik, atau aplikasi mobile khusus.
- Pengiriman notifikasi SMS/push atau integrasi layanan pihak ketiga.
- Penyimpanan unggahan bukti ke cloud sebagai konfigurasi default; filesystem aktif pada `.env.example` adalah lokal.
- Klaim jaminan ketersediaan layanan, kapasitas, performa, atau hasil evaluasi usability yang belum dibuktikan melalui pengukuran.

## 4. Peran Pengguna dan Matriks Hak Akses

Sistem mengenali tepat tiga nama role: `guru`, `operasional`, dan `siswa`. Tidak terdapat role `admin`. Akses tetap bergantung pada status aktif akun dan pemeriksaan kebijakan pada tindakan tertentu.

| Fitur / tindakan | Guru | Operasional | Siswa |
|---|---|---|---|
| Dashboard | Ringkasan sekolah, filter tanggal, statistik dan grafik | Ringkasan operasional dan jadwal/aktivitas hari ini | Ringkasan pribadi, saldo, tren, riwayat dan keberatan |
| Daftar/master data siswa | Lihat, tambah, ubah, aktif/nonaktif, impor, ekspor | Lihat dan ekspor; tindakan perubahan/import dibatasi Policy kepada Guru | Tidak dapat membuka daftar; hanya dapat melihat profil sendiri |
| Detail siswa dan transkrip | Lihat semua siswa | Lihat semua siswa | Hanya profil dan transkrip sendiri |
| Kategori poin | Kelola dan lihat katalog | Kelola dan lihat katalog | Hanya lihat katalog |
| Pelanggaran dan keterlambatan | Catat, lihat, ubah, hapus | Catat, lihat, ubah, hapus | Melihat dampaknya melalui transaksi pribadi |
| Ledger manual | Dapat membuat transaksi melalui Policy | Route tersedia, tetapi pembuatan ledger ditolak oleh Policy | Tidak dapat membuat transaksi |
| Poin tambahan dan penghargaan pagi | Catat, lihat, ubah, hapus | Catat, lihat, ubah, hapus | Hanya melihat hasil pada riwayat |
| Jadwal piket | Lihat, pratinjau, konfirmasi, dan mengelola penggantian | Lihat dan mengajukan penggantian; pratinjau/konfirmasi/keputusan penggantian hanya jika `can_manage_schedule` aktif | Tidak ada route jadwal siswa |
| Presensi piket | Lihat rekap dan jadwal; tidak dapat menyimpan presensi | Lihat rekap dan mencatat presensi yang memenuhi syarat | Tidak dapat mencatat presensi |
| Keberatan | Lihat, memutuskan, unduh bukti | Lihat, memutuskan, unduh bukti | Ajukan transaksi milik sendiri, lihat status sendiri |
| Laporan | Lihat, filter, unduh CSV dan PDF | Lihat, filter, unduh CSV dan PDF | Tidak tersedia |
| Activity log | Lihat dan filter | Tidak tersedia | Tidak tersedia |
| Notifikasi dan profil | Notifikasi; profil | Notifikasi; profil | Notifikasi; profil |

### Batas izin khusus

- `can_manage_schedule` bernilai benar untuk semua akun Guru melalui pemeriksaan model. Untuk Operasional, izin ini memerlukan flag `can_manage_schedule` pada akun.
- Seeder memberi izin tersebut kepada `piket001` dan `piket002`; akun Operasional lain tidak mendapatkannya.
- Operasional dapat membuka route daftar siswa/laporan sesuai route dan Policy, tetapi Policy siswa membatasi pembuatan, perubahan, impor, dan penonaktifan kepada Guru.
- Walaupun route ledger berada pada grup Guru/Operasional, `PointLedgerPolicy` hanya mengizinkan Guru membuat transaksi manual.

## 5. Spesifikasi Fitur Utama

### 5.1 Autentikasi dan dashboard

- Form login meminta `username` dan `password`; email bukan identitas login.
- Login sukses meregenerasi session. Logout mengakhiri session dan meregenerasi token CSRF.
- Login hanya berhasil untuk akun aktif. Middleware juga menolak akun nonaktif atau role yang tidak terdaftar.
- Dashboard bercabang berdasarkan role: ringkasan dan grafik Guru, aktivitas operasional, serta dashboard pribadi Siswa. Grafik menggunakan Chart.js.
- Registrasi tidak didaftarkan pada `routes/auth.php`. Route login lupa/reset kata sandi dan verifikasi email ada pada route autentikasi Laravel, tetapi tabel pengguna menggunakan username, bukan kolom email.

### 5.2 Akun demo hasil `DatabaseSeeder`

Password seeder diset sama dengan username (untuk siswa: NIS). Kredensial ini hanya untuk pengujian lokal dan harus diganti bila digunakan pada lingkungan bersama.

| Role | Username demo | Password demo | Catatan |
|---|---|---|---|
| Guru | `guru0001` (juga `guru0002`, `guru0003`) | Sama dengan username | Ketiga akun aktif; semua Guru memenuhi izin pengelola jadwal. |
| Operasional | `piket001` (juga `piket002`–`piket006`) | Sama dengan username | Hanya `piket001` dan `piket002` memiliki flag kelola jadwal. |
| Siswa | `12345` (rentang `12345`–`12364`) | Sama dengan username/NIS | Seeder membuat 20 akun siswa aktif dan profil siswa terkait. |

Seeder juga menyiapkan kategori, transaksi demonstrasi, serta jadwal demonstrasi. Nilai saldo siswa awal adalah 100 poin.

### 5.3 Master data siswa

- Profil mencakup NIS, NISN, nama, jenis kelamin, tingkat, jurusan, kelas, status aktif, dan poin awal.
- NIS digunakan sebagai username akun siswa. Saat akun siswa dibuat melalui fitur tambah/impor, kata sandi awal diset ke NIS.
- Guru dapat menambah/mengubah data dan mengaktifkan atau menonaktifkan akun. Nonaktif bukan penghapusan riwayat.
- Impor menerima CSV/TXT dan XLSX (worksheet pertama), dengan header seperti `nis`, `nisn`, `name`, `gender`, `grade`, `major`, `class_name`, dan opsional `is_active`. Baris tidak valid dilaporkan per nomor baris.
- Data siswa dapat diekspor sebagai CSV dan XLSX.

### 5.4 Kategori dan transaksi poin

Tabel `point_transactions` menjadi ledger utama. Saldo dihitung sebagai `initial_points` ditambah jumlah `points` pada transaksi aktif. Model `PointLedger` adalah turunan dari `PointTransaction` yang menggunakan tabel yang sama; tidak ada tabel ledger kedua.

| Alur pencatatan | Nilai yang dicatat |
|---|---:|
| Pelanggaran melalui modul pelanggaran | −5 per catatan pelanggaran |
| Keterlambatan | −5 per catatan |
| Penghargaan pagi | +15 per penghargaan |
| Presensi piket yang sah | +10 per presensi |
| Poin tambahan | Bilangan bulat positif 1–10.000 sesuai input |
| Ledger manual oleh Guru | Tanda dan nilai mengikuti jenis kategori yang dipilih |

Untuk input pelanggaran jamak, setiap kategori yang dipilih membuat catatan pelanggaran dan transaksi −5 sendiri. Dengan demikian, nilai pada field poin kategori tidak mengubah nilai tetap −5 yang dipakai service pelanggaran. Kategori poin tetap digunakan oleh alur ledger manual dan kategori transaksi lainnya.

Pencatatan poin mengunci profil siswa dan menghitung ulang saldo di dalam transaksi database. Perubahan/penghapusan catatan pelanggaran, keterlambatan, poin tambahan, dan penghargaan pagi membuat transaksi kompensasi yang mengimbangi transaksi terkait. Pelanggaran dan keterlambatan menggunakan soft delete; sumber transaksi lain mengikuti perilaku model masing-masing.

### 5.5 Keterlambatan dan penghargaan pagi

- Keterlambatan hanya dapat dicatat untuk siswa aktif dan tanggal tidak boleh di masa depan. Pukul 06.30 WIB masih tepat waktu; keterlambatan dimulai pukul 06.31 WIB. Satu siswa tidak dapat memiliki catatan keterlambatan aktif ganda pada tanggal yang sama.
- Penghargaan pagi hanya menerima waktu antara pukul 04.00–05.30 WIB, peringkat 1–10, dan tanggal hari ini atau sebelumnya. Satu siswa dan satu peringkat hanya dapat digunakan sekali pada tanggal yang sama.
- Kedua jenis pencatatan memberi notifikasi internal kepada siswa dan mencatat audit aktivitas.

### 5.6 Jadwal, penggantian, dan presensi piket

- Jadwal dibuat untuk Senin–Jumat. Generator memilih empat akun Operasional per hari dan menyeimbangkan jumlah tugas; validasi konfirmasi menerima 4–6 anggota per hari dan membatasi selisih jumlah tugas mingguan maksimal satu hari.
- Pengelola dapat meninjau pratinjau dan mengubah daftar sebelum menyimpan jadwal. Jadwal yang telah memiliki presensi atau penggantian tidak dapat mengubah roster.
- Anggota dapat mengajukan pengganti; pengelola jadwal memutuskan permohonan. Pengajuan/persetujuan pada tanggal tugas yang sama harus dilakukan sebelum pukul 06.00 WIB.
- Presensi hanya dapat dicatat oleh akun Operasional untuk jadwal berstatus aktif pada hari berjalan. Anggota jadwal atau pengganti yang disetujui harus memiliki profil siswa penerima poin yang aktif. Presensi menghasilkan +10 poin dan dicegah dari pencatatan ganda.
- Rekap presensi dapat dilihat per hari, bulan, atau tahun. Perubahan jadwal/penggantian dan kehadiran memicu audit; perubahan jadwal yang dirilis juga mengirim notifikasi kepada anggota.

### 5.7 Pengajuan keberatan

- Siswa memilih transaksi poin miliknya sendiri dan memberikan alasan 10–2.000 karakter; catatan pendukung opsional maksimal 5.000 karakter.
- Bukti opsional menerima JPG/JPEG, PNG, atau PDF dengan ukuran maksimal 5 MB dan disimpan pada disk lokal privat.
- Satu transaksi tidak dapat memiliki lebih dari satu keberatan berstatus `pending` pada saat yang sama.
- Guru atau Operasional aktif dapat menyetujui/menolak dengan alasan keputusan 5–2.000 karakter. Keberatan yang sudah diputus tidak dapat diputuskan ulang.
- Persetujuan membuat transaksi koreksi baru dengan nilai kebalikan dari transaksi sumber; transaksi sumber tetap disimpan. Keputusan dan koreksi dicatat pada audit log dan notifikasi siswa.

### 5.8 Laporan, transkrip, notifikasi, dan audit

- Laporan poin difilter berdasarkan kelas, jurusan, nama, NISN/pencarian, bulan, tahun, semester, kategori, jenis transaksi, serta rentang tanggal. Hasil merangkum frekuensi/kumpulan poin per kategori dan absensi piket.
- Laporan dapat ditampilkan, diekspor sebagai CSV, atau diunduh sebagai PDF landscape melalui Dompdf. Route bernama `reports.export.excel` juga menghasilkan file CSV, bukan workbook XLSX.
- Daftar siswa memiliki ekspor CSV dan XLSX. Transkrip poin per siswa tersedia sebagai PDF.
- Notifikasi disimpan melalui channel database; pengguna dapat menandai satu notifikasi atau seluruh notifikasi sebagai dibaca.
- Activity log Guru dapat difilter berdasarkan pengguna, aksi, rentang tanggal, dan pencarian. Tindakan yang direkam mencakup perubahan penting pada siswa, kategori, catatan poin, piket, dan keberatan.

## 6. Arsitektur Sistem dan Skema Database

### 6.1 Arsitektur aplikasi

- **Backend:** Laravel 13 (versi terkunci `v13.39.0` pada `composer.lock`), PHP minimum sesuai kendala Composer `^8.3`.
- **Presentasi:** Blade server-rendered, JavaScript/CSS dibundel dengan Vite.
- **Lapisan domain:** Controller menerima request dan memilih respons; Form Request memvalidasi/otorisasi sebagian alur; Service menangani transaksi domain, perubahan ledger, notifikasi, dan audit.
- **ORM:** Eloquent dengan relasi `belongsTo`, `hasMany`, `belongsToMany`, `morphTo`, dan `morphMany`.
- **Otorisasi:** alias middleware `role` didaftarkan di `bootstrap/app.php`; Policies untuk siswa dan transaksi poin; Gate `can_manage_schedule` didaftarkan di `AppServiceProvider`.
- **Transaksi poin:** `PointTransactionService` membuat ledger dan menghitung ulang saldo dalam `DB::transaction`, dengan penguncian baris siswa.
- **Audit/notifikasi:** `ActivityLogService` dan `InternalNotificationService` menjadi service terpisah.

### 6.2 Tabel domain dan relasi utama

| Tabel | Fungsi dan relasi utama |
|---|---|
| `roles` | Daftar role; satu role memiliki banyak `users`. Migration memasukkan `guru`, `siswa`, `operasional`. |
| `users` | Identitas login username, password, status, role, tipe guru, dan izin kelola jadwal. |
| `students` | Profil siswa; satu profil terkait satu user melalui `user_id`; menyimpan NIS/NISN, kelas, status, dan saldo awal/current. |
| `violation_categories` | Kategori `violation` atau `reward`, nilai poin, dan deskripsi. Model `Category` mewarisi model tabel ini. |
| `point_transactions` | Ledger: siswa, kategori, pencatat, tipe, nilai, tanggal, sumber polymorphic, transaksi yang dikoreksi, status aktif. |
| `violations`, `lateness_records` | Sumber catatan pelanggaran/keterlambatan, terkait siswa, pencatat, dan transaksi poin; keduanya mendukung soft delete. |
| `morning_rewards`, `additional_points` | Sumber penghargaan pagi/poin tambahan dan transaksi poin terkait. |
| `duty_schedules` | Jadwal per hari, status, pembuat, dan informasi pembatalan. |
| `duty_schedule_members` | Pivot jadwal–user dengan tautan `student_id` yang menerima poin. |
| `duty_replacements` | Permohonan penggantian, anggota asal/pengganti, siswa penerima poin, pemohon, status, dan pemutus. |
| `duty_attendances` | Presensi anggota pada jadwal, siswa penerima, pencatat, waktu, dan transaksi +10. |
| `student_objections` | Keberatan siswa terhadap sumber polymorphic, alasan/bukti, status, pemutus, dan transaksi koreksi opsional. |
| `activity_logs` | Aktor, role, aksi, kategori, subjek, data sebelum/sesudah, metadata, dan waktu kejadian. |
| `notifications` | Notifikasi Laravel berbasis UUID dan relasi polymorphic `notifiable`. |

Relasi koreksi pada `point_transactions.counter_entry_for` menunjuk transaksi yang dikoreksi. `source_type`/`source_id` menghubungkan transaksi ke sumber seperti pelanggaran, keterlambatan, penghargaan pagi, poin tambahan, presensi, atau keberatan. Sebagian sumber menyimpan `point_transaction_id` sebagai foreign key balik.

Migration Laravel juga membuat tabel pendukung `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, dan `migrations`.

### 6.3 Database dan konfigurasi lingkungan

- `.env.example` menetapkan `DB_CONNECTION=sqlite`; konfigurasi koneksi Laravel juga mencakup MySQL, MariaDB, PostgreSQL, dan SQL Server.
- `.env.example` menggunakan session, cache, dan queue berbasis database serta disk file `local`.
- PHPUnit menjalankan test dengan SQLite in-memory.
- Nama driver yang tersedia dalam konfigurasi tidak dengan sendirinya membuktikan bahwa setiap lingkungan eksternal telah diuji; verifikasi migrasi dan fitur harus dilakukan pada DBMS target sebelum deployment.

## 7. Kriteria Keberhasilan dan Metodologi Pengujian

Kriteria di bawah adalah target penerimaan yang dapat diuji, bukan pernyataan bahwa pengujian tersebut telah dijalankan atau lulus.

### 7.1 Kriteria keberhasilan

1. Role yang tidak berwenang menerima penolakan server-side pada route/tindakan yang dibatasi.
2. Siswa hanya dapat membaca profil, transaksi, dan keberatan yang terkait dengan akunnya.
3. Saldo yang ditampilkan sama dengan saldo awal ditambah jumlah transaksi aktif.
4. Nilai transaksi mengikuti aturan modul, dan edit/hapus yang berdampak pada poin menghasilkan transaksi kompensasi yang dapat ditelusuri.
5. Duplikasi keterlambatan/penghargaan/peringkat/presensi yang dilarang ditolak.
6. Jadwal hanya dapat dikonfirmasi untuk tanggal kerja dengan anggota aktif yang memenuhi batas jumlah serta pemerataan.
7. Keberatan hanya dapat diputus satu kali; persetujuan menghasilkan koreksi ledger dan status yang dapat dilihat siswa.
8. Laporan, ekspor, dan transkrip menampilkan data yang sesuai filter dan hak akses.
9. Aktivitas penting mencatat aktor, kategori/aksi, waktu, dan perubahan relevan; notifikasi tersimpan pada database.

### 7.2 Metodologi pengujian

- **Black-box peran:** login sebagai Guru, Operasional, dan Siswa; verifikasi menu, route, data milik siswa, akses denied, dan batas izin pengelola jadwal.
- **Black-box transaksi:** uji pelanggaran tunggal/jamak, keterlambatan sebelum/sesudah batas 06.30, perubahan dan penghapusan, saldo akhir, serta entri koreksi.
- **Black-box piket/keberatan:** uji jadwal 4–6 anggota, keseimbangan, penggantian sebelum/sesudah 06.00, presensi ganda, status keberatan, bukti, dan koreksi.
- **Laporan/ekspor:** cocokkan hasil filter, frekuensi/poin, CSV, PDF, dan transkrip dengan data fixture.
- **Usability evaluation:** minta perwakilan Guru, Operasional, dan Siswa menuntaskan tugas utama; ukur tingkat penyelesaian, waktu, dan kesalahan, lalu catat umpan balik. Hasil evaluasi perlu dibuat terpisah dan tidak diklaim tersedia di source code.
- **Suite otomatis:** proyek menyediakan suite PHPUnit Unit dan Feature; jalankan `php artisan test`. Jumlah tes dan status lulus tidak dinyatakan dalam PRD ini karena perlu diverifikasi terhadap snapshot dan lingkungan yang digunakan.

## 8. Rencana Penggelaran dan Environment

### Prasyarat

- PHP **8.3 atau lebih baru** sesuai batas Composer, Composer, Node.js/npm yang kompatibel dengan Vite 7.
- Database SQLite untuk konfigurasi contoh, atau DBMS lain yang dikonfigurasi secara eksplisit di `.env`.
- Akses tulis untuk direktori runtime Laravel (`storage` dan `bootstrap/cache`).

### Langkah deployment/local setup

1. Pasang dependensi PHP dengan `composer install`.
2. Salin `.env.example` menjadi `.env`; atur `APP_NAME`, `APP_URL`, `APP_KEY`, database, mail, dan konfigurasi lingkungan sesuai deployment. Jangan gunakan kredensial demo untuk sistem bersama.
3. Jika memakai SQLite dan file database belum tersedia, buat `database/database.sqlite`.
4. Jalankan `php artisan key:generate`.
5. Jalankan `php artisan migrate --seed` untuk membuat skema, role, kategori, akun demo, dan data contoh.
6. Jalankan `npm install` lalu `npm run build` untuk membangun asset produksi.
7. Konfigurasikan web server agar document root menunjuk ke direktori `public`, set `APP_ENV=production` dan `APP_DEBUG=false`, serta pastikan worker/queue sesuai konfigurasi yang dipakai.

Untuk pengembangan lokal, jalankan `npm run dev` dan `php artisan serve`. Tidak ada konfigurasi deployment cloud khusus atau hasil pengujian beban yang dinyatakan oleh repository.
