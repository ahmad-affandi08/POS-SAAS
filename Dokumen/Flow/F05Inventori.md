<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-05 · Inventori

**Sumber kebenaran:** tabel `MutasiStok` (ledger append-only). Saldo di tabel `SaldoStok` adalah cache yang bisa dibangun ulang dari ledger.

**Sub-flow:**

| Kode | Sub-flow | Keterangan |
|---|---|---|
| F-05a | **Stok Awal** | Input/import saldo awal per lokasi + HPP awal. Jurnal: Dr Persediaan, Cr Ekuitas Saldo Awal. |
| F-05b | **Transfer Antar Lokasi/Outlet** | `Draf → Dikirim (DalamPerjalanan) → Diterima` (parsial boleh). Selisih kirim vs terima → penyesuaian dengan alasan. |
| F-05c | **Stock Opname** | Snapshot stok sistem saat mulai; hitung fisik (scan/input, bisa beberapa orang, per rak/kategori); review selisih; approve → penyesuaian otomatis. Opsi *blind count* (penghitung tidak melihat qty sistem). |
| F-05d | **Penyesuaian Stok** | Rusak, hilang, kadaluarsa, sampel, konsumsi internal. Wajib alasan + approval di atas nilai tertentu. |
| F-05e | **Produksi / Rakitan** | Order produksi: konsumsi bahan (resep) → hasil produk jadi. HPP produk jadi = total HPP bahan + biaya overhead opsional. |
| F-05f | **Bahan Terbuang F&B** | Pencatatan bahan terbuang harian (untuk kontrol food cost). |
| F-05g | **Batch & Expired** | Penjualan mengambil batch otomatis dengan FEFO (First Expired First Out). Notifikasi H-30/H-7 sebelum kadaluarsa. |
| F-05h | **Serial/IMEI** | Setiap unit punya serial. Penjualan wajib pilih serial. Riwayat serial dari masuk hingga garansi. |
| F-05i | **Konsinyasi** | Stok titipan tidak menambah aset. Saat terjual → hutang konsinyasi ke penitip. Settlement periodik. |

**Jenis mutasi stok (`MutasiStok.JenisMutasi`, enum):**
`StokAwal`, `PenerimaanPembelian`, `ReturPembelian`, `Penjualan`, `ReturPenjualan`, `TransferKeluar`, `TransferMasuk`, `PenyesuaianMasuk`, `PenyesuaianKeluar`, `OpnameLebih`, `OpnameKurang`, `ProduksiPakai`, `ProduksiHasil`, `Susut`, `KonsinyasiMasuk`, `KonsinyasiRetur`.

**Aturan Bisnis:**
- BR-05.1 Setiap movement menyimpan: produk, lokasi, qty (±, dalam satuan dasar), HPP per unit saat itu, nilai, referensi dokumen (polymorphic), batch/serial, user, waktu.
- BR-05.2 Stok negatif **diizinkan per konfigurasi** (default: diizinkan untuk F&B resep, dilarang untuk apotek/serial).
- BR-05.3 Selama opname berlangsung untuk sebuah lokasi, transaksi tetap berjalan. Qty penyesuaian = fisik − (snapshot + movement selama opname).
- BR-05.4 Penjualan produk resep mengurangi bahan pada **lokasi produksi** yang ditentukan (misal "Dapur"/"Bar"), bukan lokasi toko.

**Rincian F-05b (v1.51, back-office; diputuskan agen atas mandat D-12):**
- **Transfer stok** `TF/{ASAL}-{TUJUAN}/{YYMM}/{SEQ4}` antar lokasi stok (antar outlet atau dalam outlet): `Draf → Dikirim → DiterimaSebagian → Diterima` (+ `Dibatalkan` sebelum dikirim). Kirim = mutasi `TransferKeluar` dari asal ke lokasi `DalamPerjalanan` (dinilai HPP asal; J-05.2 Dr `PersediaanDalamPerjalanan`, Cr persediaan); terima (boleh parsial) = `TransferMasuk` ke tujuan bernilai HPP kirim (J-05.3). Selisih kirim vs terima wajib alasan dan menjadi penyesuaian keluar (`Susut`, J-05.4) saat transfer ditutup. Izin `persediaan.kelola`; penerima dibatasi outlet aksesnya.
- **Stok opname** `SO/{LOKASI}/{YYMM}/{SEQ3}` per lokasi stok (seluruh produk atau per kategori): mulai = snapshot saldo sistem; lembar hitung (ketik/pindai, beberapa kali simpan), opsi hitung buta (jumlah sistem disembunyikan); tinjau selisih; setujui (izin `persediaan.penyesuaian.setujui`) → mutasi `OpnameLebih`/`OpnameKurang` dengan jumlah = fisik − (snapshot + mutasi sejak snapshot) (BR-05.3), dinilai HPP berjalan; J-05.4/J-05.5 ke `SelisihHpp`/`SusutPersediaan`. Transaksi tetap berjalan selama opname; satu opname aktif per lokasi (dan per kategori bila parsial).
- **Penyesuaian stok** `PS/{LOKASI}/{YYMM}/{SEQ4}`: alasan wajib (Rusak, Hilang, Kedaluwarsa, Sampel, KonsumsiInternal, Lainnya + keterangan); keluar = `PenyesuaianKeluar`/`Susut` dinilai HPP berjalan (J-05.4 ke akun sesuai alasan: `SusutPersediaan` atau beban lain yang dipetakan), masuk = `PenyesuaianMasuk` dengan nilai per satuan wajib (J-05.5). Nilai di atas `BatasPersetujuanPenyesuaian` (bawaan Rp 500.000, §19.2) butuh persetujuan `persediaan.penyesuaian.setujui` oleh orang lain sebelum diposting.
- Produk batch/seri: transfer, opname, dan penyesuaian menyebut batch/nomor seri per baris. Semua dokumen append-only (koreksi dengan dokumen baru), stok & jurnal di transaksi yang sama, daftar & detail di back-office dengan `TabelData`; aplikasi gudang (`/api/pos/v1/gudang/*`) menyusul.
- **Keputusan implementasi F-05b (v1.52):** lokasi "Dalam perjalanan" dibuat otomatis satu per outlet asal (kode `TRANSIT[-n]`), bukan lokasi jual. Opname seluruh produk tidak boleh berbarengan dengan opname lain di lokasi itu; opname kategori tidak boleh berbarengan dengan opname seluruh produk atau kategori yang sama (`OpnameAktifSudahAda`); snapshot per produk bersaldo ≠ 0, per batch bersisa, per nomor seri tersedia; idempoten per Uuid klien. Jumlah minus ditampilkan dengan tanda "−".

