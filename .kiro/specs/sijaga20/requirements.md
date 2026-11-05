# Requirements Document

## Introduction

SIJAGA 20 (Sistem Informasi Jaga Gerbang dan Kedisiplinan Siswa) adalah aplikasi web untuk SMKN 20 Jakarta yang mendigitalisasi pencatatan kedisiplinan siswa dan pengelolaan kegiatan piket gerbang. Sistem ini menggantikan pencatatan manual dengan platform terpusat yang dapat diakses oleh tiga kelompok pengguna: guru, siswa, dan anggota OSIS/MPK.

Fitur utama meliputi manajemen data siswa, pencatatan pelanggaran dan keterlambatan, sistem poin kumulatif berbasis ledger, penghargaan kedatangan pagi, penjadwalan dan absensi piket gerbang, pengajuan keberatan siswa, laporan kedisiplinan, notifikasi internal, dan Activity Log yang tidak dapat diubah.

## Glossary

- **Sistem**: Aplikasi web SIJAGA 20 secara keseluruhan
- **Guru**: Pengguna dengan hak akses administrasi penuh; menggunakan akun guru0001, guru0002, atau guru0003
- **Siswa**: Pengguna yang login menggunakan NIS sebagai username dan kata sandi awal
- **Anggota_OSIS**: Siswa yang juga menjabat sebagai anggota OSIS/MPK; memiliki akun siswa pribadi dan akun operasional
- **Akun_Operasional**: Akun khusus piket001 hingga piket006 yang digunakan anggota OSIS/MPK saat bertugas; seluruh Akun_Operasional dapat membuat, melihat, mengedit, dan menghapus data Penghargaan_Pagi, sedangkan akun yang ditunjuk sebagai Pengurus OSIS/MPK juga diberikan permission `can_manage_schedule` untuk membuat dan mengelola jadwal piket
- **can_manage_schedule**: Permission yang diberikan kepada Akun_Operasional tertentu yang ditunjuk sebagai Pengurus OSIS/MPK; permission ini dikelola oleh Guru dan diperiksa di sisi server melalui middleware atau policy Laravel
- **NIS**: Nomor Induk Siswa; berfungsi sebagai username login siswa sekaligus kata sandi awal, terdiri dari 5–20 digit angka dan disimpan sebagai VARCHAR atau tipe teks setara agar angka nol di depan tidak hilang
- **NISN**: Nomor Induk Siswa Nasional; identitas alternatif untuk pencarian, disimpan sebagai string
- **Poin**: Nilai bersih kumulatif Siswa yang dihitung dengan menjumlahkan nilai bertanda (positif atau negatif) dari seluruh entri Ledger_Poin milik Siswa
- **Ledger_Poin**: Tabel pencatatan seluruh transaksi penambahan dan pengurangan poin siswa; setiap perubahan dicatat sebagai entri baru yang berstatus aktif; pembatalan dilakukan dengan menambahkan counter-entry baru berstatus aktif yang nilainya merupakan kebalikan aritmetika dari transaksi yang dibatalkan; entri yang sudah ada tidak pernah diubah atau dihapus
- **Pelanggaran**: Catatan perilaku siswa yang melanggar tata tertib, bernilai -5 poin per kejadian
- **Keterlambatan**: Catatan siswa yang tiba di sekolah pada pukul 06.31 WIB atau lebih lambat, bernilai -5 poin per kejadian; maksimal satu catatan per siswa per tanggal kalender
- **Penghargaan_Pagi**: Catatan untuk 10 siswa yang tiba paling awal pada rentang pukul 04.00–05.30 WIB pada hari tertentu, bernilai +15 poin per kejadian; menyimpan waktu kedatangan dan peringkat
- **Piket_Gerbang**: Kegiatan tugas jaga gerbang yang dilakukan oleh anggota OSIS/MPK sesuai jadwal Senin–Jumat
- **Poin_Tambahan**: Poin prestasi atau kontribusi khusus yang diberikan Guru di luar kategori standar; jumlahnya ditentukan oleh Guru dan selalu berupa bilangan bulat positif
- **Jadwal_Piket**: Daftar penugasan anggota OSIS/MPK per hari Senin–Jumat dalam satu minggu, dengan 4–6 anggota per hari; jadwal yang belum berlaku dapat dibatalkan (status "Dibatalkan"), sedangkan jadwal yang sudah pernah berlaku tidak dapat dibatalkan atau dihapus
- **Pergantian_Anggota**: Pengajuan penggantian anggota OSIS/MPK yang terjadwal dengan anggota lain beserta alasannya; memiliki status "Menunggu Persetujuan", "Disetujui", atau "Ditolak"; hanya Pergantian_Anggota berstatus "Disetujui" yang berlaku untuk validasi kehadiran
- **Keberatan**: Pengajuan oleh siswa untuk meminta pemeriksaan catatan yang dianggap tidak sesuai
- **Activity_Log**: Catatan tidak dapat diubah dari seluruh aksi penting dalam sistem
- **Notifikasi**: Pemberitahuan internal di dalam aplikasi untuk pengguna terkait

---

## Requirements

### Aturan Bisnis Perhitungan Poin

1. Pelanggaran mengurangi poin Siswa sebesar 5 poin per kejadian, sehingga transaksi Ledger_Poin bernilai **-5**.
2. Keterlambatan mengurangi poin Siswa sebesar 5 poin per kejadian, sehingga transaksi Ledger_Poin bernilai **-5**.
3. Penghargaan_Pagi menambah poin Siswa sebesar 15 poin per kejadian, sehingga transaksi Ledger_Poin bernilai **+15**.
4. Poin_Tambahan untuk prestasi atau kontribusi khusus dan poin kehadiran Piket_Gerbang menambah poin Siswa; transaksi masing-masing harus bernilai positif. Jumlah Poin_Tambahan ditentukan oleh Guru.
5. Total poin adalah jumlah aritmetika seluruh nilai transaksi Ledger_Poin milik Siswa. Nilai negatif mengurangi total dan nilai positif menambah total; setiap transaksi asli maupun counter-entry dihitung tepat satu kali.
6. Untuk membatalkan transaksi, Sistem membuat counter-entry aktif sebesar nilai transaksi asli dengan tanda berlawanan. Jika sumber poin diubah, Sistem kemudian membuat transaksi baru dengan nilai sesuai aturan kategori dan data terbaru. Entri Ledger_Poin yang telah dibuat tidak diubah atau dihapus.

### Requirement 1: Manajemen Akun Pengguna

**User Story:** Sebagai guru, saya ingin mengelola akun seluruh pengguna sistem, agar akses ke SIJAGA 20 dapat dikendalikan dan dipantau.

#### Acceptance Criteria

