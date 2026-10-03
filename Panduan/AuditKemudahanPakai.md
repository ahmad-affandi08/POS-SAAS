# Audit kemudahan pakai PAYOU (3 Oktober 2026)

Penyisiran baca-kode atas tiga area: back-office web, aplikasi Kasir, dan alur awal & pengaturan (termasuk Aplikasi
Pemilik). Tujuannya mencari langkah yang terlalu banyak, isian yang bisa otomatis, istilah teknis, input ganda, dan
jalan buntu. Hitungan ketukan adalah perkiraan dari kode, bukan uji di perangkat. Path bukti relatif ke `Aplikasi/`.

Angka utama hari ini: **dari `/daftar` sampai transaksi pertama di kasir ±11–12 layar, ±30–35 aksi, 9 isian wajib**
(jalur tercepat "Siapkan semuanya otomatis"). **Bayar tunai di kasir: 5 ketukan per transaksi.** Satu produk baru +
stok awal di back-office: ±4 halaman, 1 dialog, 10+ klik.

Kolom **Putusan**: `Agent` = perbaikan teknis/UX yang bisa langsung dikerjakan; `Pemilik` = mengubah aturan atau
keputusan produk, perlu persetujuan pemilik produk dulu.

## Gelombang 1 — jalan buntu & alur paling sering (Tinggi)

| # | Area | Masalah | Usulan | Putusan |
|---|---|---|---|---|
| 1 | Awal | Panduan wajib macet total bila belum ada template sektor terbit (`PanduanAwal/Sektor.tsx:97-111`, `LangkahPanduan::CekWajib`, `WajibPanduanAwal`) | Template "Umum" cadangan selalu tersedia; daftar kosong = langkah Sektor boleh dilewati | Agent |
| 2 | Awal | PIN kasir pemilik tidak ditanyakan di panduan; baru gagal di perangkat ("PIN belum diatur", `MasukPinKasir.php:53`) | Minta PIN 6 digit di langkah Perangkat kasir | Agent |
| 3 | Awal | Tidak ada tautan unduh aplikasi kasir di back-office (`KartuKodeAktivasi.tsx:37`) | Tombol unduh per platform + QR di kartu aktivasi & langkah Perangkat | Agent |
| 4 | Awal | "Siapkan semuanya otomatis" ada di bawah 14 kartu, tetap harus klik "Selesaikan panduan", dan bisa tidak menandai Produk selesai (`SiapkanOtomatisPanduan.php:80`) | Jalur otomatis jadi pilihan utama dan langsung menyelesaikan panduan | Agent |
| 5 | Awal | Metode pembayaran tidak punya rumah setelah panduan (rute hanya di `routes/PanduanAwal.php`) | Butir "Metode pembayaran" di Pengaturan › Kasir & struk | Agent |
| 6 | Kasir | Bayar tunai 5 ketukan: metode tidak terpilih, "Uang diterima" kosong, layar selesai harus ditutup manual (`PanelBayar.dart:103,240,1331`) | Tunai terpilih otomatis, tombol "Uang pas" di keranjang, layar selesai tertutup saat produk berikut dipindai → 2 ketukan | Agent |
| 7 | Kasir | Pilihan kursus (Pembuka/Utama/Penutup) menempel, pesanan meja berikutnya tertahan tidak sampai dapur (`LayarJual.dart:140,694,1529`) — **bug** | Reset setelah kirim/ganti pesanan; tampil hanya bila outlet memakai kursus | Agent |
| 8 | Kasir | Kas masuk/keluar: kategori dicek setelah supervisor memasukkan PIN (`LembarMutasiKas.dart:76`, `LayananShift.dart:219`) | Validasi sebelum PIN; satu kategori = terpilih otomatis; chip, bukan dropdown | Agent |
| 9 | Kasir | Papan PIN tidak menerima keyboard fisik di Windows (`PapanPin.dart`) | Angka, Backspace, Enter dari keyboard | Agent |
| 10 | Kasir | Retur/tukar: nomor struk harus diketik persis, tidak bisa dari baris Riwayat; Riwayat tanpa pencarian (`LayananReturPenjualan.dart:106`, `LayarRiwayat.dart:288,383`) | Tombol Retur/Tukar di baris Riwayat, cari sebagian nomor/nominal, "Cetak ulang struk terakhir" di Jual | Agent |
| 11 | Back-office | Produk baru tidak bisa langsung punya stok; stok awal ada di Pengaturan, draf → posting (`Produk/Form.tsx`, `StokAwal/*`) | Isian opsional "Stok sekarang" + "Harga beli" di formulir produk, diposting otomatis | Agent |
| 12 | Back-office | Posting stok buntu bila akun jurnal belum dipetakan; pesan "minta Akuntan" (`PanelKesiapanAkun.tsx`, `PenentuAkun.php:30`) | Pemetaan akun otomatis dari template bawaan + tombol "Perbaiki otomatis" | Agent |
| 13 | Back-office | Dokumen stok & pembelian selalu "Simpan draf" lalu buka detail & posting (penyesuaian, transfer, stok awal, PO) | "Simpan & posting" jadi tombol utama, draf sekunder | Agent |
| 14 | Back-office | Belanja stok (beli tunai sekali simpan) tersembunyi di halaman Penerimaan; tidak bisa diretur (`SimpanReturPembelian.php:97`) | Entri menu pertama di Pembelian + pintasan Beranda; retur belanja stok | Agent |
| 15 | Back-office | Catat pengeluaran & kategori kas memaksa pilih akun COA berkode (`KasBank/Buat.tsx:62`, `KategoriKas.tsx:185`) | Kategori ramah ("Listrik & air", "Sewa", …) dipetakan otomatis; kode akun hanya di mode akuntan | Agent |
| 16 | Back-office | Penyesuaian > Rp 500.000 dan PO > Rp 5 jt wajib disetujui orang lain → toko satu admin buntu (`SetujuiPenyesuaianStok.php:47`, `SetujuiPesananPembelian.php:48`) | Pengguna tunggal / Pemilik = posting langsung tercatat audit; four-eyes aktif saat ada penyetuju kedua | **Pemilik** (aturan four-eyes BR) |
| 17 | Awal | Paket Bisnis saat daftar langsung memaksa 2FA sebelum panduan (`KatalogPaket.json`, `web.php:173`) | Tenggang 2FA selama trial + banner | **Pemilik** (aturan keamanan paket) |

