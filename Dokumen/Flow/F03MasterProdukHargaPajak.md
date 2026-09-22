<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-03 · Master Produk, Harga & Pajak

**Tujuan:** Katalog lengkap yang mendukung semua sektor.

**Jenis produk (`Produk.Jenis`, enum):**

| Tipe | Keterangan | Punya stok? | Contoh |
|---|---|---|---|
| `Stok` | Barang dagang biasa | Ya | Sabun, kaos |
| `IndukVarian` | Induk varian (tidak dijual langsung) | Tidak (anak yang punya stok) | Kaos → S/M/L × Merah/Biru |
| `Resep` | Produk jadi dari resep; stok bahan berkurang saat terjual | Tidak (bahan yang berkurang) | Es kopi susu |
| `Produksi` | Diproduksi dulu (batch), lalu punya stok | Ya | Roti, kue |
| `Paket` | Paket beberapa produk; stok komponen berkurang | Tidak | Paket hemat |
| `Jasa` | Jasa | Tidak | Potong rambut, cuci motor |
| `NonStok` | Tanpa stok | Tidak | Biaya kirim, kantong plastik gratis |
| `BahanBaku` | Bahan baku (tidak tampil di POS) | Ya | Susu, gula, biji kopi |
| `Konsinyasi` | Titipan | Ya (bukan aset) | Kue titipan |

**Atribut penting produk:**
- SKU (unik per tenant), barcode (bisa banyak per produk/satuan), nama, nama struk (pendek), kategori (bertingkat), brand, gambar.
- **Satuan & konversi:** satuan dasar (pcs) + satuan alternatif (pak = 10 pcs, dus = 12 pak). Harga & barcode boleh berbeda per satuan. Qty desimal diizinkan per produk (kg, meter).
- **Modifier group:** misal "Level Gula" (wajib, pilih 1), "Topping" (opsional, maks 3, masing-masing berharga & opsional mengurangi stok bahan).
- **Resep/BOM:** daftar bahan × qty × satuan, termasuk *yield* & *waste %*. Contoh: 1 cup Es Kopi Susu = 18 g kopi + 150 ml susu + 20 ml gula aren + 1 cup + 1 sedotan.
- **Pelacakan (`Produk.Pelacakan`):** `Tidak` | `Batch` (dengan tanggal kedaluwarsa) | `Seri`.
- **Pajak:** kategori pajak produk (Kena PPN, Bebas PPN, Kena PB1, Non-pajak) dan flag harga *termasuk pajak* / *belum termasuk pajak*.
- **HPP:** metode per tenant: **Moving Average (default)** atau **FIFO**.
- Min/Max stok per lokasi (untuk restock), flag "tampil di POS", "tampil di toko online", "boleh jual saat stok kosong".

**Harga (Price Engine):**

Harga final ditentukan berlapis (prioritas tinggi ke rendah):
1. Harga manual kasir (butuh izin `pos.harga.timpa`)
2. Promo aktif (Promo Engine, F-16)
3. **Price List** yang cocok (kombinasi outlet × channel × tier pelanggan × rentang waktu)
4. Harga bertingkat qty (tiered): 1–11 = Rp 5.000, 12+ = Rp 4.500
5. Harga dasar produk per satuan

**Aturan Bisnis:**
- BR-03.1 SKU unik per tenant. Barcode unik per tenant (boleh sama lintas tenant).
- BR-03.2 Produk yang sudah punya transaksi tidak bisa dihapus, hanya diarsipkan.
- BR-03.3 Perubahan harga dicatat di tabel `RiwayatHarga` (kapan, siapa, lama/baru).
- BR-03.4 Perubahan resep **tidak** mengubah transaksi lampau (resep di-snapshot saat penjualan untuk kalkulasi HPP).
- BR-03.5 HPP produk resep = Σ (qty bahan × HPP bahan saat itu) / yield.
- BR-03.6 Import massal memakai validasi baris per baris dengan laporan error yang bisa diunduh. Import besar diproses di antrian (queue).