1. THE Sistem SHALL menyediakan tepat tiga role pengguna: Guru, Siswa, dan Akun_Operasional, di mana setiap pengguna ditetapkan ke tepat satu role.
2. THE Sistem SHALL menyediakan tepat tiga akun Guru (guru0001, guru0002, guru0003) yang masing-masing memiliki hak akses Guru yang identik mencakup: manajemen akun pengguna, manajemen seluruh data catatan, manajemen jadwal piket, dan manajemen laporan.
3. THE Sistem SHALL menyediakan maksimal enam Akun_Operasional dengan username piket001, piket002, piket003, piket004, piket005, dan piket006; seluruh Akun_Operasional dapat mencatat kehadiran piket dan memiliki hak membuat, melihat, mengedit, serta menghapus seluruh data Penghargaan_Pagi tanpa dibatasi oleh akun pembuat; Akun_Operasional yang diberikan permission `can_manage_schedule` oleh Guru juga dapat membuat dan mengelola jadwal piket.
4. WHEN seorang Guru membuat akun Siswa baru, THE Sistem SHALL memvalidasi bahwa NIS terdiri dari 5 hingga 20 digit angka, disimpan sebagai teks untuk mempertahankan angka nol di depan, dan bersifat unik di antara seluruh data Siswa sebelum menyimpan data.
5. IF seorang Guru mencoba membuat akun Siswa baru dengan NIS yang sudah terdaftar, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan error yang mengindikasikan NIS tersebut sudah digunakan.
6. WHEN seorang Guru menonaktifkan akun Siswa, THE Sistem SHALL mengubah status siswa menjadi tidak aktif dan mencegah login dengan akun tersebut.
7. IF seorang Guru mencoba menghapus akun Guru terakhir yang aktif, THEN THE Sistem SHALL menolak tindakan tersebut dan menampilkan pesan bahwa minimal satu akun Guru harus tetap ada.
8. WHEN seorang Guru membuat, mengubah, atau menonaktifkan akun pengguna, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas pengguna, jenis aksi, data sebelum, dan data sesudah perubahan.
9. WHEN seorang Guru memulai impor data Siswa dari file CSV atau Excel dengan ukuran maksimal 5 MB, THE Sistem SHALL memvalidasi seluruh baris terlebih dahulu dan hanya menyimpan data jika tidak ada NIS duplikat di dalam file maupun dengan data yang sudah ada; IF terdapat duplikat, THEN THE Sistem SHALL menolak seluruh impor dan menampilkan daftar baris yang menyebabkan duplikasi.
10. WHEN seorang Guru memilih format CSV atau Excel dan meminta ekspor data Siswa, THE Sistem SHALL menghasilkan file dalam format yang dipilih Guru dan menyediakan file tersebut untuk diunduh; IF Guru belum memilih format, THEN THE Sistem SHALL tidak menghasilkan file dan meminta Guru memilih format terlebih dahulu.
11. WHEN seorang Guru memberikan permission `can_manage_schedule` kepada sebuah Akun_Operasional, THE Sistem SHALL menampilkan dialog konfirmasi sebelum menyimpan; WHEN Guru mengkonfirmasi, THE Sistem SHALL mengaktifkan permission tersebut secara langsung dan segera berlaku untuk Akun_Operasional yang bersangkutan.
12. WHEN seorang Guru mencabut permission `can_manage_schedule` dari sebuah Akun_Operasional, THE Sistem SHALL menampilkan dialog konfirmasi sebelum menyimpan; WHEN Guru mengkonfirmasi, THE Sistem SHALL menonaktifkan permission tersebut secara langsung dan segera berlaku, sehingga akun tersebut tidak lagi dapat mengakses fitur pengelolaan jadwal piket.
13. IF seorang Guru mencoba memberikan permission `can_manage_schedule` kepada akun dengan role selain Akun_Operasional, THEN THE Sistem SHALL menolak tindakan tersebut dan menampilkan pesan bahwa permission hanya dapat diberikan kepada Akun_Operasional.
14. THE Sistem SHALL menampilkan status permission `can_manage_schedule` secara eksplisit pada halaman detail dan daftar akun Akun_Operasional.
15. WHEN seorang Guru memberikan atau mencabut permission `can_manage_schedule`, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas Guru, identitas Akun_Operasional yang terdampak, jenis perubahan permission (diberikan atau dicabut), dan waktu kejadian.
16. IF Akun_Operasional yang permission `can_manage_schedule`-nya baru saja dicabut sedang dalam sesi aktif, THE Sistem SHALL memblokir akses ke seluruh rute pengelolaan jadwal piket pada permintaan berikutnya tanpa perlu logout.
17. THE Sistem SHALL memastikan bahwa fitur ganti kata sandi mandiri untuk Siswa, Guru, dan Akun_Operasional tidak termasuk dalam ruang lingkup versi pertama SIJAGA 20 dan tidak tersedia di antarmuka aplikasi; perubahan kata sandi melalui proses administrasi yang diizinkan tetap dapat dilakukan sesuai Requirement 16.

---

### Requirement 2: Data Profil Siswa

**User Story:** Sebagai guru, saya ingin mengelola data lengkap setiap siswa, agar informasi siswa terpusat dan akurat.

#### Acceptance Criteria

1. THE Sistem SHALL menyimpan data Siswa yang mencakup bidang-bidang berikut: NIS (VARCHAR atau tipe teks setara, 5–20 digit angka, unik, mempertahankan angka nol di depan), NISN (string 10 digit numerik), nama lengkap (string 1–100 karakter), jenis kelamin (nilai terbatas: Laki-laki atau Perempuan), tingkat kelas (nilai terbatas: X, XI, atau XII), jurusan/program keahlian (string 1–100 karakter), rombongan belajar (string 1–20 karakter), dan status (nilai terbatas: aktif atau tidak aktif); tanggal dibuat dan tanggal diperbarui diisi otomatis oleh sistem.
2. WHEN seorang Guru menyimpan data Siswa dengan NIS yang sudah terdaftar, THE Sistem SHALL menolak penyimpanan dan menampilkan pesan yang menyatakan NIS tersebut telah digunakan.
3. WHEN seorang Guru menyimpan data Siswa dengan NISN yang sudah terdaftar, THE Sistem SHALL menampilkan peringatan yang mencantumkan nama seluruh Siswa yang memiliki NISN tersebut dan meminta konfirmasi sebelum melanjutkan penyimpanan.
4. IF seorang Guru membatalkan konfirmasi duplikasi NISN, THEN THE Sistem SHALL membatalkan penyimpanan dan mengembalikan Guru ke formulir dengan data yang telah dimasukkan tetap tersimpan di formulir.
5. WHEN seorang Guru melakukan pencarian Siswa menggunakan NIS, NISN, nama lengkap, kelas, atau jurusan, THE Sistem SHALL menampilkan seluruh hasil yang cocok secara parsial dan tidak peka huruf besar-kecil; IF tidak ada hasil yang ditemukan, THEN THE Sistem SHALL menampilkan pesan bahwa tidak ada Siswa yang sesuai dengan kriteria pencarian.

---

### Requirement 3: Pencatatan Pelanggaran

**User Story:** Sebagai guru, saya ingin mencatat pelanggaran siswa, agar setiap pelanggaran terdokumentasi dan berdampak pada poin siswa.

#### Acceptance Criteria

