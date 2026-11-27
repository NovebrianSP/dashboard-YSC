# Dashboard Manajemen Rapat Smart Cendekia

Aplikasi PHP native untuk mengelola jadwal rapat, notulensi, dan tindak lanjut.

## Persyaratan

- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql`, `fileinfo`, `zip`, dan `simplexml`
- MySQL atau MariaDB
- Apache dengan `mod_rewrite`/`AllowOverride` bila menggunakan file `.htaccess`
- Node.js dan npm untuk membangun stylesheet Tailwind lokal

## Menjalankan aplikasi

1. Buat database dan tabel dengan mengimpor `database.sql` ke MySQL/MariaDB.
2. Atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`. Default lokal adalah host `127.0.0.1`, database `smartcendekia`, user `root`, tanpa password.
   Atur juga `APP_URL` ke origin publik aplikasi (contoh `https://dashboard.example.org`) dan `MAIL_FROM` ke alamat pengirim yang valid. Pastikan fungsi PHP `mail()` dikonfigurasi untuk mengirim email melalui SMTP; untuk XAMPP, atur SMTP pada `php.ini` yang digunakan Apache, lalu restart Apache.
3. Buat admin pertama melalui CLI. Set `ADMIN_EMAIL` dan `ADMIN_PASSWORD` (minimal 10 karakter), lalu jalankan `php config/create_admin.php`. Script hanya berjalan melalui CLI dan hanya jika tabel pengguna masih kosong.
4. Pasang dependensi front-end, bangun stylesheet Tailwind, dan salin renderer DOCX lokal:

   ```powershell
   npm install
   npm run build:css
   npm run build:js
   ```

   Jalankan kembali `npm run build:css` setelah mengubah template atau stylesheet. Jalankan `npm run build:js` setelah memasang atau memperbarui dependensi pratinjau.
5. Arahkan document root web server ke folder project ini atau buka aplikasi melalui subfoldernya (misalnya `http://localhost/dashboardysc/`). URL internal akan mengikuti lokasi aplikasi secara otomatis.
6. Pastikan PHP dapat menulis ke `uploads/lampiran`. Lampiran diunduh melalui endpoint terautentikasi, bukan URL file langsung.

### Daily Activities Plan (DAP)

Untuk database yang sudah ada, jalankan `database/migrations/20261119_create_dap_tables.sql` sekali setelah memilih database aplikasi di MySQL. Instalasi baru mendapatkan tabel DAP saat mengimpor `database.sql`.

Admin dan pimpinan dapat membuat satu DAP per minggu untuk akun masing-masing. Pilih tanggal Senin sebagai awal minggu, isi hingga 10 pekerjaan prioritas dan slot aktivitas Senin–Sabtu, lalu pilih **Submit DAP**. Slot tersedia setiap 30 menit: Senin–Jumat pukul 07.30–16.00 dan Sabtu pukul 07.30–13.30. Admin juga dapat memilih **Impor dari Excel** pada halaman DAP untuk mengunggah template `.xlsx` (maksimal 5 MB), memilih sheet (default **Iyan**) dan tanggal Senin minggu tujuan. Impor mengisi DAP akun admin yang sedang masuk, memetakan aktivitas menurut urutan kolom Senin–Sabtu, dan tidak menimpa DAP yang sudah ada dengan isi berbeda. File hanya diproses sementara. Dari halaman detail DAP, unduh file Excel `.xlsx` atau gunakan tombol cetak dan simpan sebagai PDF.

### Keamanan login dan reset kata sandi

Tiga kegagalan kata sandi berturut-turut mengunci akun sampai reset kata sandi berhasil. Tautan reset dikirim ke alamat email akun, hanya berlaku satu jam, dan hanya dapat digunakan sekali. Pengguna dapat meminta tautan baru dari halaman login. Untuk database yang sudah ada, jalankan `database/migrations/20261127_add_login_lock_and_password_reset.sql` sekali. Reset membutuhkan `APP_URL`, `MAIL_FROM`, dan konfigurasi SMTP PHP `mail()` yang berfungsi.

### Rapat, notulensi, dan laporan bulanan

Notulensi dan jadwal rapat tersedia bersama pada menu **Rapat dan Notulensi**. Halaman tersebut memuat arsip notulensi dan alur pembuatan notulensi untuk rapat yang berstatus **Selesai**. Detail notulensi berada di `modules/rapat/notulensi`.

