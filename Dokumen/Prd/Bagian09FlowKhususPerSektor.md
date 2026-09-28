<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 9. Flow Khusus per Sektor

### 9.1 F&B Restoran (Mode `table`)

```mermaid
sequenceDiagram
    participant W as Pelayan/Kasir
    participant POS
    participant KDS as KDS/Printer Dapur
    participant C as Pelanggan
    W->>POS: Pilih meja 7, jumlah tamu 4
    W->>POS: Tambah item + modifier + catatan
    POS->>KDS: Kirim order (per station: Dapur/Bar)
    KDS-->>POS: Status item: cooking → ready
    W->>POS: Tambah item susulan (ronde 2)
    POS->>KDS: Kirim item susulan saja
    C->>W: Minta bill
    W->>POS: Cetak pre-bill (belum lunas)
    C->>W: Bayar (split: 2 orang QRIS, 2 orang tunai)
    POS->>POS: Split bill → 2 pembayaran, meja kosong
```

Fitur khusus:
- Denah meja visual (drag & drop editor), area (Indoor/Outdoor/VIP), status warna (kosong/terisi/minta bill/perlu dibersihkan).
- Pindah meja, gabung meja, gabung bill, pisah bill.
- Kursus/course (appetizer, main, dessert) dengan "tahan & kirim" (hold & fire).
- Reservasi meja dengan DP (fase 3).
- Minimum charge per meja/area (VIP).
- **Menu engineering:** klasifikasi Star/Plowhorse/Puzzle/Dog berdasarkan popularitas × margin.
- **Food cost %** harian: HPP teoretis (resep) vs HPP aktual (opname), sehingga selisih pemakaian bahan terlihat.

### 9.2 Kafe / QSR (Mode `quick`)

- Grid tombol besar per kategori, favorit, pencarian.
- Modifier wajib muncul sebagai pop-up cepat.
- Nomor order/antrian otomatis, layar panggil antrian.
- Mode "bayar dulu" (default QSR) vs "open bill" (kafe duduk).
- Customer Display (layar kedua) menampilkan pesanan & QRIS.

### 9.3 Retail Umum / Minimarket (Mode `retail`)

- Fokus input scanner (keyboard wedge). Kursor selalu di field scan.
- Shortcut keyboard (F1 cari, F2 pelanggan, F8 bayar, F9 tunai pas, Esc batal item).
- Barcode timbangan (prefix 20–29: harga/berat terenkode di barcode EAN-13).
- Multi-satuan otomatis dari barcode (scan barcode dus → satuan dus).
- Cek harga cepat tanpa menambah ke keranjang.
- Label harga & barcode cetak massal (fase 2).

### 9.4 Fashion

- Matrix varian ukuran × warna (input stok & harga dalam grid).
- Tukar barang (ukuran) dalam satu layar.
- Koleksi/musim, markdown (diskon cuci gudang) terjadwal.

### 9.5 Apotek / Toko Obat

- Batch & expired wajib, FEFO otomatis.
- Golongan obat (bebas, bebas terbatas, keras, psikotropika/narkotika). Obat keras wajib **input resep** (nama dokter, no. resep) dan hanya bisa dijual oleh role Apoteker.
- Harga HNA + margin → HJA, embalase/tuslah (biaya racik).
- Racikan: resep racik sebagai produk `recipe` sementara.
- Laporan obat mendekati kadaluarsa & laporan penjualan obat keras.
- ⚠️ Kepatuhan laporan ke regulator (misal SIPNAP) di luar lingkup v1. Sistem menyediakan export data pendukung.

### 9.6 Elektronik (Serial/IMEI)

- Serial wajib saat GRN dan saat jual. Pencarian riwayat serial.
- Kartu garansi (tanggal jual + masa garansi) tercetak di struk.
- Modul servis sederhana (terima unit, estimasi, status, ambil) memakai Work Order (§9.10).

