# Audit kemudahan pemakaian (D-23)

Ukuran: jumlah **halaman** yang harus dibuka dan **isian** yang harus diketik/dipilih untuk tugas harian pemilik usaha
kecil. Angka "sebelum" dihitung dari kode per 27 September 2026 (PRD v2.12). Kolom "sesudah" diisi setiap bagian D-23
selesai.

| Tugas | Sebelum | Masalah | Target D-23 | Sesudah |
|---|---|---|---|---|
| Tambah 1 produk siap jual | 2 halaman (Tambah produk → tab/halaman Harga), 11 isian di form produk (nama, nama struk, SKU, jenis, kategori, merek, satuan dasar, pelacakan, harga termasuk pajak, boleh minus, …) + harga di halaman terpisah | Harga jual tidak ada di form tambah produk; isian lanjutan tampil untuk semua orang | 1 halaman, 3 isian wajib (nama, harga, kategori), sisanya bawaan & dilipat (B) | **B selesai:** 1 layar, 4 isian (nama, jenis, harga jual, kategori), 2 wajib (nama, harga); satuan/pajak/SKU otomatis |
| Jual paket sesi baru | 2 halaman (buat produk Jasa → Paket sesi → pilih produk) | Dua tempat untuk satu hal | Centang "Jual sebagai paket sesi" di form produk (B) | **B selesai:** 1 halaman, centang + jumlah sesi (+ masa berlaku opsional) |
| Mengetahui transaksi offline yang perlu dicek | Buka Penjualan (saring tinjauan), Retur, Shift, Isi deposit, Saldo paket sesi satu per satu; tidak ada tanda "sudah dicek" | Tersebar di ≥ 5 halaman, tinjauan tidak pernah selesai | 1 halaman Kotak Tindakan + tombol "Tandai sudah dicek" (C) | **C selesai:** 1 halaman (`/kelola/tindakan`, ringkasan di Beranda), pilih semua + 1 klik tandai |
| Tahu stok yang harus dibeli | Laporan stok → saring kritis → buat PO manual per produk | Tidak ada ajakan tindakan | Kotak Tindakan menampilkan stok kritis (C), draf PO otomatis (D) | C selesai: stok kritis tampil di Beranda/Kotak Tindakan dengan tautan; D menyusul |
| Tahu piutang pelanggan / hutang pemasok jatuh tempo | Buka Piutang dan Hutang, saring umur | Harus ingat membuka | Kotak Tindakan (C), pengingat WA otomatis (D) | C selesai: piutang lewat jatuh tempo & faktur jatuh tempo ≤ 7 hari tampil otomatis; D menyusul |
| Shift lupa ditutup, bulan lalu belum ditutup buku | Tidak ada pengingat | Laporan & kas tidak akurat tanpa disadari | Kotak Tindakan (C), tutup otomatis bila aman (D) | C selesai: shift > 24 jam & bulan lalu belum ditutup (mulai tgl 10) tampil otomatis; D menyusul |
| Mulai pakai (usaha baru) | Wizard + atur satuan, kategori, pajak, metode bayar, meja, stasiun, produk satu per satu | Lama sebelum transaksi pertama | Pilih sektor → data awal lengkap, impor Excel/tempel teks (A) | |
| Fitur di luar paket | Tergantung halaman: sebagian menolak dengan pesan | Tidak ada ajakan jelas | Menu tetap tampil, klik → dialog naik paket / beli add-on | |