## Gelombang 2 — beban berulang & istilah (Tinggi–Sedang)

| # | Area | Masalah | Usulan | Putusan |
|---|---|---|---|---|
| 18 | Back-office | Varian: harga/barcode beda harus dibuka satu per satu (`PembuatVarian.tsx`, `Produk/Detail.tsx`) | Tabel kombinasi bisa diedit di formulir produk + "isi sama untuk semua" | Agent |
| 19 | Back-office | Tidak ada aksi massal di halaman mana pun walau `TabelData` mendukung | Produk (kategori, harga %, arsip, tampil di POS), hutang/piutang (bayar terpilih) | Agent |
| 20 | Back-office | Pembelian tempo tanpa PO butuh penerimaan + faktur (nomor faktur wajib) + pembayaran | "Bayar nanti (tempo X hari)" di penerimaan → faktur otomatis, nomor opsional | Agent |
| 21 | Back-office | Harga beli tidak terisi otomatis di PO/penerimaan (`AturanPembelian.ts:43`) | Isi harga beli terakhir per pemasok | Agent |
| 22 | Back-office | Penyesuaian stok masuk wajib "Harga modal" per baris (`Penyesuaian/Form.tsx:342`) | Isi otomatis HPP rata-rata, bisa diubah | Agent |
| 23 | Awal | Pajak/PKP ditanya di 3–4 tempat dengan DPP, PBJT, NITKU; warung non-PKP tetap lewat halaman Pajak | Satu pertanyaan PKP di Profil; lewati langkah Pajak bila non-PKP & bukan F&B; NITKU/DPP di "Lanjutan" | Agent |
| 24 | Awal | QRIS dinamis: dua tempat + jargon (MDR, Lingkungan, kredensial, callback) | Wizard "Hubungkan QRIS" yang sekaligus membuat metode QRIS | Agent |
| 25 | Kasir | PIN supervisor: pilih nama dulu, tiap void/retur/batal item meja per item (`DialogPinSupervisor`) | Supervisor tunggal terpilih otomatis; persetujuan berlaku beberapa menit; batal beberapa item satu PIN | Agent (masa berlaku persetujuan: **Pemilik**) |
| 26 | Kasir | Kunci layar 5 menit walau keranjang berisi / menunggu QRIS (`RuangKerja.dart:271`) | Tidak mengunci saat transaksi berjalan; bawaan 15 menit | Agent |
| 27 | Kasir | Alasan void/retur/selisih diketik bebas, batas minimum tidak seragam | Chip alasan siap pakai + "Lainnya" | Agent |
| 28 | Kasir | Hitung pecahan hanya +/- (37 lembar = 37 ketukan, `HitungPecahan.dart`) | Jumlah bisa diketik + tombol +10 | Agent |
| 29 | Kasir | Item meja tersimpan: ketuk langsung tawarkan batal, +/- ditolak | "+" membuat baris baru yang sama; ketuk buka rincian | Agent |
| 30 | Kasir | Istilah: Void, Laporan X/Z, Kanal, Marketplace, Antrean kirim, approval EDC, pre-order/DP; kode teknis di layar Sinkron | Bahasa sehari-hari; label lengkap untuk semua jenis outbox | Agent |
| 31 | Kasir | Absen terpisah dari masuk kasir (dua kali PIN, ±17 ketukan); absen keluar harus keluar dulu | Tawarkan absen masuk saat masuk pertama hari itu & absen keluar di Laporan tutup shift | Agent |
| 32 | Kasir | Mode latihan bisa dinyalakan siapa pun tanpa PIN → penjualan sungguhan bisa hilang | Wajib PIN supervisor + peringatan di tombol Bayar | Agent |
| 33 | Semua | Menu samping 12 entri + 58 sub-menu tanpa saring sektor; banyak gembok | Saring per sektor & fitur aktif; "Mode sederhana" untuk paket kecil | **Pemilik** (anggaran navigasi D-27) |
| 34 | Awal | Tiga cara menambah orang (undang, tambah langsung, karyawan) + 58 izin & 11 peran | Satu "Tambah staf"; 3–4 peran ringkas; izin rinci di "Sesuaikan" | Agent |

