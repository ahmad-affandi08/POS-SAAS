# Video promosi PAYOU (30 detik)

Motion graphic HTML untuk video promosi awal. 1920×1080 (16:9), 30 detik, tanpa audio.
Memakai logo dari `../Sumber/`, palet merek D-15, dan font Atkinson Hyperlegible (§17.5).

## Alur cerita

| Detik | Adegan | Pesan |
|---|---|---|
| 0–4,4 | Masalah | "Jualan lagi ramai… internet malah mati." Wi-Fi putus, stok selisih, rekap manual, laporan belum jadi |
| 4,4–8,2 | Pengenalan | Bintang merek terbang ke logo, logo PAYOU tersapu masuk, lalu mengecil ke sudut |
| 8,2–13,2 | 01 Kasir offline-first | Tablet PAYOU POS: status luring, keranjang, bayar berhasil, lalu tersinkron tanpa dobel |
| 13,2–17,7 | 02 Template sektor | "Satu aplikasi, 12+ jenis usaha": 14 sektor dari §5.1 muncul bergantian |
| 17,7–22,2 | 03 Stok & akuntansi otomatis | Penjualan → mutasi stok → jurnal seimbang (D = K) → laporan laba rugi |
| 22,2–26,2 | 04 Aplikasi PAYOU Owner | Omzet 3 outlet, grafik, stok menipis, persetujuan void dari HP |
| 26,2–30 | Penutup | Logo, "Daftar gratis sekarang", Android · iPad & iPhone · Windows · Web |

## Memutar

Buka `VideoPromosi30Detik.html` di peramban (butuh internet untuk memuat font Google Fonts).
Spasi = putar/jeda, R = ulang, H = sembunyikan kontrol, panah kiri/kanan = geser 1 detik.

## Merekam ke MP4

Rekaman dibuat bingkai demi bingkai sehingga hasilnya mulus dan sama persis setiap kali:

```bash
npm i -g playwright            # sekali, jika belum ada
pip install imageio-ffmpeg     # jika ffmpeg belum terpasang; lalu set FFMPEG ke path biner-nya
NODE_PATH=$(npm root -g) node Spesifikasi/Merek/VideoPromosi/RekamVideo.mjs keluaran.mp4 30
```

Hasil MP4 tidak disimpan di repo; buat ulang dengan perintah di atas.
Video tidak memuat audio; tambahkan musik atau sulih suara saat penyuntingan.