1. WHEN seorang Guru menyimpan catatan Pelanggaran baru untuk seorang Siswa, THE Sistem SHALL membuat entri Pelanggaran dan secara otomatis membuat transaksi Ledger_Poin sebesar -5 poin untuk Siswa tersebut dalam satu operasi atomik; IF salah satu gagal, THEN keduanya dibatalkan.
2. WHEN seorang Guru mengubah catatan Pelanggaran yang ada, THE Sistem SHALL memperbarui data entri Pelanggaran, membuat counter-entry baru di Ledger_Poin sebesar +5 poin (berstatus aktif) untuk membatalkan transaksi lama, dan membuat transaksi Ledger_Poin baru sebesar -5 poin (berstatus aktif), seluruhnya dalam satu operasi atomik; entri Ledger_Poin lama tidak diubah atau dihapus.
3. WHEN seorang Guru menghapus catatan Pelanggaran, THE Sistem SHALL menghapus entri Pelanggaran dan membuat counter-entry baru di Ledger_Poin sebesar +5 poin (berstatus aktif) untuk membatalkan transaksi terkait, dalam satu operasi atomik; entri Ledger_Poin asli tidak diubah atau dihapus.
4. THE Sistem SHALL menyimpan setiap Pelanggaran dengan data: NIS Siswa, tanggal kejadian, jenis pelanggaran (dipilih dari daftar terkontrol), deskripsi pelanggaran (teks bebas hingga 500 karakter), dan identitas Guru yang mencatat.
5. IF seorang Guru mencoba menyimpan catatan Pelanggaran dengan NIS Siswa yang tidak ditemukan, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa NIS tersebut tidak terdaftar.
6. WHEN seorang Guru membuat, mengubah, atau menghapus catatan Pelanggaran, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas Guru, ID catatan Pelanggaran terdampak, ID seluruh transaksi Ledger_Poin terkait (termasuk counter-entry dan transaksi baru jika ada), dan waktu kejadian.
7. WHEN catatan Pelanggaran baru berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk Siswa yang bersangkutan.

---

### Requirement 4: Pencatatan Keterlambatan

**User Story:** Sebagai guru, saya ingin mencatat keterlambatan siswa, agar setiap keterlambatan terdokumentasi dan berdampak pada poin siswa.

#### Acceptance Criteria

1. THE Sistem SHALL mendefinisikan keterlambatan sebagai kondisi siswa yang tiba di sekolah pada pukul 06.31 WIB atau lebih lambat; siswa yang tiba pada pukul 06.30 WIB atau lebih awal tidak dianggap terlambat.
2. WHEN seorang Guru menyimpan atau mengubah catatan Keterlambatan untuk seorang Siswa, THE Sistem SHALL memvalidasi bahwa waktu tiba yang dimasukkan adalah pukul 06.31 WIB atau lebih lambat; IF waktu tiba yang dimasukkan adalah pukul 06.30 WIB atau lebih awal, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa waktu tersebut bukan termasuk keterlambatan.
3. THE Sistem SHALL memastikan hanya satu catatan Keterlambatan yang dapat disimpan per Siswa per tanggal kalender; IF Guru mencoba menyimpan catatan Keterlambatan kedua untuk Siswa yang sama pada tanggal yang sama, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa catatan keterlambatan untuk Siswa ini pada tanggal tersebut sudah ada.
4. WHEN seorang Guru menyimpan catatan Keterlambatan baru untuk seorang Siswa, THE Sistem SHALL membuat entri Keterlambatan dan secara otomatis membuat transaksi Ledger_Poin sebesar -5 poin untuk Siswa tersebut dalam satu operasi atomik; IF salah satu gagal, THEN keduanya dibatalkan.
5. WHEN seorang Guru mengubah catatan Keterlambatan yang ada, THE Sistem SHALL memperbarui data entri Keterlambatan, membuat counter-entry baru di Ledger_Poin sebesar +5 poin (berstatus aktif) untuk membatalkan transaksi lama, dan membuat transaksi Ledger_Poin baru sebesar -5 poin (berstatus aktif), seluruhnya dalam satu operasi atomik; entri Ledger_Poin lama tidak diubah atau dihapus.
6. WHEN seorang Guru menghapus catatan Keterlambatan, THE Sistem SHALL menghapus entri Keterlambatan dan membuat counter-entry baru di Ledger_Poin sebesar +5 poin (berstatus aktif) untuk membatalkan transaksi terkait, dalam satu operasi atomik; entri Ledger_Poin asli tidak diubah atau dihapus.
7. THE Sistem SHALL menyimpan setiap Keterlambatan dengan data: NIS Siswa, tanggal kejadian, waktu tiba (jam:menit WIB), dan identitas Guru yang mencatat.
8. WHERE durasi keterlambatan disertakan secara opsional oleh Guru, THE Sistem SHALL memvalidasi bahwa nilai durasi adalah bilangan bulat positif dalam satuan menit dengan rentang 1 hingga 999; IF durasi tidak disertakan, THE Sistem SHALL tetap menyimpan catatan Keterlambatan tanpa nilai durasi.
9. IF seorang Guru mencoba menyimpan catatan Keterlambatan dengan NIS Siswa yang tidak ditemukan, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa NIS tersebut tidak terdaftar.
10. WHEN seorang Guru membuat, mengubah, atau menghapus catatan Keterlambatan, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas Guru, ID catatan terdampak, dan waktu kejadian.
11. WHEN catatan Keterlambatan baru berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk Siswa yang bersangkutan.

---

### Requirement 5: Penghargaan Kedatangan Pagi

**User Story:** Sebagai Guru atau anggota OSIS/MPK melalui Akun_Operasional, saya ingin mencatat dan mengelola penghargaan untuk 10 siswa yang paling awal tiba setiap hari, agar siswa berprestasi dalam kedisiplinan mendapat poin.

#### Acceptance Criteria

1. WHEN seorang Guru atau Akun_Operasional memasukkan secara manual catatan Penghargaan_Pagi untuk seorang Siswa, THE Sistem SHALL memvalidasi bahwa waktu kedatangan yang dimasukkan berada dalam rentang 04.00–05.30 WIB.
2. IF waktu kedatangan yang dimasukkan berada di luar rentang 04.00–05.30 WIB, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa waktu kedatangan harus berada dalam rentang 04.00–05.30 WIB.
3. WHEN catatan Penghargaan_Pagi berhasil divalidasi, THE Sistem SHALL memvalidasi bahwa peringkat yang dimasukkan berada dalam rentang 1 hingga 10; IF peringkat berada di luar rentang tersebut, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa peringkat harus berada dalam rentang 1–10; WHEN peringkat valid, THE Sistem SHALL menyimpan entri dengan data: NIS Siswa, tanggal, waktu kedatangan (jam:menit WIB), peringkat (1–10), dan identitas akun yang mencatat; serta secara otomatis membuat transaksi Ledger_Poin sebesar +15 poin untuk Siswa tersebut dalam satu operasi atomik; IF salah satu gagal, THEN seluruh operasi dibatalkan.
4. IF seorang Guru atau Akun_Operasional mencoba menyimpan lebih dari 10 catatan Penghargaan_Pagi untuk tanggal kalender yang sama, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa batas 10 siswa per hari telah tercapai.
5. IF seorang Guru atau Akun_Operasional mencoba menyimpan catatan Penghargaan_Pagi untuk Siswa yang sama pada tanggal kalender yang sama, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan duplikasi.
6. IF seorang Guru atau Akun_Operasional mencoba menyimpan catatan Penghargaan_Pagi dengan peringkat yang sudah digunakan oleh Siswa lain pada tanggal kalender yang sama, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan bahwa peringkat tersebut sudah digunakan oleh Siswa lain pada tanggal yang sama.
7. WHEN seorang Guru atau Akun_Operasional menghapus catatan Penghargaan_Pagi, THE Sistem SHALL menghapus entri tersebut tanpa mengubah atau menghapus riwayat transaksi Ledger_Poin dan membuat counter-entry baru di Ledger_Poin sebesar -15 poin (berstatus aktif) untuk membatalkan transaksi poin +15 terkait, dalam satu operasi atomik.
8. WHEN seorang Guru atau Akun_Operasional mengedit catatan Penghargaan_Pagi yang sudah ada, THE Sistem SHALL memvalidasi kembali waktu kedatangan sesuai Kriteria 1–2, peringkat sesuai batas pada Kriteria 3, batas maksimal 10 siswa per tanggal sesuai Kriteria 4, keunikan Siswa per tanggal sesuai Kriteria 5, dan keunikan peringkat per tanggal sesuai Kriteria 6. Dalam pemeriksaan duplikasi dan batas maksimal, THE Sistem SHALL mengecualikan catatan yang sedang diedit. Proses validasi ini hanya memeriksa aturan dan tidak boleh membuat transaksi poin baru. IF seluruh validasi berhasil, THEN THE Sistem SHALL membuat satu counter-entry baru di Ledger_Poin sebesar -15 poin (berstatus aktif) untuk membatalkan transaksi lama, membuat satu transaksi Ledger_Poin baru sebesar +15 poin (berstatus aktif), dan memperbarui data Penghargaan_Pagi, seluruhnya dalam satu operasi atomik. IF salah satu proses gagal, THEN seluruh perubahan dibatalkan. Jika validasi gagal, THE Sistem SHALL menolak perubahan dan mempertahankan data serta transaksi yang sudah ada.
9. WHEN seorang Guru atau Akun_Operasional membuat, mengedit, atau menghapus catatan Penghargaan_Pagi, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas aktor, waktu kejadian, ID catatan terdampak, ID seluruh transaksi Ledger_Poin terkait (termasuk counter-entry dan transaksi baru jika ada), serta data sebelum dan sesudah perubahan; THE Sistem SHALL tetap menyimpan riwayat transaksi Ledger_Poin ketika data Penghargaan_Pagi dihapus.
10. WHEN catatan Penghargaan_Pagi baru berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk Siswa yang bersangkutan.
11. THE Sistem SHALL memberikan kepada Guru dan seluruh Akun_Operasional hak untuk melihat, membuat, mengedit, dan menghapus seluruh data Penghargaan_Pagi tanpa membatasi pengeditan atau penghapusan berdasarkan identitas akun pembuat data.