## Gelombang 3 — sisa (Sedang–Rendah)

Laporan penjualan 11 tab (ganti nama "Analisis ABC", "Menu engineering", ekspor xlsx); formulir promo ±35 isian (wizard
template); stok opname 4 tahap; tutup buku per bulan via menu titik-tiga; bayar gaji meminta akun beban & edit per baris;
hutang/piutang terbelah dua menu; isian Coretax di formulir produk biasa; mode Sederhana/Lengkap produk berpindah sendiri
& tanpa barcode; penerimaan toleransi 0%; pertanyaan ganda (nama usaha 3×, zona waktu); Pengaturan 25 butir berjargon
(COA, webhook, modifier); form outlet 6 isian wajib; tiga daftar "yang harus dikerjakan"; printer/struk terpecah web &
kasir; `/daftar` dengan ulangi sandi & pilihan paket; dialog naik paket tanpa manfaat; kasir: layar selesai menelan
pindaian, tumpukan baris kepala keranjang, Tempo/Deposit hilang tanpa petunjuk, modal awal dari nol, pesan metode bayar
kosong menyalahkan internet, catatan item teks bebas, tandai habis tersembunyi, QRIS statis dua konfirmasi; Aplikasi
Pemilik tanpa "Lupa kata sandi".

Rincian bukti per butir ada di laporan penyisiran sesi 3 Oktober 2026; butir dikerjakan berurutan dan dicatat di
riwayat versi PRD saat selesai.
