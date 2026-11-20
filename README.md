# Dashboard Manajemen Rapat Smart Cendekia

Aplikasi PHP native untuk mengelola jadwal rapat, notulensi, dan tindak lanjut.

## Persyaratan

- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql` dan `fileinfo`
- MySQL atau MariaDB
- Apache dengan `mod_rewrite`/`AllowOverride` bila menggunakan file `.htaccess`
- Node.js dan npm untuk membangun stylesheet Tailwind lokal

## Menjalankan aplikasi

1. Buat database dan tabel dengan mengimpor `database.sql` ke MySQL/MariaDB.
2. Atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`. Default lokal adalah host `127.0.0.1`, database `smartcendekia`, user `root`, tanpa password.
3. Buat admin pertama melalui CLI. Set `ADMIN_EMAIL` dan `ADMIN_PASSWORD` (minimal 10 karakter), lalu jalankan `php config/create_admin.php`. Script hanya berjalan melalui CLI dan hanya jika tabel pengguna masih kosong.
4. Pasang dependensi front-end dan bangun stylesheet Tailwind:

   ```powershell
   npm install
   npm run build:css
   ```

   Jalankan kembali `npm run build:css` setelah mengubah template atau stylesheet.
5. Arahkan document root web server ke folder project ini atau buka aplikasi melalui subfoldernya (misalnya `http://localhost/dashboardysc/`). URL internal akan mengikuti lokasi aplikasi secara otomatis.
6. Pastikan PHP dapat menulis ke `uploads/lampiran`. Lampiran diunduh melalui endpoint terautentikasi, bukan URL file langsung.

### Daily Activities Plan (DAP)

Untuk database yang sudah ada, jalankan `database/migrations/20261119_create_dap_tables.sql` sekali setelah memilih database aplikasi di MySQL. Instalasi baru mendapatkan tabel DAP saat mengimpor `database.sql`.

Admin dan pimpinan dapat membuat satu DAP per minggu untuk akun masing-masing. Pilih tanggal Senin sebagai awal minggu, isi hingga 10 pekerjaan prioritas dan slot aktivitas Senin–Sabtu, lalu pilih **Submit DAP**. Slot tersedia setiap 30 menit: Senin–Jumat pukul 07.30–15.30 dan Sabtu pukul 07.30–13.00. Aktivitas pada slot boleh dikosongkan. Setelah submit, DAP langsung dapat dilihat admin dan pimpinan; pemiliknya saja yang dapat mengedit. Dari halaman detail DAP, unduh file Excel `.xlsx` atau gunakan tombol cetak dan simpan sebagai PDF.

Contoh menjalankan server development dari folder project:

```powershell
php -S 127.0.0.1:8000 dev_router.php
```

Lalu buka `http://127.0.0.1:8000`.

## Catatan keamanan

- Query menggunakan PDO prepared statements; password akun disimpan dengan `password_hash()`.
- Form perubahan data dilindungi CSRF token dan tampilan meng-escape output.
- Role SD IT hanya melihat data kategori SD IT dan Semua Unit; role SMP IT hanya melihat SMP IT dan Semua Unit.
- File lampiran dibatasi PDF/JPG/PNG, maksimal 5 MB, MIME diperiksa, dan nama penyimpanannya acak.
- Gunakan HTTPS dan kredensial database dengan hak minimum pada deployment. Direktori lampiran dibatasi oleh konfigurasi Apache di `.htaccess`; pada server selain Apache, terapkan aturan web server setara yang menolak akses langsung ke `uploads/lampiran`.