---

### Requirement 6: Poin Tambahan dari Guru

**User Story:** Sebagai guru, saya ingin memberikan poin tambahan kepada siswa di luar kategori standar, agar prestasi atau kontribusi khusus siswa dapat diapresiasi.

#### Acceptance Criteria

1. WHEN seorang Guru menyimpan Poin_Tambahan baru untuk seorang Siswa, THE Sistem SHALL membuat entri Poin_Tambahan dan secara otomatis membuat transaksi Ledger_Poin dengan jumlah poin positif yang ditentukan Guru dalam satu operasi atomik; IF salah satu gagal, THEN keduanya dibatalkan.
2. THE Sistem SHALL memvalidasi bahwa jumlah poin pada Poin_Tambahan adalah bilangan bulat positif lebih besar dari nol; IF nilai adalah nol, negatif, atau bukan bilangan bulat, THEN THE Sistem SHALL menolak penyimpanan dan menampilkan pesan validasi.
3. THE Sistem SHALL menyimpan setiap Poin_Tambahan dengan data: NIS Siswa, jumlah poin, tanggal, alasan pemberian (1–255 karakter), dan identitas Guru yang memberikan.
4. WHEN seorang Guru mengubah catatan Poin_Tambahan milik Siswa mana pun (termasuk catatan yang dibuat oleh Guru lain), THE Sistem SHALL memperbarui data entri Poin_Tambahan, membuat counter-entry baru di Ledger_Poin dengan nilai kebalikan dari poin asli (berstatus aktif), dan membuat transaksi Ledger_Poin baru sesuai nilai poin yang baru (berstatus aktif), seluruhnya dalam satu operasi atomik; entri Ledger_Poin lama tidak diubah atau dihapus.
5. WHEN seorang Guru menghapus catatan Poin_Tambahan, THE Sistem SHALL menghapus entri dan membuat counter-entry baru di Ledger_Poin dengan nilai kebalikan dari poin asli (berstatus aktif) untuk membatalkan transaksi terkait, dalam satu operasi atomik; entri Ledger_Poin asli tidak diubah atau dihapus.
6. WHEN seorang Guru membuat, mengubah, atau menghapus catatan Poin_Tambahan, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas Guru yang melakukan perubahan, ID catatan terdampak, jumlah poin, dan waktu kejadian.
7. WHEN catatan Poin_Tambahan baru berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk Siswa yang bersangkutan.

---

### Requirement 7: Sistem Ledger Poin

**User Story:** Sebagai guru, saya ingin seluruh perubahan poin siswa tercatat sebagai transaksi tersendiri, agar riwayat poin dapat ditelusuri dan total poin selalu akurat.

#### Acceptance Criteria

1. WHEN terjadi penambahan atau pengurangan poin pada seorang Siswa, THE Sistem SHALL mencatat peristiwa tersebut sebagai entri baru di Ledger_Poin dengan data: NIS Siswa, jumlah poin bertanda, kategori sumber (pelanggaran, keterlambatan, penghargaan_pagi, piket_gerbang, atau poin_tambahan), ID referensi sumber, tanggal dan waktu, dan identitas aktor yang memicu perubahan; seluruh entri Ledger_Poin yang dibuat berstatus aktif. Nilai transaksi SHALL mengikuti aturan berikut: pelanggaran -5, keterlambatan -5, penghargaan_pagi +15, piket_gerbang bernilai positif, dan poin_tambahan bernilai positif sesuai jumlah yang diberikan Guru.
2. THE Sistem SHALL menghitung total Poin Siswa sebagai penjumlahan aritmetika nilai bertanda seluruh entri Ledger_Poin milik Siswa tersebut, termasuk transaksi asli dan counter-entry; setiap ID transaksi dihitung tepat satu kali. Transaksi negatif mengurangi total dan transaksi positif menambah total.
3. WHEN entri sumber (pelanggaran, keterlambatan, penghargaan_pagi, piket_gerbang, atau poin_tambahan) dihapus, THE Sistem SHALL membuat counter-entry baru di Ledger_Poin dengan nilai kebalikan dari transaksi asli dan berstatus aktif; entri asli tidak diubah atau dihapus dan tetap tersimpan dengan nilai dan statusnya semula.
4. WHEN entri sumber diubah, THE Sistem SHALL membuat counter-entry baru di Ledger_Poin dengan nilai kebalikan dari transaksi lama (berstatus aktif) dan membuat transaksi baru yang mencerminkan nilai sesuai aturan kategori dan data yang telah diperbarui (berstatus aktif); entri asli tidak diubah atau dihapus; seluruh operasi dilakukan secara atomik.
5. THE Sistem SHALL mengizinkan total Poin Siswa bernilai positif, nol, maupun negatif tanpa batas minimum atau maksimum.
6. THE Sistem SHALL memastikan entri Ledger_Poin tidak dapat diubah atau dihapus secara langsung melalui antarmuka aplikasi oleh pengguna mana pun; koreksi hanya dilakukan melalui penambahan counter-entry baru.

#### Kasus Penggunaan: Perhitungan dan Koreksi Poin

