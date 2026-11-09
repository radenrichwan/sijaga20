# 🛡️ SIJAGA 20 — Sistem Informasi Kedisiplinan, Pelanggaran, dan Penghargaan Siswa

**SIJAGA 20** adalah aplikasi manajemen kedisiplinan dan poin siswa berbasis web yang dikembangkan khusus untuk **SMKN 20 Jakarta**. Aplikasi ini dirancang untuk mencatat poin pelanggaran, keterlambatan, absensi piket, serta penghargaan siswa secara transparan, terintegrasi, dan *auditable*.

---

## 📌 Fitur Utama

- **Pencatatan Poin & Transaksi Real-time**: Mengatur saldo poin awal siswa (100 poin) dengan penambahan/pengurangan otomatis berdasarkan kategori.
- **Role & Hak Akses Berkelompok**:
  - 👨‍🏫 **Guru**: Akses kelola kategori poin, pantau laporan kedisiplinan, dan verifikasi/persetujuan pengajuan keberatan.
  - 📋 **Petugas Piket**: Pencatatan keterlambatan harian, absensi jadwal piket, dan pemberian penghargaan pagi.
  - 👨‍🎓 **Siswa**: Dashboard pribadi untuk memantau saldo poin, riwayat transaksi kedisiplinan, dan pengajuan keberatan poin.
- **Audit Trail & Activity Log**: Mencatat jejak audit seluruh aktivitas transaksi secara detail (Aktor, Waktu bertingkat, Tindakan, Nilai Sebelum & Sesudah).
- **Pengajuan Keberatan Poin**: Fitur transparansi bagi siswa untuk mengajukan keberatan terhadap sanksi/poin dengan sistem persetujuan guru.
- **Export Laporan & Cetak Transkrip**: Generasi laporan kedisiplinan dan transkrip poin siswa.

---

## 🛠️ Tech Stack & Dependencies

- **Framework**: Laravel 11 / PHP 8.5
- **Database**: PostgreSQL
- **Frontend**: Blade Templating, Bootstrap 5, Select2, Alpine.js
- **Build Tool**: Vite & Node.js
- **Testing**: PHPUnit / Laravel Dusk (90 Tests / 754 Assertions Lulus 100%)

---

## 🚀 Cara Menjalankan Proyek (Local Development)

### 1. Clone Repository
```bash
git clone [https://github.com/radenrichwan/sijaga20.git](https://github.com/radenrichwan/sijaga20.git)
cd sijaga20