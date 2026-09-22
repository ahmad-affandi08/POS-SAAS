<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 2. Latar Belakang, Masalah & Peluang

### 2.1 Konteks Pasar

- Indonesia punya lebih dari 60 juta pelaku UMKM. Sebagian besar masih mencatat transaksi secara manual atau semi-manual (buku, Excel, WhatsApp).
- Adopsi QRIS meningkat pesat, sehingga pembayaran non-tunai sudah menjadi standar, bukan fitur tambahan.
- Pemilik usaha multi-outlet butuh kontrol stok dan kas jarak jauh karena kebocoran (fraud kasir, stok hilang) adalah masalah utama.
- Regulasi pajak terus berubah (PPN 12% dengan DPP nilai lain, Coretax DJP, PBJT daerah). Sistem wajib bisa dikonfigurasi, bukan hard-coded.

### 2.2 Masalah Pengguna

| # | Masalah | Dampak | Siapa yang merasakan |
|---|---|---|---|
| P1 | Internet tidak stabil (terutama di luar kota besar) sehingga POS cloud macet | Antrian, transaksi hilang, pelanggan kabur | Kasir, Owner |
| P2 | Stok tidak akurat antara sistem dan fisik | Stockout / overstock, uang tertahan | Owner, Gudang |
| P3 | Kebocoran kas dan fraud kasir (void fiktif, diskon liar) | Kerugian langsung | Owner |
| P4 | Laporan keuangan harus direkap manual | Tidak tahu untung sebenarnya, sulit ajukan kredit | Owner, Akuntan |
| P5 | Satu aplikasi tidak cocok untuk usaha campuran (resto + toko) | Pakai 2–3 aplikasi, data terpisah | Owner |
| P6 | Promo rumit tidak bisa diatur | Kehilangan peluang penjualan | Marketing, Owner |
| P7 | Hitung HPP/resep F&B manual | Harga jual salah, margin tipis | Owner F&B |
| P8 | Pajak (PPN/PB1) salah hitung | Risiko sanksi | Owner, Akuntan |
| P9 | Biaya langganan dan hardware mahal | UMKM mikro enggan beralih | UMKM mikro |

### 2.3 Peluang

Belum ada pemain yang menggabungkan **(a)** offline-first yang andal, **(b)** multi-sektor dalam satu tenant, **(c)** akuntansi dan pajak Indonesia yang benar secara otomatis, dan **(d)** harga terjangkau untuk UMKM mikro. {{APP}} mengisi celah tersebut.