1. **Mencatat pelanggaran atau keterlambatan:** Guru menyimpan catatan; Sistem membuat transaksi **-5 poin**. Jika catatan diubah, Sistem membatalkan transaksi lama dengan counter-entry **+5 poin**, lalu membuat transaksi baru **-5 poin**. Jika catatan dihapus, Sistem membuat counter-entry **+5 poin** sehingga dampak transaksi pelanggaran atau keterlambatan tersebut menjadi nol.
2. **Memberikan penghargaan pagi:** Guru menyimpan catatan Penghargaan_Pagi yang valid; Sistem membuat transaksi **+15 poin**. Jika catatan diubah atau dihapus, Sistem membatalkan transaksi lama dengan counter-entry **-15 poin**; untuk perubahan, Sistem juga membuat transaksi baru **+15 poin**.
3. **Memberikan poin prestasi atau piket:** Poin_Tambahan hanya dapat diberikan dengan nilai positif yang ditentukan Guru. Kehadiran piket yang valid menghasilkan transaksi **+10 poin** untuk akun pribadi Siswa yang hadir.
4. **Melihat total poin:** Sistem menjumlahkan seluruh transaksi bertanda, termasuk counter-entry. Sebagai contoh, transaksi pelanggaran **-5** yang dibatalkan oleh **+5** menghasilkan dampak bersih **0**, sedangkan penghargaan **+15** yang dibatalkan oleh **-15** juga menghasilkan dampak bersih **0**.

---

### Requirement 8: Penjadwalan Piket Gerbang

**User Story:** Sebagai pengurus OSIS/MPK, saya ingin membuat dan mengelola jadwal piket gerbang mingguan, agar penugasan anggota terdistribusi merata dan tercatat dengan jelas.

#### Acceptance Criteria

1. WHEN Akun_Operasional yang memiliki permission `can_manage_schedule` atau seorang Guru membuat Jadwal_Piket baru, THE Sistem SHALL memungkinkan penugasan 4 hingga 6 anggota OSIS/MPK per hari untuk hari Senin hingga Jumat dalam satu minggu (5 hari kerja).
2. WHEN Akun_Operasional yang memiliki permission `can_manage_schedule` atau seorang Guru mengaktifkan fitur pengacakan (gacha) pada Jadwal_Piket, THE Sistem SHALL mendistribusikan penugasan sehingga jumlah hari tugas setiap anggota dalam satu minggu tidak berbeda lebih dari 1 hari antara anggota mana pun, dan tidak menugaskan seorang anggota lebih dari satu kali pada hari yang sama; IF distribusi yang memenuhi kedua ketentuan tersebut tidak dapat dicapai dengan jumlah anggota yang tersedia, THEN THE Sistem SHALL menampilkan pesan kegagalan yang menjelaskan penyebabnya, meminta pengguna menyesuaikan jumlah anggota atau konfigurasi jadwal, dan tidak menyimpan jadwal yang melanggar ketentuan tersebut.
3. WHEN Akun_Operasional yang memiliki permission `can_manage_schedule` atau seorang Guru akan menyimpan Jadwal_Piket, THE Sistem SHALL menampilkan pratinjau jadwal lengkap (semua hari Senin–Jumat beserta nama anggota per hari) sebelum penyimpanan dikonfirmasi.
4. WHEN Jadwal_Piket baru berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk setiap anggota yang mendapat jadwal.
5. WHEN Akun_Operasional yang memiliki permission `can_manage_schedule` atau seorang Guru mengubah Jadwal_Piket, THE Sistem SHALL mencatat riwayat perubahan dan membuat Notifikasi internal untuk anggota yang jadwalnya berubah.
6. WHEN Akun_Operasional yang memiliki permission `can_manage_schedule` atau seorang Guru mengajukan Pergantian_Anggota piket, THE Sistem SHALL menyimpan identitas anggota asal, anggota pengganti, alasan pergantian, dan waktu pencatatan dengan status awal "Menunggu Persetujuan"; THE Sistem SHALL membuat Notifikasi internal kepada Guru dan seluruh Akun_Operasional yang memiliki permission `can_manage_schedule` bahwa terdapat pengajuan Pergantian_Anggota yang perlu ditinjau.
7. IF Akun_Operasional yang memiliki permission `can_manage_schedule` atau seorang Guru mencoba mengajukan Pergantian_Anggota pada atau setelah pukul 06.00 WIB pada hari kalender pelaksanaan piket tersebut, THEN THE Sistem SHALL menolak pengajuan dan menampilkan pesan bahwa batas waktu pengajuan pergantian telah terlewat.
8. IF pengguna mana pun mencoba mencatat Pergantian_Anggota dengan anggota pengganti yang telah memiliki jadwal piket pada hari yang sama, THEN THE Sistem SHALL menolak pergantian dan menampilkan pesan bahwa anggota pengganti sudah memiliki jadwal pada hari tersebut.
9. WHEN seorang Guru atau Akun_Operasional yang memiliki permission `can_manage_schedule` yang bukan merupakan pengaju Pergantian_Anggota tersebut menyetujui Pergantian_Anggota yang berstatus "Menunggu Persetujuan", THE Sistem SHALL mengubah status Pergantian_Anggota menjadi "Disetujui", mencatat identitas penyetuju dan waktu persetujuan, membuat Notifikasi internal kepada anggota asal dan anggota pengganti, dan mencatat aksi tersebut ke Activity_Log; IF pengaju mencoba menyetujui pengajuannya sendiri, THEN THE Sistem SHALL menolak tindakan tersebut dan menampilkan pesan bahwa pengaju tidak dapat menyetujui pengajuannya sendiri.
10. WHEN seorang Guru atau Akun_Operasional yang memiliki permission `can_manage_schedule` yang bukan merupakan pengaju Pergantian_Anggota tersebut menolak Pergantian_Anggota yang berstatus "Menunggu Persetujuan", THE Sistem SHALL mengubah status Pergantian_Anggota menjadi "Ditolak", menyimpan alasan penolakan, mencatat identitas penolak dan waktu penolakan, membuat Notifikasi internal kepada anggota asal dan anggota pengganti, dan mencatat aksi tersebut ke Activity_Log; IF pengaju mencoba menolak pengajuannya sendiri, THEN THE Sistem SHALL menolak tindakan tersebut dan menampilkan pesan yang sesuai.
11. THE Sistem SHALL memastikan bahwa hanya Pergantian_Anggota yang berstatus "Disetujui" yang dapat digunakan sebagai dasar validasi kehadiran piket; Pergantian_Anggota yang berstatus "Menunggu Persetujuan" atau "Ditolak" tidak berlaku untuk validasi kehadiran dan tidak menghasilkan poin piket.
12. THE Sistem SHALL menyimpan seluruh riwayat Jadwal_Piket dan Pergantian_Anggota secara permanen sehingga tidak dapat dihapus oleh pengguna mana pun; Jadwal_Piket yang belum berlaku hanya dapat dibatalkan oleh Guru atau Akun_Operasional yang memiliki permission `can_manage_schedule`, dengan mengubah statusnya menjadi "Dibatalkan" disertai alasan, waktu, dan identitas pengguna yang melakukan pembatalan; Jadwal_Piket yang sudah pernah berlaku tidak dapat dibatalkan atau diubah statusnya; Pergantian_Anggota tidak dapat dihapus dalam kondisi apa pun, termasuk yang berstatus "Ditolak"; IF pengguna tanpa hak akses tersebut mencoba membatalkan Jadwal_Piket, THEN THE Sistem SHALL menolak tindakan tersebut melalui pemeriksaan di sisi server dan menampilkan halaman error 403.
13. WHEN pengguna mana pun membuat atau mengubah Jadwal_Piket, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas aktor, jenis perubahan, dan waktu kejadian.
14. THE Sistem SHALL memastikan bahwa hanya Akun_Operasional yang memiliki permission `can_manage_schedule` dan pengguna dengan role Guru yang dapat membuat dan mengelola Jadwal_Piket; Akun_Operasional tanpa permission tersebut hanya dapat mengakses fitur absensi piket.
15. IF Akun_Operasional tanpa permission `can_manage_schedule` mencoba mengakses fitur pengelolaan jadwal piket, THEN THE Sistem SHALL menolak akses dan menampilkan halaman error 403.

