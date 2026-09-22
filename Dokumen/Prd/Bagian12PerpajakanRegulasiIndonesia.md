<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 12. Perpajakan & Regulasi Indonesia

> ⚠️ Aturan pajak di Indonesia sering berubah. **Tidak ada tarif yang di-hard-code.** Semua tarif disimpan di tabel `TarifPajak` dengan `BerlakuMulai`/`BerlakuSampai`, dan diperbarui oleh Super Admin (default nasional) atau tenant (tarif daerah). Nilai di bawah adalah default awal yang **wajib diverifikasi ulang oleh konsultan pajak** sebelum rilis.

### 12.1 Jenis Pajak yang Didukung

| Pajak | Berlaku untuk | Default | Catatan implementasi |
|---|---|---|---|
| **PPN** | Tenant berstatus PKP yang menjual BKP/JKP (retail, grosir, jasa kena pajak) | Tarif 12% dengan **DPP nilai lain 11/12** dari harga jual untuk barang/jasa non-mewah, sehingga beban efektif 11% | Model pajak mendukung `Tarif` + `PengaliDpp` (DPP). Barang mewah memakai DPP penuh. |
| **PB1 / PBJT Makanan & Minuman** | Restoran/kafe (pajak daerah, UU HKPD) | 10% (maksimal, tarif ditetapkan Perda masing-masing kab/kota) | Tarif per outlet sesuai kota. Restoran yang dikenai PBJT **tidak dikenai PPN** atas makanan/minuman tersebut. |
| **Service Charge** | F&B (bukan pajak) | 0–10% (konfigurasi) | Bisa masuk DPP PB1 sesuai konfigurasi daerah. |
| **PPh Final UMKM** | Info untuk owner (0,5% omzet bagi WP yang memenuhi syarat) | Laporan pendukung omzet bulanan | v1 hanya **laporan estimasi**, bukan pemotongan otomatis. |
| **Pajak lain** | Pajak hiburan, parkir, dsb. | Custom | Tenant dapat membuat jenis pajak kustom. |

### 12.2 Struktur Model Pajak

```
JenisPajak:     Ppn, PbjtMakananMinuman, Kustom...
TarifPajak:     IdJenisPajak, Tarif (decimal), PengaliDpp (decimal, default 1),
                KodeWilayah (nullable), BerlakuMulai, BerlakuSampai
KelompokPajak:  kombinasi pajak untuk satu kategori produk (misal "F&B Dine-in" = PBJT 10% + SC 5%)
Produk.IdKelompokPajak, Outlet.ProfilPajak (PKP? kota?), HargaTermasukPajak (per tenant/outlet)
```

- Perhitungan pajak dilakukan **per baris**, dan pembulatan dilakukan **per dokumen per jenis pajak** agar sesuai dengan cara pelaporan.
- Transaksi menyimpan snapshot `TarifPajak`, `PengaliDpp`, `DasarPengenaanPajak`, dan `JumlahPajak` per baris (`PenjualanDetail.SnapshotPajak`).

### 12.3 Kepatuhan Lain

| Area | Kebutuhan | Implementasi |
|---|---|---|
| **UU PDP No. 27/2022** (Perlindungan Data Pribadi) | Dasar pemrosesan, hak subjek data (akses, hapus), keamanan, notifikasi insiden | Kebijakan privasi, persetujuan pemasaran (opt-in WA/email), fitur export & anonimisasi data pelanggan, enkripsi field sensitif (NIK, no. HP opsional), log akses, prosedur insiden. |
| **E-Faktur / Coretax DJP** | Faktur pajak untuk PKP | Export data faktur dalam format impor yang berlaku saat itu (XML/Excel sesuai ketentuan DJP). Integrasi langsung via PJAP di fase 4. |
| **Struk** | Mencantumkan identitas usaha, NPWP (jika PKP), rincian pajak | Template struk mendukung semua field ini. |
| **QRIS (Bank Indonesia)** | Transaksi QRIS dan MDR sesuai ketentuan | MDR dikonfigurasi per metode, bukan hard-code. Dinamis via PJP berlisensi (payment gateway). |
| **Retensi dokumen** | Dokumen pembukuan disimpan bertahun-tahun (ketentuan perpajakan umumnya 10 tahun) | Data transaksi tidak pernah dihapus fisik. Arsip export tahunan. |
| **Perlindungan konsumen** | Harga jelas, struk | Harga tampil inklusif pajak di self-order/toko online jika dikonfigurasi. |
| **Mata uang & format** | IDR tanpa desimal di tampilan, pemisah ribuan titik | `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })` |
