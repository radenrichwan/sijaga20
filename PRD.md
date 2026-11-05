# Product Requirement Document (PRD)

## 1. Informasi dan Identitas Produk

| Atribut | Keterangan |
|---|---|
| Nama aplikasi | **SIJAGA 20** — Sistem Informasi Jaga Gerbang dan Kedisiplinan Siswa |
| Instansi | SMKN 20 Jakarta |
| Status proyek | Selesai 100% (Batch 1 sampai Batch 12) |
| Backend | Laravel 13, PHP 8.5 |
| Basis data | PostgreSQL |
| Antarmuka | Blade, Bootstrap 5 |
| Visualisasi data | Chart.js |
| Dokumen PDF | Dompdf |

## 2. Latar Belakang dan Tujuan Bisnis

Pencatatan kedisiplinan, keterlambatan, penghargaan, jadwal piket, absensi, dan perubahan poin siswa perlu dikelola secara terpusat agar riwayatnya konsisten serta mudah ditinjau. Pencatatan manual berisiko menyebabkan data ganda, perhitungan poin yang tidak konsisten, dan kesulitan menelusuri perubahan.

SIJAGA 20 mendigitalisasi proses tersebut dengan satu sumber data transaksi poin (central point ledger), kontrol akses berbasis peran, notifikasi internal, serta laporan yang dapat difilter dan diekspor.

### Tujuan bisnis

- Mencatat pelanggaran, keterlambatan, penghargaan pagi, dan kehadiran piket secara terstruktur.
- Menghitung perubahan poin secara akurat dan atomik.
- Menyediakan jadwal piket yang dapat dibagikan dan mekanisme penggantian anggota yang dapat ditinjau.
- Memberi siswa akses privat ke ringkasan serta riwayat poin dan kanal pengajuan keberatan.
- Menyediakan audit aktivitas dan laporan untuk mendukung pemantauan serta pengambilan keputusan.

## 3. Matriks Role dan Hak Akses

Sistem menggunakan tepat tiga role utama.

| Role | Akun/identitas | Hak akses utama |
|---|---|---|
| **Guru** | `guru0001`, `guru0002`, `guru0003` | Akses administrasi; mengelola akun dan data siswa, kategori, pelanggaran, keterlambatan, poin tambahan, jadwal dan penggantian piket; meninjau keberatan; melihat laporan dan Activity Log; mengelola penghargaan pagi sesuai kewenangan. |
| **Operasional Piket** | `piket001` sampai `piket006` | Mengelola penghargaan pagi, mencatat absensi anggota piket yang sah dan memberikan +10 poin melalui ledger, melihat jadwal, serta mengajukan penggantian. Penjadwalan, konfirmasi jadwal, dan keputusan penggantian memerlukan izin `can_manage_schedule`. |
| **Siswa** | NIS sebagai username autentikasi | Melihat dashboard, profil, dan riwayat poin milik sendiri; melihat kategori dan notifikasi; mengajukan keberatan privat atas transaksi miliknya sendiri dengan bukti maksimal 5 MB. Tidak dapat mengubah poin atau mengakses data siswa lain. |

### Prinsip otorisasi

- Akses route dan tindakan diperiksa berdasarkan autentikasi, role, dan permission yang relevan.
- Data siswa dibatasi pada pemiliknya untuk akses siswa.
- Bukti keberatan tidak tersedia secara publik dan hanya dapat diakses melalui alur yang berwenang.
- Activity Log hanya dapat dibaca Guru dan tidak menyediakan operasi ubah/hapus melalui aplikasi.

## 4. Logika Bisnis dan Central Point Ledger

### Nilai transaksi poin

| Jenis transaksi | Perubahan poin |
|---|---:|
| Pelanggaran | −5 poin per kejadian |
| Keterlambatan | −5 poin per kejadian |
| Penghargaan Pagi | +15 poin per kejadian |
| Absensi Piket Gerbang | +10 poin per kehadiran anggota yang sah |
| Poin Tambahan Guru | Bilangan bulat positif sesuai input Guru |

### Aturan operasional