---

### Requirement 9: Absensi Piket Gerbang

**User Story:** Sebagai anggota OSIS/MPK, saya ingin mencatat kehadiran saya saat bertugas piket melalui akun operasional, agar kehadiran saya tervalidasi dan saya mendapat poin.

#### Acceptance Criteria

1. WHEN petugas piket menggunakan Akun_Operasional untuk mencatat kehadiran anggota OSIS/MPK yang sedang bertugas, THE Sistem SHALL memvalidasi bahwa NIS anggota yang dicatat adalah anggota yang sah untuk penugasan hari tersebut, yaitu: (a) anggota yang terdaftar dalam Jadwal_Piket hari tersebut dan tidak memiliki Pergantian_Anggota yang berstatus "Disetujui" untuk penugasan tersebut, ATAU (b) anggota pengganti yang Pergantian_Anggota-nya berstatus "Disetujui" untuk penugasan hari tersebut; anggota asli yang sudah digantikan oleh Pergantian_Anggota berstatus "Disetujui" tidak berhak melakukan absensi untuk penugasan tersebut; poin +10 diberikan kepada akun pribadi Siswa berdasarkan NIS anggota yang benar-benar hadir, bukan kepada Akun_Operasional yang digunakan untuk mencatat; validasi dilakukan di sisi server.
2. IF kehadiran piket untuk anggota OSIS/MPK yang bersangkutan pada tanggal kalender yang sama telah tercatat dalam sistem, THEN THE Sistem SHALL menolak pencatatan kehadiran duplikat dan menampilkan pesan bahwa kehadiran anggota tersebut untuk hari ini sudah tercatat.
3. WHEN kehadiran piket berhasil divalidasi, THE Sistem SHALL menyimpan catatan kehadiran beserta timestamp pencatatan dan secara otomatis membuat transaksi Ledger_Poin sebesar +10 poin untuk akun pribadi (NIS) anggota tersebut dalam satu operasi atomik; IF salah satu gagal, THEN keduanya dibatalkan.
4. IF NIS anggota yang dicatat tidak memenuhi kondisi validitas pada Kriteria 1 (bukan anggota aktif dalam Jadwal_Piket maupun anggota pengganti yang Pergantian_Anggota-nya "Disetujui", atau merupakan anggota asli yang sudah digantikan), THEN THE Sistem SHALL menolak pencatatan kehadiran dan menampilkan pesan bahwa anggota tersebut tidak memiliki hak absensi piket yang valid pada hari ini.
5. WHEN catatan kehadiran piket berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk akun pribadi anggota yang bersangkutan yang menyebutkan tanggal piket dan jumlah poin yang diterima.
6. WHEN pencatatan kehadiran piket dilakukan, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log beserta identitas Akun_Operasional, identitas akun pribadi anggota, dan timestamp pencatatan.

---

### Requirement 10: Dasbor dan Tampilan Siswa

**User Story:** Sebagai siswa, saya ingin melihat seluruh catatan dan poin pribadi saya setelah login, agar saya mengetahui rekam jejak kedisiplinan saya secara transparan.

#### Acceptance Criteria

1. WHEN seorang Siswa berhasil login menggunakan NIS, THE Sistem SHALL menampilkan dasbor yang memuat total poin terkini yang dihitung dari penjumlahan seluruh transaksi Ledger_Poin bertanda, serta ringkasan catatan pelanggaran, keterlambatan, penghargaan_pagi, poin_tambahan, dan poin piket gerbang milik Siswa tersebut.
2. WHEN seorang Siswa mengakses halaman riwayat untuk suatu kategori catatan, THE Sistem SHALL menampilkan riwayat lengkap kategori tersebut milik Siswa yang sedang login, dengan informasi tanggal, jenis/deskripsi, dan nilai poin bertanda per transaksi; pelanggaran dan keterlambatan ditampilkan sebagai pengurang poin, sedangkan penghargaan, prestasi, dan poin piket ditampilkan sebagai penambah poin. Riwayat ditampilkan dalam halaman berisi maksimal 25 entri per halaman.
3. THE Sistem SHALL menampilkan daftar Notifikasi internal milik Siswa dengan status sudah dibaca dan belum dibaca.
4. WHEN seorang Siswa menandai Notifikasi sebagai sudah dibaca, THE Sistem SHALL memperbarui status notifikasi tersebut menjadi "Sudah Dibaca".
5. IF seorang Siswa mencoba mengakses halaman catatan atau poin milik Siswa lain melalui URL langsung, THEN THE Sistem SHALL menolak akses dan menampilkan halaman error 403.

---

### Requirement 11: Pengajuan Keberatan Siswa

**User Story:** Sebagai siswa, saya ingin mengajukan keberatan terhadap catatan yang saya anggap tidak sesuai, agar data yang salah dapat diperiksa dan diperbaiki oleh guru.

#### Acceptance Criteria

1. WHEN seorang Siswa mengajukan Keberatan terhadap catatan miliknya, THE Sistem SHALL menyimpan pengajuan dengan status awal "Menunggu Pemeriksaan", beserta referensi catatan yang dipermasalahkan dan alasan yang ditulis Siswa dengan panjang 10 hingga 500 karakter.
2. IF seorang Siswa mencoba mengajukan Keberatan terhadap catatan milik Siswa lain, THEN THE Sistem SHALL menolak pengajuan dan menampilkan pesan akses ditolak.
3. IF seorang Siswa mencoba mengajukan Keberatan terhadap catatan yang sudah memiliki Keberatan dengan status "Menunggu Pemeriksaan" atau "Disetujui", THEN THE Sistem SHALL menolak pengajuan dan menampilkan pesan bahwa Keberatan untuk catatan tersebut sudah ada.
4. WHEN seorang Guru menyetujui Keberatan, THE Sistem SHALL mengubah status Keberatan menjadi "Disetujui" dan memungkinkan Guru untuk memperbaiki atau menghapus catatan yang bersangkutan melalui proses pengelolaan data standar.
5. WHEN seorang Guru menolak Keberatan, THE Sistem SHALL mengubah status Keberatan menjadi "Ditolak" dan menyimpan alasan penolakan dari Guru dengan panjang 10 hingga 500 karakter.
6. WHEN status Keberatan berubah menjadi "Disetujui" atau "Ditolak", THE Sistem SHALL membuat Notifikasi internal untuk Siswa pengaju.
7. WHEN seorang Siswa mengakses halaman riwayat Keberatan miliknya, THE Sistem SHALL menampilkan seluruh Keberatan yang pernah diajukan beserta status terkini dan alasan keputusan Guru untuk setiap Keberatan yang berstatus "Disetujui" atau "Ditolak".
8. WHEN Guru memproses atau membuat keputusan atas Keberatan, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log dengan menyertakan identitas Guru, identitas Keberatan, tindakan yang dilakukan, dan waktu kejadian.