Arsip laporan bulanan berdiri sendiri pada menu **Arsip Laporan Bulanan**. Untuk database yang sudah ada, jalankan `database/migrations/20261120_create_monthly_reports_table.sql` sekali. Admin dan pimpinan dapat mengunggah beberapa laporan per bulan dalam format DOC, DOCX, atau PDF (maksimal 10 MB per file), melihat PDF dan DOCX langsung di aplikasi, serta mengunduh arsip tersebut. File DOC lama tetap dapat diunduh, tetapi belum mendukung pratinjau langsung. File disimpan di `uploads/laporan_bulanan` dan hanya dapat diakses melalui aplikasi oleh admin atau pimpinan.

### Laporan keuangan

Laporan keuangan menyediakan ringkasan, grafik tren, rekap bulanan per sumber/komponen, perbandingan rekap bulanan dengan laporan sumber kumulatif, capaian target tahunan, dan rekonsiliasi saldo. Admin dan pimpinan dapat mengisi **Input Realisasi Bulanan**, **Input Laporan Sumber**, target pada **Atur Target**, dan saldo pada **Atur saldo**. **Rekap Terperinci** menampilkan rincian tahunan seluruh sheet keuangan. Untuk database yang sudah ada, jalankan `database/migrations/20261124_create_finance_reporting.sql`, lalu `database/migrations/20261124_create_finance_source_consolidation.sql`; instalasi baru mendapatkan tabel tersebut dari `database.sql`.

Penerimaan dicatat menurut SDIT, SMPIT, Yayasan (termasuk DANUS), Wakaf, Infak, dan Lain-lain. Pengeluaran dicatat menurut biaya SDIT, SMPIT, Yayasan, Wakaf, dan Infak. Nilai pada riwayat transaksi per pos yang lama tetap tersedia, tetapi tidak digabung otomatis agar data agregat konsolidasi tidak terhitung ganda.

Untuk memasukkan data dari `Dashboard Capaian Keuangan.xlsx`, jalankan seeder CLI dari root project: `php database/seeders/seed_finance_excel_2026.php`. Seeder memasukkan realisasi bulanan Januari–Oktober 2026, rekap kumulatif laporan sumber per 31 Juli, target tahunan, serta saldo pada sheet Dashboard. Rekap kumulatif disimpan terpisah dari realisasi bulanan agar selisihnya dapat dibandingkan tanpa penghitungan ganda. Seeder aman dijalankan ulang: data yang sama dilewati, sedangkan nilai yang berbeda menyebabkan proses batal tanpa menimpa data.

Admin dapat memilih tombol **Impor dari Excel** pada halaman DAP untuk mengunggah template `.xlsx` (maksimal 5 MB), menentukan nama sheet (default **Iyan**) dan tanggal Senin minggu tujuan. Impor mengisi DAP akun admin yang sedang masuk, memetakan aktivitas menurut urutan kolom Senin–Sabtu, dan tidak menimpa DAP yang sudah ada dengan isi berbeda. File hanya diproses sementara dan tidak disimpan di server.

Seeder CLI tetap tersedia: `php database/seeders/import_dap_excel.php "<path-file>" "<nama-sheet>" "<email-akun>" "<tanggal-senin-YYYY-MM-DD>"`. Contoh: `php database/seeders/import_dap_excel.php "D:\path\DAP MAGANG YSC 23-28 Nov 2026.xlsx" "Iyan" "default@gmail.com" "2026-11-23"`. Slot DAP diperpanjang agar aktivitas pukul 15.30 dan 13.00 tetap dapat dimasukkan.

Contoh menjalankan server development dari folder project:

```powershell
php -S 127.0.0.1:8000 dev_router.php
```

Lalu buka `http://127.0.0.1:8000`.

## Catatan keamanan

- Query menggunakan PDO prepared statements; password akun disimpan dengan `password_hash()`.
- Akun dikunci setelah tiga kali gagal login berturut-turut; reset email memakai token acak sekali pakai yang disimpan dalam bentuk hash dan kedaluwarsa satu jam.
- Form perubahan data dilindungi CSRF token dan tampilan meng-escape output.
- Role SD IT hanya melihat data kategori SD IT dan Semua Unit; role SMP IT hanya melihat SMP IT dan Semua Unit.
- Lampiran notulensi dibatasi PDF/JPG/PNG maksimal 5 MB; laporan bulanan dibatasi DOC/DOCX/PDF maksimal 10 MB. Jenis file diperiksa, nama penyimpanannya acak, dan file laporan hanya diunduh lewat endpoint terautentikasi.
- Gunakan HTTPS dan kredensial database dengan hak minimum pada deployment. Direktori unggahan dibatasi oleh konfigurasi Apache di `.htaccess`; pada server selain Apache, terapkan aturan web server setara yang menolak akses langsung ke `uploads/lampiran` dan `uploads/laporan_bulanan`.