- Zona waktu operasional adalah **Asia/Jakarta (WIB)**.
- Batas keterlambatan: pukul **06.30 WIB** masih tepat waktu; pukul **06.31 WIB atau setelahnya** dianggap terlambat.
- Setiap siswa hanya dapat memiliki satu catatan keterlambatan per tanggal.
- Penghargaan pagi diberikan kepada maksimal 10 siswa tercepat per tanggal pada rentang **04.00–05.30 WIB**. Satu siswa dan satu peringkat hanya boleh tercatat sekali pada tanggal yang sama.
- Absensi piket hanya berlaku bagi anggota jadwal atau pengganti yang berstatus `approved`. Anggota yang digantikan tidak memperoleh poin untuk tugas tersebut.
- Pengajuan penggantian pada hari pelaksanaan hanya dapat dilakukan sebelum pukul **06.00 WIB**.

### Prinsip ledger

- Perubahan saldo berasal dari transaksi ledger; setiap transaksi menyimpan siswa, nilai, kategori/sumber, pencatat, dan tanggal transaksi.
- Pencatatan dan perubahan terkait dilakukan secara atomik menggunakan `DB::transaction`.
- Riwayat transaksi bersifat **append-only**: transaksi lama tidak dihapus atau ditimpa untuk mengoreksi saldo.
- Edit atau penghapusan data operasional yang berdampak pada poin harus membuat transaksi koreksi baru yang berpasangan dengan transaksi sebelumnya.
- Total poin dihitung dari saldo awal ditambah jumlah transaksi aktif, termasuk entri koreksi yang berlaku.

## 5. Modul Utama dan Fitur

### 5.1 Autentikasi, role, dan dashboard

- Autentikasi pengguna dengan role Guru, Operasional Piket, atau Siswa.
- Menu dan dashboard menyesuaikan hak akses tiap role.
- Dashboard Guru menampilkan statistik kedisiplinan dan operasional, termasuk visualisasi.
- Dashboard Operasional merangkum jadwal dan presensi serta akses pencatatan penghargaan pagi.
- Dashboard Siswa menampilkan ringkasan poin, perkembangan, riwayat, notifikasi, dan akses pengajuan keberatan.

### 5.2 Manajemen akun dan data siswa

- Guru mengelola data siswa melalui operasi CRUD dengan modal Bootstrap.
- Data siswa meliputi NIS, NISN, nama, jenis kelamin, tingkat, jurusan, rombongan belajar, dan status aktif.
- Mendukung pencarian, filter, pagination, aktivasi, dan deaktivasi.
- Mendukung impor/ekspor CSV dan Excel, dengan validasi duplikasi serta laporan kesalahan per baris.
- NIS disimpan dan diproses sebagai string numerik agar angka nol di depan tidak hilang.

### 5.3 Kedisiplinan

- Guru mencatat serta mengelola pelanggaran dan keterlambatan.
- Pencatatan terhubung dengan ledger, sedangkan edit/hapus menghasilkan transaksi koreksi.
- Riwayat mendukung filter tanggal, bulan, kelas, dan kategori/jenis yang relevan.

### 5.4 Penghargaan pagi dan poin tambahan

- Guru dan Operasional Piket yang berwenang mencatat penghargaan pagi dengan validasi waktu, peringkat, dan batas jumlah.
- Guru memberikan poin tambahan berupa bilangan bulat positif beserta alasan.
- Perubahan dan penghapusan data yang berpengaruh pada poin menghasilkan koreksi ledger dan audit.

### 5.5 Piket gerbang

- Jadwal berlaku Senin sampai Jumat, dengan 4–6 anggota per hari.
- Sistem pembagian otomatis (gacha) mengupayakan pembagian yang adil, dengan selisih maksimal satu hari tugas antaranggota dalam satu minggu.
- Hasil gacha ditampilkan sebagai pratinjau dan dapat disunting sebelum dikonfirmasi.
- Penggantian anggota memiliki status `pending`, `approved`, atau `rejected`; riwayat anggota awal tetap dipertahankan.
- Presensi memvalidasi keanggotaan jadwal atau pengganti yang disetujui serta mencegah presensi/poin ganda.
- Kehadiran anggota yang sah menghasilkan +10 poin secara atomik.
- Rekap presensi dapat ditinjau berdasarkan periode.

### 5.6 Pengajuan dan peninjauan keberatan