**Rincian F-05a (v1.33, diputuskan agen atas mandat D-12):**
- Dokumen `StokAwal` per lokasi stok: `Draf → Memproses → Diposting → Dibatalkan`, dan `Draf → Dibuang`. Draf **tidak pernah dihapus** (status `Dibuang`), dokumen terposting tidak diedit; pembatalan = mutasi pembalik + jurnal pembalik (`IdJurnalDibalik`, `KunciSumber = Pembatalan`) dengan alasan wajib. Pembatalan ditolak (`StokSudahTerpakai`) bila stok dari dokumen itu sudah terpakai (FIFO: lapisannya sudah dikonsumsi).
- Posting berizin `persediaan.stok-awal.posting`; dokumen besar diposting lewat antrean (status `Memproses`, halaman memantau `/status`), galat aturan bisnis di antrean mengembalikan dokumen ke `Draf` dengan `PesanGalat`. Satu stok awal Diposting per (produk, lokasi) (`StokAwalSudahAda`); tanggal tidak boleh di masa depan atau sebelum mutasi terakhir pasangan itu. Maks. 2.000 baris per dokumen.
- Jumlah dalam **satuan dasar**, HPP per satuan dasar. Produk konsinyasi ditolak (menunggu F-05i). Batch wajib bertanggal kedaluwarsa (`WajibKedaluwarsaBatch`, dapat dikonfigurasi); produk Seri: jumlah = banyaknya nomor seri, nilai dialokasikan per nomor tanpa selisih pembulatan. Stok awal setelah stok minus diperbolehkan; selisih HPP (BR-04.3) dijurnal ke `SelisihHpp`.
- Stok awal bernilai nol diposting tanpa jurnal. Produk berjenis Produksi dijurnal ke `PersediaanBarangDagang` sampai J-05.6 (F-05e).
- Metode HPP per tenant (`RataRataBergerak`/`Fifo`, bukan per produk); **terkunci** setelah ada mutasi (`MetodeHppTerkunci`); alat konversi menyusul. HPP skala 6, nilai skala 2 dengan pembulatan HalfUp; Q = 0 ⇒ nilai = 0. Uji HPP berupa contoh & uji properti di PHP saja (POS tidak menghitung HPP), bukan test vector bersama.
- Ledger: `CatatMutasiStok` satu-satunya penulis `MutasiStok`/`SaldoStok`/`LapisanFifo`, sinkron dalam transaksi pemanggil, urutan kunci tetap (Tenant S → idempotensi → SaldoStok → batch → nomor seri → lapisan FIFO), idempoten per (JenisReferensi, IdReferensi, KunciBaris): kirim ulang = `sudahAda`, sebagian tercatat = `MutasiGanda`; idempotensi diperiksa sebelum kunci periode. Batch tidak pernah minus walau stok boleh minus. Stok tidak cukup memakai kode `StokTidakCukup`. Nilai/saldo/HPP yang melampaui kolom DECIMAL ditolak (`JumlahTidakValid`/`HppTidakValid`), bukan galat server; `Uuid` klien yang sudah dipakai ditolak `UuidSudahDipakai`.
- Pelacakan produk (`Pelacakan`) tidak bisa diubah setelah ada riwayat stok (`PelacakanTerkunci`).
- Impor stok awal: xlsx/csv dengan penjaga berkas F-03, pemetaan kolom, validasi per baris (≤ 300 baris langsung, di atasnya antrean), baris valid dijadikan **draf** per lokasi (dipecah per 2.000 baris) dan **tidak pernah diposting otomatis**. Berkas dipangkas setelah 30 hari; catatan impor yang dirujuk dokumen tetap disimpan sebagai jejak asal.
- Saldo & kartu stok (izin `persediaan.lihat`, HPP ikut terlihat; saringan, urutan, ringkasan, dan paginasi saldo dikerjakan di SQL lewat subkueri publik Katalog `InfoProdukStok::KueriIdNama`, hanya satu halaman yang dimuat), pengaturan persediaan (izin `akuntansi.kelola`). `SaldoStok` adalah cache: `persediaan:bangun-ulang-saldo {--tenant=*} {--periksa}` membangun ulang dari `MutasiStok` dan dijadwalkan memeriksa tiap malam 02:30 WIB.
- Inti jurnal dibangun di F-05a (bukan menunggu F-13): `PostingJurnal` (seimbang, tidak nol, idempoten per sumber, kunci periode, nomor `JU/YYYY/MM/NNNNNN` per tenant) dan `BalikkanJurnal` (sekali per jurnal). Jurnal otomatis diaudit lewat dokumen sumbernya. Penyusun jurnal per domain (`PenyusunJurnalStokAwal` + `PetaAkunPersediaan`) memanggil inti jurnal; pola ini baku sampai F-13 memusatkan `AturanPosting`. Halaman jurnal memakai izin `laporan.keuangan.lihat`.