### 9.7 Grosir & Distributor (Mode `wholesale`)

- **Sales Order** (SO) → Delivery Order (DO) → Invoice → Piutang → Pelunasan.
- Harga per level pelanggan, harga bertingkat qty, harga khusus per pelanggan.
- Salesman lapangan (kanvas/taking order) via **modul Salesman di aplikasi Flutter** (HP Android/iPhone), bisa offline: ambil order, lihat stok, lihat piutang pelanggan, kunjungan (check-in lokasi).
- Pengiriman: rute, armada, surat jalan, konfirmasi terima.
- Retur dari toko, nota kredit.
- Limit kredit & blokir otomatis.

**Rincian grosir bagian 1 — keputusan istilah, akuntansi & pajak (v2.74; menutup §25 no. 27; pemilik produk: "kerjakan, berikan yang terbaik dan sesuai peraturan di Indonesia", jadi rinciannya diputuskan agen atas mandat D-12 & D-32):**

- **Tiga dokumen, tiga tabel** (kamus §13.7.1): `PesananGrosir` (SO) → `SuratJalan` (DO) → `FakturPenjualan`. `PesananPenjualan` **tidak** dipakai ulang meskipun catatan v1.68 menyebut begitu (dibalik dengan persetujuan pemilik produk 28/09/2026, lihat D-32): tabel itu menuntut perangkat, shift, dan kasir karena dibuat di POS, nomornya memuat kode perangkat, dan alurnya `Dipesan → Siap → Diambil` sekali ambil — sedangkan SO grosir dibuat admin penjualan di back-office tanpa perangkat/shift dan dipenuhi **beberapa kali kirim**. Memaksakan keduanya ke satu tabel berarti dua state machine dalam satu baris dan setiap kueri pre-order harus disaring per jenis.

- **BR-12.2 Titik pengakuan = penyerahan barang (surat jalan), bukan faktur.** Ini ditentukan aturan, bukan selera:
  - **PPN.** UU PPN (UU 8/1983 sdtd UU 7/2021) Pasal 11 ayat (1): PPN terutang pada saat **penyerahan** Barang Kena Pajak; PP 44/2022 menegaskan penyerahan barang bergerak terjadi saat barang diserahkan langsung kepada pembeli atau diserahkan kepada juru kirim/pengangkut. Pasal 11 ayat (2): bila pembayaran diterima sebelum penyerahan, PPN terutang saat pembayaran.
  - **Pendapatan.** PSAK 72 mengakui pendapatan saat kendali atas barang berpindah, yang untuk penjualan barang berstok terjadi di penyerahan.
  - Karena itu **kedua usulan di §25 no. 27(b) ditolak**: usulan pertama memisahkan HPP (di surat jalan) dari pendapatan (di faktur) sehingga melanggar penandingan biaya-pendapatan dan PSAK 72; usulan kedua ("Persediaan Terkirim Belum Difakturkan") menunda pendapatan **dan PPN** ke faktur, sehingga PPN bulan penyerahan dilaporkan kurang dan SPT Masa tidak cocok dengan penyerahan sesungguhnya.
  - Yang dipakai adalah **cermin sisi pembelian yang sudah berjalan di PAYOU** (J-04.1 barang diterima sebelum faktur pemasok → *Hutang Belum Difakturkan*, lalu J-04.2 faktur membersihkannya): sisi penjualan memakai peran akun baru **`PiutangBelumDifakturkan`** (tipe Aset, kode **1-1470**, melanjutkan blok piutang 1-1400/1-1450/1-1460). Penambahannya mengikuti cara `OverheadProduksiDibebankan` (v2.29), karena **tidak ada mekanisme peran "opsional"**: BR-P03.3 mewajibkan setiap peran terpetakan dan `ValidatorTemplate` memeriksanya. Jadi peran ini ditambahkan ke **ketiga template sektor bawaan** (akun + pemetaan), sedangkan **tenant yang sudah menerapkan template lama** mendapat akunnya otomatis saat jurnal pertama lewat `PenyediaAkunPeran::Pastikan()`. Konsekuensi yang diketahui (§25 no. 16b): versi template yang sudah terbit tanpa peran ini akan diminta memetakannya bila kelak disunting di konsol — itu perilaku yang benar, bukan regresi, dan tidak menyentuh tenant yang sudah berjalan.
  - Catatan asimetri yang memang benar: PPN **Masukan** baru diakui di faktur pembelian (J-04.2) karena pajak masukan hanya bisa dikreditkan berdasarkan Faktur Pajak (UU PPN Pasal 9), sedangkan PPN **Keluaran** mengikuti penyerahan. Jadi bukan salah satu dari keduanya yang keliru.

