# Koze-Kost

Koze-Kost adalah platform galeri dan pencarian kos yang mempertemukan **pencari kos**, **pemilik kos**, dan **admin** dalam satu sistem.

## Gambaran Project

Koze-Kost dirancang untuk membantu pengguna menemukan kos sesuai kebutuhan dengan pengalaman yang lebih modern:
- Informasi kos tersusun rapi dalam bentuk katalog/listing
- Lokasi kos dapat dilihat melalui peta
- Tersedia komunikasi langsung antara pihak terkait
- Pemilik kos dapat mengelola data properti dan ketersediaan kamar

## Peran Pengguna

1. **Pencari Kos**
   - Mencari dan melihat daftar kos
   - Menggunakan chat AI untuk rekomendasi kos sesuai preferensi
   - Berkomunikasi langsung dengan pemilik kos

2. **Pemilik Kos**
   - Mengunggah data kos
   - Memperbarui informasi dan ketersediaan kos
   - Berinteraksi dengan calon penyewa melalui chat real-time

3. **Admin**
   - Mengelola data pengguna dan konten platform
   - Mengelola iklan yang tampil di sistem

## Fitur Utama

- **Galeri/Listing Kos**: menampilkan daftar kos beserta detail penting
- **Rekomendasi Kos via AI Chat**: membantu pencari mendapatkan saran kos sesuai kebutuhan
- **Peta Lokasi Kos**: memudahkan eksplorasi area kos berdasarkan lokasi
- **Chat Real-Time Pencari & Pemilik**: komunikasi langsung (transaksi/pembelian dilakukan di luar sistem)
- **Manajemen Ketersediaan Kos**: pemilik dapat update status ketersediaan kamar
- **Manajemen Iklan**: owner dapat upload banner iklan untuk ditampilkan pada sistem

## Model Bisnis

Koze-Kost menggunakan dua sumber monetisasi utama:

1. **Google Ads**
   - Menampilkan iklan dari jaringan Google Ads di platform.

2. **Iklan UMKM**
   - Pelaku UMKM dapat mengajukan iklan.
   - Banner iklan yang disetujui akan diunggah dan dikelola melalui sistem.

## Tech Stack

Proyek ini dibangun dengan stack berikut:

- **Backend**: PHP 8.2, Laravel 12
- **Frontend**: Vue 3, Inertia.js, Vite
- **UI & Styling**: Tailwind CSS, Nuxt UI
- **Database**: SQLite (default development), dapat disesuaikan untuk environment lain
- **Authorization**: Spatie Laravel Permission
- **Testing**: PHPUnit

## Menjalankan Project (Local)

Masuk ke folder `src/`, lalu jalankan:

```bash
composer setup
composer dev
```

Untuk testing:

```bash
composer test
```

## Nama Kelompok

1. Tri Aldy Kurniawan
2. Fatah
3. Kamila Zahwa
4. Aldyth
5. Nevita