- Siswa hanya dapat mengajukan keberatan atas transaksi miliknya sendiri, dengan alasan dan bukti privat maksimal 5 MB.
- Pengajuan dimulai dengan status `pending`.
- Guru meninjau dan menetapkan status `approved` atau `rejected`, disertai alasan keputusan.
- Keberatan yang disetujui dikoreksi melalui transaksi ledger baru; riwayat transaksi awal tetap tersedia.
- Status keputusan ditampilkan kepada siswa dan dapat memicu notifikasi internal.

### 5.7 Notifikasi dan Activity Log

- Notifikasi internal mencakup kejadian kedisiplinan, pemberian poin, absensi, keputusan keberatan, rilis jadwal, dan keputusan penggantian.
- Pengguna dapat melihat notifikasi, jumlah notifikasi belum dibaca, serta menandai notifikasi sebagai dibaca satu per satu atau sekaligus.
- Activity Log menyimpan aktor dan role, tindakan, kategori data, ID terkait, nilai sebelum/sesudah, serta waktu kejadian.
- Log bersifat imutabel melalui antarmuka aplikasi dan hanya dapat diakses Guru dengan pencarian, filter, serta pagination.

### 5.8 Laporan dan ekspor

- Laporan dapat difilter menurut kelas, jurusan, bulan, tahun, semester, kategori poin, jenis transaksi, serta rentang tanggal.
- Data dipisahkan secara eksplisit per kelas, termasuk kelas paralel.
- Setiap kategori menampilkan frekuensi kejadian dan akumulasi poin, misalnya **“5 kali (−25 poin)”**. Kategori tanpa transaksi ditampilkan sebagai tanda `-`.
- Laporan menyertakan rekapitulasi absensi piket.
- Mendukung ekspor CSV yang kompatibel dengan Excel dan dokumen PDF menggunakan Dompdf dengan tata letak siap cetak.

## 6. Kebutuhan Nonfungsional

- **Integritas data:** transaksi poin dan perubahan status terkait harus konsisten dan atomik.
- **Keamanan:** validasi input, pemeriksaan otorisasi server-side, pembatasan akses data pribadi, dan penyimpanan bukti secara privat.
- **Auditabilitas:** tindakan penting dapat ditelusuri tanpa mengubah riwayat.
- **Kegunaan:** antarmuka responsif dan menyajikan navigasi sesuai role.
- **Kompatibilitas:** PostgreSQL sebagai basis data utama; ekspor CSV dapat dibuka dengan aplikasi spreadsheet yang kompatibel.
- **Waktu:** aturan yang bergantung waktu menggunakan zona waktu operasional Asia/Jakarta.

## 7. Kriteria Penerimaan

1. Pengguna hanya dapat mengakses route dan data yang diizinkan role/permission-nya.
2. Siswa tidak dapat melihat profil, transaksi, atau keberatan milik siswa lain.
3. Nilai transaksi sesuai aturan poin dan tersimpan bersama sumber serta pencatatnya.
4. Duplikasi keterlambatan, penghargaan, peringkat, atau presensi yang dilarang ditolak dengan pesan yang jelas.
5. Perubahan data yang memengaruhi poin menghasilkan entri koreksi baru dan tidak menghapus riwayat lama.
6. Jadwal dan penggantian mengikuti validasi anggota, persetujuan, batas waktu, dan proses pratinjau.
7. Filter laporan menghasilkan kelompok kelas yang benar serta frekuensi, akumulasi poin, dan rekap absensi yang konsisten.
8. Ekspor PDF menghasilkan berkas PDF valid; CSV mempertahankan format data dan dapat dibuka sebagai lembar kerja.
9. Notifikasi dan Activity Log tersimpan serta mematuhi pembatasan akses masing-masing.

## 8. Pengujian dan Status Proyek

### Standar verifikasi

- Jalankan suite unit dan feature dengan `php artisan test`.
- Pastikan seluruh pengujian lulus tanpa kegagalan.
- Jalankan `vendor/bin/pint --test` untuk memverifikasi format kode PHP.

### Hasil verifikasi terakhir yang tercatat

- `php artisan test`: **83 tes lulus, 664 assertions**.
- `vendor/bin/pint --test`: **lulus**.
- Pengujian laporan mencakup filter, rekap frekuensi/poin, absensi piket, ekspor CSV, dan keluaran PDF Dompdf.

Status keseluruhan proyek dicatat **Selesai 100% (Batch 1–12)** sesuai status proyek yang ditetapkan untuk dokumen ini.