- **BR-12.3 Jurnal grosir.** **J-12.1** surat jalan diposting: Dr **HPP** / Cr Persediaan (nilai HPP berjalan, aturan #9 lewat `MutasiStok`), **dan** Dr **Piutang Belum Difakturkan** / Cr **Penjualan** + Cr **PPN Keluaran** (+ Dr Diskon Penjualan bila ada). **J-12.2** faktur penjualan diposting: Dr **Piutang Usaha** / Cr **Piutang Belum Difakturkan** — reklasifikasi saja, tidak ada pendapatan maupun PPN yang bergerak lagi, sehingga faktur tidak bisa menggandakan pendapatan. **J-12.3** pembatalan surat jalan terposting = jurnal pembalik + stok kembali, hanya bila surat jalan itu belum difakturkan.

- **BR-12.4 Faktur gabungan & batas waktunya.** UU PPN Pasal 13 ayat (2) & (2a) mengizinkan **satu Faktur Pajak gabungan** untuk seluruh penyerahan kepada pembeli yang sama dalam **satu bulan kalender**, dibuat paling lama pada akhir bulan penyerahan. Karena itu `FakturPenjualan` boleh memuat **beberapa surat jalan** milik satu pelanggan, dengan syarat semuanya di bulan kalender yang sama (beda bulan = ditolak `SuratJalanBedaBulan`). Butir Kotak Tindakan **"Surat jalan belum difakturkan"** muncul untuk surat jalan terposting yang bulan penyerahannya berjalan atau sudah lewat; karena pendapatan & PPN sudah dibukukan di penyerahan, ini **peringatan kepatuhan**, bukan syarat agar pembukuannya benar — tutup bulan tidak diblokir olehnya. Nomor Faktur Pajak dari e-Faktur/Coretax diisi sebagai kolom terpisah (`NomorFakturPajak`, boleh kosong; ekspor Coretax tetap fase 3).

- **BR-12.5 Piutang dari faktur.** `Piutang.IdPenjualan` dijadikan **boleh kosong** dan ditambah `IdFakturPenjualan` (migrasi **expand**, aturan #15; tepat satu dari keduanya wajib terisi, dijaga di aksi dan test). Dengan begitu umur piutang, ringkasan aging, pengingat, pembatalan, dan **Pelunasan piutang** F-12 bagian 1 dipakai ulang apa adanya. Jatuh tempo faktur = tanggal faktur + `Pelanggan.TerminHari`.

- **BR-12.6 Limit kredit di grosir.** BR-12.1 dipakai ulang, tetapi penyetujunya bukan PIN kasir: konfirmasi SO yang melampaui limit (sisa piutang + nilai SO belum terkirim + SO ini) atau pelanggan dengan piutang lewat jatuh tempo melebihi batas ditolak, kecuali pengguna memegang izin baru **`grosir.setujui-kredit`**. Alasan persetujuan wajib dan masuk audit.

- **Cakupan bagian 1** (sesuai §25 no. 27d): barang berstok tanpa resep/paket/pelacakan batch-seri; harga dari price engine yang sama dengan kasir (daftar harga bertingkat & tier pelanggan, X8); pajak lewat mesin kalkulasi yang sama dengan kasir (inklusif/eksklusif, `KelompokPajak`); satu outlet/gudang per surat jalan; izin baru **`grosir.kelola`**.
- **Menyusul:** retur grosir & nota kredit, salesman lapangan (modul Salesman fase 3), rute & armada pengiriman, konfirmasi terima oleh pembeli, giro/cek mundur, ekspor e-Faktur/Coretax, SO dari toko online.

**State Machine `PesananGrosir.Status`:** `Draf → Dikonfirmasi → SebagianDikirim → Selesai`; `Draf → Dibatalkan` dan `Dikonfirmasi → Dibatalkan` (hanya bila belum ada surat jalan terposting). **`SuratJalan.Status`:** `Draf → Diposting → Dibatalkan` (pembatalan hanya sebelum difakturkan). **`FakturPenjualan.Status`:** `Draf → Diposting → Dibatalkan` (pembatalan hanya bila piutangnya belum dibayar sebagian pun). Setiap perubahan dicatat di `RiwayatStatusDokumen`.

### 9.8 Salon / Barbershop / Spa (Mode `service`)

```
Booking online/WA → Konfirmasi → Reminder H-1 (WA) → Check-in
  → Layanan (staf ditugaskan, bahan terpakai opsional) → Pembayaran
  → Komisi staf → Follow-up/rebooking
```
- Kalender per staf (slot waktu, durasi layanan, buffer).
- Antrian walk-in + booking dalam satu tampilan.
- Paket sesi/membership & saldo deposit.
- Komisi bertingkat (staf senior/junior), komisi penjualan produk.
- Pemakaian bahan per layanan (cat rambut) sebagai resep layanan.

**Rincian F-07 mode service bagian 1 (v2.23; rincian diputuskan agen atas mandat D-12 dan urutan §22):**
- **Layanan yang bisa direservasi** = produk berjenis Jasa, aktif, dengan isian baru **Durasi layanan** (`Produk.DurasiMenit`, 5–720 menit). Reservasi online hanya layanan yang juga "Tampil di toko online". Harga yang ditampilkan = harga dasar produk (pembayaran tetap di kasir).
- **Slot**: dihitung server dari **jadwal kerja staf** (F-18 `JadwalKerja`) di outlet pada tanggal itu, mulai tiap `IntervalSlotMenit` (bawaan 30) sejak jam mulai jadwal dan selesai paling lambat jam selesai jadwal. Slot dibuang bila bentrok dengan reservasi staf yang masih memakai slot (Menunggu/Dikonfirmasi/Hadir, di outlet mana pun) ditambah `JedaMenit` sebelum/sesudah. Staf boleh dipilih atau "siapa saja" (staf kosong pertama menurut nama). Tanpa jadwal kerja = tidak ada slot (layar memberi petunjuk mengisi jadwal).
- **Anti-bentrok**: pembuatan & pindah jadwal dikunci per staf (kunci cache atomik) lalu slot dihitung ulang di dalam kunci, sehingga dua pemesan tidak mendapat staf & jam yang sama.
- **Tabel `Reservasi`**: `Nomor` `RS/YYYY/MM/NNNN`, outlet, pelanggan (ditautkan bila nomor HP terdaftar), `NamaPelanggan`, `NoHp` (dinormalisasi `62…`), layanan, staf, `MulaiPada`/`SelesaiPada` (UTC), `Status`, `Sumber` (BackOffice/Online/Pos), catatan, alasan batal, `KodeAkses` (12 karakter, untuk halaman publik), `IdPenjualan` (disiapkan untuk bagian 2), `HadirPada`, `PengingatTerkirimPada`. Transisi: Menunggu → Dikonfirmasi/Hadir/Batal; Dikonfirmasi → Hadir/Batal/TidakDatang (hanya setelah jam mulai lewat); Hadir → Selesai/Batal. Setiap perubahan dicatat di `RiwayatStatusDokumen` dan audit (bila oleh pengguna). Batal oleh toko wajib alasan.
- **Back-office `/kelola/reservasi`** (izin baru `reservasi.kelola`: Owner, Admin, Manajer Outlet, Supervisor; peran kustom bisa ditambah; dibatasi outlet akses): TabelData (cari nomor/nama/HP, saring tanggal, status, staf, outlet), **Catat reservasi** (telepon/WA/datang langsung), aksi status, **Pindah jadwal** (slot tanpa reservasi itu sendiri; pengingat dikirim ulang), **Pengaturan** (pengguna tanpa batas outlet): reservasi online, konfirmasi otomatis, interval 10–120 menit, jeda 0–60 menit, paling jauh 1–180 hari, paling cepat 0–2.880 menit sebelumnya, pengingat H-1.
- **Reservasi online** `/{slugToko}/reservasi` (tanpa login, hanya bila diaktifkan): pilih outlet, layanan, staf (opsional), tanggal (hari ini s.d. batas hari), jam kosong, nama, nomor WhatsApp, catatan, dan **persetujuan** pemakaian data untuk reservasi & pengingat (UU 27/2022 PDP). Status awal Dikonfirmasi bila konfirmasi otomatis aktif, selain itu Menunggu (butir Kotak Tindakan "Reservasi online menunggu konfirmasi"). Anti-spam: batas laju per IP dan paling banyak 3 reservasi mendatang aktif per nomor. Setelah memesan pelanggan dibawa ke `/{slugToko}/reservasi/{kodeAkses}` (status + tombol batal selama belum datang dan jam belum lewat). Slug situs pemasaran dengan bagian kedua `reservasi` ditolak.
- **Pengingat H-1**: jadwal `reservasi:kirim-pengingat` tiap jam mengantrekan WhatsApp untuk reservasi Menunggu/Dikonfirmasi yang mulai 20–28 jam lagi dan belum diingatkan (bila pengaturan pengingat aktif dan WhatsApp platform aktif). Templat resmi opsional `NamaTemplatPengingatReservasi` (toko, layanan, waktu, tautan). Nomor pelanggan tidak ikut antrean maupun log.
- **Belum di bagian 1**: lihat bagian 2 di bawah; deposit/DP reservasi, pemakaian bahan per layanan, dan follow-up/rebooking belum dikerjakan.

**Rincian F-07 mode service bagian 2 (v2.24; aplikasi kasir, rincian diputuskan agen atas mandat D-12):**
- **Antrian (online):** `GET /api/pos/v1/reservasi?tanggal=YYYY-MM-DD` (bawaan hari ini menurut zona outlet) → `{Reservasi: [{Uuid, Nomor, MulaiPada, SelesaiPada (UTC), NamaPelanggan, NoHp (terformat), Pelanggan ({Uuid, Nama, NoHp tersamar, KodeTier, NamaTier} atau null), UuidProduk, NamaLayanan, UuidStaf, NamaStaf, Status, LabelStatus, Catatan}]}` urut jam, hanya outlet perangkat. Offline = `PerluOnline` (pembayaran tetap offline-first).
- **Check-in:** `POST /api/pos/v1/reservasi/{uuid}/hadir {UuidPengguna}`; pengguna wajib anggota outlet dengan izin `penjualan.buat` atau `reservasi.kelola` (selain itu 403), reservasi outlet lain/tidak dikenal 404; sudah Hadir = idempoten; transisi lain mengikuti aturan bagian 1 (422). Riwayat status dicatat.
- **Layani di kasir:** Riwayat › **Reservasi hari ini** (panel di Ruang Kerja) → **Layani {nama}**: perangkat memeriksa layanan ada di katalog lokal dulu, lalu check-in, lalu keranjang diisi layanan (harga katalog saat ini, harga tier pelanggan bila ada) dengan **staf pelaksana** reservasi (dasar komisi F-18) dan pelanggan tertaut. Keranjang harus kosong. Panel Bayar menampilkan "Melayani reservasi {Nomor}"; tombol "Jadikan pre-order" disembunyikan. Kasir boleh menambah barang lain sebelum membayar.
- **Penyelesaian:** `Penjualan.Buat` membawa `UuidReservasi` (opsional, ULID). Server, di transaksi DB yang sama dengan penjualan, menandai reservasi Hadir (bila belum) lalu **Selesai** dan mengisi `IdPenjualan`. Reservasi tidak dikenal, di outlet lain, atau sudah Selesai/Dibatalkan/TidakDatang → penjualan tetap diterima (offline-first) + tinjauan **`Reservasi`**. Kiriman ulang item yang sama = `Duplikat` tanpa efek.
- **Walk-in** tanpa reservasi tetap dijual langsung dengan memilih staf per baris (F-18 komisi); membuat reservasi dari aplikasi kasir belum ada (lewat back-office atau halaman publik).

### 9.9 Laundry

- Tiket laundry: berat (kg) atau per item (jas, bed cover), layanan (reguler/express), parfum, estimasi selesai otomatis.
- Label/nota bernomor + QR untuk tracking.
- Status proses (F-10) + notifikasi WA "siap diambil".
- Bayar di depan / saat ambil (piutang pendek), deposit langganan.
- Laporan cucian belum diambil > N hari.

**Rincian laundry bagian 1 (v2.25; server, back-office, lacak publik; rincian diputuskan agen atas mandat D-12):**
- **Tiket = penjualan.** Kasir menjual layanan laundry (produk jasa, mis. "Cuci kering setrika /kg") seperti biasa; `Penjualan.Buat` membawa blok opsional `Laundry {JenisLayanan: Reguler|Express, Berat? (kg, maks 2 desimal, DECIMAL), Item? [{Nama, Jumlah}] (maks 50), Parfum?, Catatan?, EstimasiSelesaiPada?, NamaPelanggan?, NoHp?}`. Server membuat satu `TiketLaundry` di transaksi DB yang sama (idempoten per penjualan) dengan `Uuid` & `Nomor` = penjualan, sehingga perangkat bisa mencetak QR lacak saat offline. Nama & HP diambil dari pelanggan tertaut (F-16a) bila ada, selain itu dari blok (HP dinormalisasi `62…`). Estimasi selesai dari perangkat; kosong/di luar 0–60 hari dari transaksi = waktu transaksi + durasi pengaturan. Tanpa nama, atau tanpa berat maupun item = tiket tetap dibuat + tinjauan **`Laundry`**. "Bayar saat ambil" memakai metode Tempo (F-12); deposit langganan memakai deposit/paket sesi (F-16d).
- **Status (F-10):** Diterima → Dicuci → Dikeringkan → Disetrika → Siap → Diambil. Maju boleh melompat (cuci lipat tanpa setrika), tidak boleh mundur; Diambil hanya dari Siap; status sama = idempoten. Void penjualan (F-09) membatalkan tiket yang belum diambil (`Dibatalkan`). Setiap perubahan dicatat di `RiwayatStatusDokumen` + audit `laundry.status`.
- **Notifikasi:** saat Siap, bila `NotifikasiSiap` aktif dan nomor HP ada, WhatsApp "cucian {Nomor} di {toko} sudah siap diambil" + tautan lacak dikirim sekali (antrean, `NotifikasiSiapPada`); templat resmi opsional `NamaTemplatLaundrySiap` (toko, nomor, tautan). Nomor tidak ikut antrean maupun log.
- **Lacak publik:** QR label/nota menuju struk digital `/s/{kode}` (kode = awalan `Laundry.AwalanLacak` di data-awal + Uuid penjualan). Halaman menampilkan status & tahap proses (berteks + ikon), jenis layanan, berat/item, parfum, perkiraan selesai; tanpa nomor HP. Transaksi bertiket laundry tetap bisa dilacak walau struk digital dimatikan.
- **Aplikasi kasir (API, online):** `GET /api/pos/v1/laundry?kata=` (cucian aktif outlet perangkat; tanpa kata = siap diambil; cari nomor/nama/HP; maks 50) dan `POST /api/pos/v1/laundry/{uuid}/status {Status, UuidPengguna}` (izin `penjualan.buat` atau `laundry.kelola`). Layar kasir di bagian 2.
- **Back-office `/kelola/laundry`** (izin baru `laundry.kelola`: Owner, Admin, Manajer Outlet, Supervisor; dibatasi outlet akses): TabelData (cari, saring tanggal terima, status, outlet, **Perlu perhatian**: lewat perkiraan belum siap / siap lebih dari N hari = laporan cucian belum diambil), aksi ubah status. **Pengaturan** (pengguna tanpa batas outlet, audit `laundry.pengaturan`): isian laundry di kasir aktif, durasi reguler & express 1–720 jam (express ≤ reguler), daftar parfum (maks 20), notifikasi siap, batas belum diambil 1–90 hari. Data-awal POS membawa blok `Laundry {Aktif, JamReguler, JamExpress, Parfum, AwalanLacak}`.
- **Kotak Tindakan:** "Cucian lewat estimasi selesai" (Penting) dan "Cucian siap belum diambil lebih dari N hari" (Perhatian).
- **Bagian 2 (v2.26, aplikasi kasir):** bila `Laundry.Aktif`, keranjang (penjualan langsung, bukan pesanan meja/pengambilan pre-order) punya baris **Tiket laundry** → isian: layanan Reguler/Express (perkiraan selesai = sekarang + durasi, ditampilkan menurut jam outlet), berat kg (maks 2 desimal, ≤ 9.999,99), item satuan (nama + jumlah), parfum dari pengaturan, catatan, serta nama (wajib) & WhatsApp pemilik bila belum memilih pelanggan. Wajib berat atau item. Panel Bayar menampilkan ringkasan tiket; "Jadikan pre-order" disembunyikan. Tiket tersimpan di penjualan lokal (Drift skema 15, kolom `Penjualan.Laundry`; migrasi aditif, outbox utuh) sehingga struk cetak & cetak ulang memuat blok **TIKET LAUNDRY** (layanan, berat, item, parfum, catatan, selesai) dan **QR lacak** `/s/{kode}` (walau struk digital mati). **Riwayat › Cucian** (online): bawaan cucian siap diambil, cari nomor/nama/HP, tombol tahap berikutnya / **Siap diambil** / **Sudah diambil** (izin `penjualan.buat` atau `laundry.kelola`), dan **Cetak nota** (label ber-QR untuk kantong cucian).

### 9.10 Bengkel

- Data kendaraan (plat, merk, tipe, tahun, km) terhubung ke pelanggan.
- Work Order: keluhan → diagnosis → estimasi (jasa + part) → persetujuan pelanggan (via WA link) → pengerjaan → QC → invoice.
- Mekanik ditugaskan per jasa (komisi).
- Riwayat servis per kendaraan, pengingat servis berkala (km/waktu).

### 9.11 Bakery / Produksi Harian

- Rencana produksi harian (berdasarkan forecast/pre-order).
- Order produksi (F-05e) → stok produk jadi.
- Pre-order kue ulang tahun: DP, spesifikasi kustom, tanggal ambil, status.
- Produk expired hari yang sama → diskon sore otomatis (promo terjadwal) → sisa dicatat waste.

### 9.12 Bahan Bangunan

- Qty desimal & konversi (batang ↔ meter, sak, m³).
- Harga sering berubah: update harga massal (% atau nominal per kategori).
- Pengiriman dengan armada, ongkir per jarak/zona.
- Penjualan tempo ke kontraktor dengan limit & aging.