---

### Requirement 12: Dasbor dan Manajemen Data oleh Guru

**User Story:** Sebagai guru, saya ingin melihat statistik kedisiplinan dan mengelola seluruh data administrasi dari satu tempat, agar pengawasan dan pengelolaan sistem dapat dilakukan secara efisien.

#### Acceptance Criteria

1. WHEN seorang Guru mengakses halaman dasbor, THE Sistem SHALL menampilkan statistik kedisiplinan berikut yang dihitung dari data aktual database untuk periode yang dipilih Guru dari pilihan harian, mingguan, bulanan, semesteran, dan tahunan:
   a. Jumlah total Pelanggaran dan rincian jumlah per jenis pelanggaran beserta jenis pelanggaran yang paling sering terjadi pada periode tersebut.
   b. Tren jumlah Pelanggaran berdasarkan sub-periode dalam rentang yang dipilih (misalnya per hari dalam rentang mingguan, per minggu dalam rentang bulanan).
   c. Jumlah total Keterlambatan pada periode tersebut.
   d. Jumlah total Penghargaan_Pagi pada periode tersebut.
   e. Jumlah kehadiran dan ketidakhadiran anggota OSIS/MPK pada Jadwal_Piket untuk periode tersebut.
   f. Jumlah total Poin_Tambahan yang diberikan dan total nilai poin yang disalurkan melalui kategori Poin_Tambahan pada periode tersebut.
2. THE Sistem SHALL memungkinkan Guru mengakses dan mengelola seluruh catatan dengan batasan berikut: (a) data Siswa, Pelanggaran, Keterlambatan, Poin_Tambahan, dan Keberatan dapat dilihat, ditambah, diubah, dan dihapus oleh Guru; data Penghargaan_Pagi dapat dilihat, ditambah, diubah, dan dihapus oleh Guru maupun seluruh Akun_Operasional sesuai Req 5; (b) Jadwal_Piket yang belum berlaku dapat dibatalkan oleh Guru (status "Dibatalkan") tetapi tidak dapat dihapus; Jadwal_Piket yang sudah pernah berlaku tidak dapat dibatalkan atau dihapus; (c) Pergantian_Anggota dapat dilihat dan diproses (setujui/tolak) oleh Guru tetapi tidak dapat dihapus dalam kondisi apa pun; (d) catatan absensi piket dapat dilihat oleh Guru tetapi tidak dapat dihapus.
3. IF pengguna tanpa role Guru mencoba mengakses halaman manajemen data selain pengelolaan Penghargaan_Pagi yang diizinkan pada Req 5, atau mencoba mengakses dasbor Guru, THEN THE Sistem SHALL menolak akses dan menampilkan pesan akses ditolak.

---

### Requirement 13: Laporan Kedisiplinan

**User Story:** Sebagai guru, saya ingin menghasilkan laporan kedisiplinan siswa yang dapat difilter dan diekspor, agar data dapat digunakan untuk evaluasi dan pelaporan formal.

#### Acceptance Criteria

1. WHEN seorang Guru mengakses halaman laporan kedisiplinan, THE Sistem SHALL menyediakan filter berdasarkan: kelas, jurusan, bulan dan tahun, periode semester (Januari–Juni atau Juli–Desember), serta kategori pencatatan (pelanggaran, keterlambatan, penghargaan_pagi, piket_gerbang, atau poin_tambahan).
2. WHEN seorang Guru menghasilkan laporan, THE Sistem SHALL menampilkan laporan per kelas secara terpisah dengan kolom nilai bertanda untuk: Pelanggaran, Keterlambatan, Penghargaan_Pagi, Piket_Gerbang, dan Poin_Tambahan, serta kolom Total Akumulasi Poin per Siswa. Total Akumulasi Poin dihitung sebagai jumlah nilai kelima kategori tersebut.
3. THE Sistem SHALL menampilkan nilai poin dalam angka positif atau negatif; IF tidak ada kejadian pada kategori tertentu untuk seorang Siswa, THEN THE Sistem SHALL menampilkan tanda "-" pada kolom kategori tersebut.
4. THE Sistem SHALL menghitung nilai tiap kategori dan total poin laporan dari seluruh entri Ledger_Poin milik Siswa dalam periode yang dipilih, termasuk counter-entry, dengan memastikan setiap ID transaksi dihitung tepat satu kali. Nilai transaksi bertanda dijumlahkan apa adanya: nilai negatif mengurangi skor dan nilai positif menambah skor; counter-entry berlawanan tanda membatalkan dampak transaksi asalnya.
5. WHEN seorang Guru memilih format ekspor (PDF atau Excel) dan mengkonfirmasi ekspor, THE Sistem SHALL menghasilkan file laporan kedisiplinan dalam format yang dipilih dan menyediakannya untuk diunduh; IF Guru belum memilih format sebelum mengkonfirmasi, THEN THE Sistem SHALL menolak permintaan ekspor dan meminta Guru memilih format terlebih dahulu.
6. WHEN seorang Guru mengakses rekap piket gerbang, THE Sistem SHALL menampilkan data per minggu atau per periode yang dipilih dengan kolom: nama anggota, tanggal piket, dan status kehadiran.

---

### Requirement 14: Activity Log

**User Story:** Sebagai guru, saya ingin melihat seluruh riwayat aktivitas penting dalam sistem, agar setiap perubahan data dapat diaudit dan ditelusuri.

#### Acceptance Criteria

1. THE Sistem SHALL mencatat setiap aksi penting ke Activity_Log dengan data: identitas pengguna, role pengguna, jenis aksi, kategori data yang terdampak, ID data terkait, nama field dan nilai sebelum serta sesudah perubahan untuk setiap field yang berubah, serta tanggal dan waktu aksi dalam format YYYY-MM-DD HH:MM:SS.
2. THE Sistem SHALL mencakup seluruh jenis aksi berikut dalam Activity_Log: login, logout, tambah akun, ubah akun, nonaktifkan akun, pemberian permission can_manage_schedule, pencabutan permission can_manage_schedule, tambah catatan pelanggaran, ubah catatan pelanggaran, hapus catatan pelanggaran, tambah catatan keterlambatan, ubah catatan keterlambatan, hapus catatan keterlambatan, tambah penghargaan pagi, ubah penghargaan pagi, hapus penghargaan pagi oleh Guru atau Akun_Operasional, pemberian poin tambahan, ubah poin tambahan, hapus poin tambahan, buat jadwal piket, ubah jadwal piket, pembatalan jadwal piket, pengajuan pergantian anggota, persetujuan pergantian anggota, penolakan pergantian anggota, pencatatan kehadiran piket, pembatalan transaksi poin, penyesuaian transaksi poin, persetujuan keberatan, penolakan keberatan, impor data siswa, dan ekspor data.
3. THE Sistem SHALL memastikan entri Activity_Log tidak dapat diubah atau dihapus melalui antarmuka aplikasi oleh pengguna mana pun, termasuk Guru.
4. IF pengguna dengan role Siswa atau Akun_Operasional mencoba mengakses halaman Activity_Log melalui URL langsung, THEN THE Sistem SHALL menolak akses dan menampilkan halaman error 403.
5. THE Sistem SHALL menyediakan fitur pencarian dan filter Activity_Log berdasarkan identitas pengguna, jenis aksi, kategori data, dan rentang tanggal.
6. WHEN seorang Guru memilih format ekspor PDF atau Excel dan mengkonfirmasi ekspor Activity_Log, THE Sistem SHALL menghasilkan file dalam format yang dipilih dan menyediakannya untuk diunduh.

---

### Requirement 15: Sistem Notifikasi Internal

**User Story:** Sebagai pengguna, saya ingin menerima notifikasi di dalam aplikasi untuk kejadian yang relevan, agar saya mengetahui perubahan penting tanpa harus memeriksa data secara manual.

#### Acceptance Criteria

1. WHEN catatan Pelanggaran, Keterlambatan, Penghargaan_Pagi, atau Poin_Tambahan baru berhasil disimpan, atau poin Piket_Gerbang diterima, atau Keberatan disetujui atau ditolak, THE Sistem SHALL membuat Notifikasi internal untuk Siswa yang bersangkutan.
2. WHEN Jadwal_Piket baru berhasil disimpan, THE Sistem SHALL membuat Notifikasi internal untuk seluruh anggota yang dijadwalkan dalam jadwal piket tersebut tanpa pengecualian; WHEN jadwal piket mengalami perubahan, THE Sistem SHALL membuat Notifikasi internal untuk anggota yang jadwalnya berubah.
3. WHEN status Pergantian_Anggota berubah menjadi "Disetujui" atau "Ditolak", THE Sistem SHALL membuat Notifikasi internal untuk anggota asal dan anggota pengganti yang mencantumkan hasil keputusan dan hari piket yang bersangkutan.
4. WHEN Notifikasi baru dibuat, THE Sistem SHALL menyimpan Notifikasi tersebut dengan status awal "Belum Dibaca".
5. WHEN seorang pengguna menandai satu Notifikasi sebagai dibaca, THE Sistem SHALL memperbarui status Notifikasi tersebut menjadi "Sudah Dibaca".
6. THE Sistem SHALL menampilkan indikator jumlah Notifikasi yang belum dibaca di area navigasi utama antarmuka pengguna yang sedang login.
7. THE Sistem SHALL menampilkan toast (pemberitahuan singkat dengan durasi tampil 3–5 detik yang hilang otomatis) untuk setiap aksi yang berhasil atau gagal dilakukan oleh pengguna.

---

### Requirement 16: Autentikasi dan Otorisasi

**User Story:** Sebagai pengguna, saya ingin sistem mengontrol akses berdasarkan role, agar setiap pengguna hanya dapat mengakses fitur yang sesuai dengan perannya.

#### Acceptance Criteria

1. THE Sistem SHALL mengautentikasi seluruh pengguna menggunakan kombinasi username dan kata sandi serta menyimpan kata sandi menggunakan hashing, bukan teks biasa:
   a. THE Sistem SHALL menyediakan tepat tiga akun Guru dengan hak akses identik: username guru0001 dengan kata sandi awal guru0001, username guru0002 dengan kata sandi awal guru0002, dan username guru0003 dengan kata sandi awal guru0003.
   b. THE Sistem SHALL menyediakan maksimal enam Akun_Operasional dengan username piket001, piket002, piket003, piket004, piket005, dan piket006; kata sandi awal setiap akun sama dengan username masing-masing.
   c. Kata sandi awal Guru dan Akun_Operasional SHALL memiliki panjang minimal 8 karakter. Pengguna tidak wajib mengganti kata sandi setelah login pertama dan Sistem tidak boleh memaksa perubahan kata sandi. Jika kata sandi Guru atau Akun_Operasional diubah melalui proses administrasi yang diizinkan, kata sandi baru SHALL memiliki panjang minimal 8 karakter.
   d. Siswa menggunakan NIS yang terdiri dari 5–20 digit angka sebagai username sekaligus kata sandi awal; NIS lima digit diperbolehkan dan NIS disimpan menggunakan VARCHAR atau tipe teks setara agar angka nol di depan tidak hilang.
   e. Kata sandi awal Siswa dikecualikan dari aturan panjang minimal 8 karakter dan Sistem tidak boleh memaksa Siswa mengganti kata sandi awal.
2. WHEN pengguna berhasil login, THE Sistem SHALL mengarahkan pengguna ke dasbor yang sesuai dengan role-nya: dasbor Guru, dasbor Siswa, atau dasbor Akun_Operasional.
3. THE Sistem SHALL selalu menerapkan middleware otorisasi berbasis role pada seluruh rute yang dilindungi DAN selalu memblokir akses langsung melalui URL oleh pengguna yang tidak memiliki role yang sesuai, kecuali hak akses lintas-role yang secara eksplisit ditetapkan pada requirement terkait; pengecualian hak akses lintas-role bagi Akun_Operasional hanya berlaku untuk pengelolaan Penghargaan_Pagi sebagaimana ditetapkan pada Req 5.
4. THE Sistem SHALL memeriksa permission `can_manage_schedule` melalui middleware atau policy Laravel di sisi server pada setiap permintaan ke rute pengelolaan jadwal piket; permission ini dikelola oleh Guru melalui antarmuka manajemen akun dan disimpan pada tabel permissions atau kolom yang terkait dengan Akun_Operasional.
5. IF pengguna mencoba mengakses halaman yang tidak sesuai dengan role atau permission-nya, THEN THE Sistem SHALL menampilkan halaman error 403; Akun_Operasional hanya memperoleh hak akses Penghargaan_Pagi dan hak lain yang secara eksplisit diberikan pada requirement terkait.
6. WHEN pengguna berhasil login atau logout, THE Sistem SHALL mencatat aksi tersebut ke Activity_Log.
7. THE Sistem SHALL tidak menerapkan pembatasan jumlah percobaan login atau penguncian akun akibat percobaan login gagal.

---

### Requirement 17: Dasbor Akun_Operasional

**User Story:** Sebagai anggota OSIS/MPK yang menggunakan akun operasional, saya ingin melihat informasi piket hari ini dan mengakses fitur yang sesuai dengan hak akses akun saya, agar tugas piket dapat dijalankan dengan efisien.

#### Acceptance Criteria

1. WHEN Akun_Operasional berhasil login, THE Sistem SHALL menampilkan dasbor yang memuat: jadwal piket hari ini (hari kalender saat login), daftar nama anggota OSIS/MPK yang bertugas pada hari tersebut, dan status kehadiran setiap anggota (sudah hadir atau belum hadir).
2. THE Sistem SHALL menyediakan akses langsung ke halaman pencatatan absensi piket dari dasbor Akun_Operasional.
3. WHEN Akun_Operasional yang memiliki permission `can_manage_schedule` mengakses dasbor, THE Sistem SHALL menampilkan akses tambahan ke fitur pengelolaan jadwal piket dan Pergantian_Anggota.
4. WHEN Akun_Operasional yang tidak memiliki permission `can_manage_schedule` mengakses dasbor, THE Sistem SHALL hanya menampilkan fitur absensi piket dan informasi jadwal hari ini tanpa menampilkan akses ke fitur pengelolaan jadwal.
5. IF tidak ada Jadwal_Piket yang berlaku pada hari saat login, THE Sistem SHALL menampilkan pesan bahwa tidak ada jadwal piket pada hari ini.
6. THE Sistem SHALL menampilkan indikator jumlah Notifikasi yang belum dibaca pada dasbor Akun_Operasional sesuai dengan ketentuan Req 15.
