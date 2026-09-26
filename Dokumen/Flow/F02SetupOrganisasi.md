<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-02 · Setup Organisasi

**Tujuan:** Struktur usaha tergambar di sistem: Tenant → Brand (opsional) → Outlet → Gudang/Lokasi → Perangkat → User.

**Entitas & hubungan:**
```
Tenant 1─* Brand 1─* Outlet 1─* Warehouse(Location)
                         1─* Device (kasir/KDS/self-order kiosk)
                         1─* Table Area 1─* Table   (F&B)
Tenant 1─* User *─* Outlet (penugasan) + Role per outlet
```

**Langkah:**
1. Tambah outlet (nama, kode 3–5 huruf untuk penomoran dokumen, alamat, zona waktu, template sektor, jam operasional).
2. Tambah gudang/lokasi stok per outlet (default: 1 lokasi "Toko"). Bisa tambah "Gudang Belakang", "Dapur", "Bar".
3. **Tambah pengguna langsung** dengan peran & outlet yang ditugaskan (D-22): karyawan kasir cukup nama + PIN tanpa email (hanya masuk aplikasi kasir); pengguna dengan email diberi kata sandi awal yang wajib diganti saat pertama masuk. Email yang sudah punya akun PAYOU tetap lewat undangan email (persetujuan pemilik akun). Undangan via email tetap tersedia sebagai pilihan.
4. Kasir mendapat **PIN 6 digit** untuk login cepat di perangkat kasir bersama.
5. **Aktivasi perangkat**: di back-office, admin membuat perangkat (tipe: Kasir / KDS / Gudang / Pelayan) dan mendapat **kode aktivasi 8 karakter + QR** (berlaku 15 menit). Di aplikasi Flutter, pengguna memindai QR atau mengetik kode. Server mengembalikan **device token** (disimpan di secure storage) dan kode perangkat `Perangkat.Kode` (misal `JKT1-K02`) untuk penomoran offline. Satu instalasi aplikasi = satu perangkat terdaftar.

**Aturan Bisnis:**
- BR-02.1 Jumlah outlet, perangkat, dan user dibatasi paket langganan.
- BR-02.2 Kode outlet unik per tenant dan **tidak bisa diubah** setelah ada transaksi.
- BR-02.3 Perangkat yang dicabut (revoke) langsung ditolak saat sinkron, tetapi transaksi offline yang sudah dibuat sebelum revoke **tetap diterima** (dengan flag review).
- BR-02.4 Setiap outlet wajib punya minimal 1 lokasi stok dan 1 akun kas (Kas Outlet).

**Rincian F-02a (organisasi, pengguna & peran, tanpa perangkat/PIN; diputuskan agen atas mandat pemilik produk):**
- Outlet: kode 3–5 karakter diawali huruf (huruf/angka, disimpan huruf besar, misal `UTAMA`, `JKT1`), unik per tenant termasuk outlet arsip. Kota dari data `Wilayah` P-02; zona waktu (WIB/WITA/WIT) mengikuti kota, dipilih manual bila kota kosong. Jam tutup buku `JJ:MM` (bawaan 04:00). Profil pajak dasar disimpan untuk F-03: PKP, NITKU outlet (22 angka, opsional), memungut PBJT makanan & minuman. Jam operasional & template sektor per outlet diisi F-01.
- BR-02.2 ditegakkan lewat `Outlet.KodeDikunciPada`: diisi oleh flow yang pertama kali membuat transaksi/perangkat outlet (F-02b/F-06/F-07); setelah terisi kode tidak bisa diubah.
- BR-02.4 (bagian lokasi stok): outlet baru otomatis mendapat lokasi stok jenis Toko; lokasi stok jual terakhir (bukan Rusak/Dalam perjalanan) di outlet aktif tidak bisa diarsipkan atau diubah jenisnya. Akun Kas Outlet dibangun bersama COA tenant (F-01/F-13).
- Outlet & lokasi stok tidak pernah dihapus, hanya **diarsipkan** (status Aktif/Diarsipkan) dan bisa dipulihkan; minimal satu outlet aktif. Merek hanya bisa dihapus bila belum pernah dipakai outlet; minimal satu merek.
- BR-02.1 / BR-P04.3: layanan `PastikanBatasPaket` (batas efektif `EvaluatorFitur`, baris `Langganan` dikunci agar penambahan bersamaan tidak lolos) dipanggil saat menambah/memulihkan outlet (`BatasOutlet`, outlet arsip tidak dihitung) dan saat mengundang, menerima undangan, atau mengaktifkan kembali pengguna (`BatasPengguna` = anggota aktif + undangan yang masih berlaku). Pesan penolakan menyebut batas paket dan mengarahkan ke menu Langganan. `BatasPerangkatPerOutlet` di F-02b.
- Undangan pengguna lewat email: berlaku 72 jam, sekali pakai, token hanya disimpan sebagai hash; undangan baru untuk email yang sama membatalkan yang lama. Penerima tanpa akun membuat akun (email dianggap terverifikasi); email yang sudah punya akun (termasuk anggota tenant lain) wajib masuk dulu lalu akunnya ditautkan (BR-00.1). Email anggota aktif/nonaktif tenant yang sama ditolak (nonaktif → aktifkan kembali).
- Peran & izin tenant: peran bawaan §19.1 dibuat untuk setiap tenant saat pendaftaran dan diselaraskan perintah `organisasi:siapkan-peran` (idempoten, juga untuk tenant lama). Peran bawaan tidak bisa diubah; peran kustom dibuat dari izin granular. Pemilik selalu memegang semua izin & semua outlet. Anti-eskalasi: pelaku bukan Pemilik hanya bisa memberi izin/peran yang izinnya ia miliki dan outlet yang ia akses; hanya Pemilik yang menunjuk/mengubah Pemilik; izin `langganan.kelola` khusus Pemilik. Akses outlet: semua outlet atau daftar outlet (`OutletPengguna`).
- Anggota dinonaktifkan, tidak dihapus: sesinya langsung terputus, undangan yang ia kirim dibatalkan. Tidak bisa mengubah akses/status akun sendiri; Pemilik aktif terakhir tidak bisa diturunkan atau dinonaktifkan.
- Semua aksi F-02 serta pendaftaran, masuk, pilih tenant, keluar, dan akhir trial dicatat di `LogAudit` (append-only); halaman log audit untuk pemegang izin `audit.lihat`.
- Ditunda ke F-02b: perangkat, kode aktivasi, PIN kasir, BR-02.3, `BatasPerangkatPerOutlet`.

**Rincian F-02b (perangkat, kode aktivasi, PIN kasir; diputuskan agen atas mandat pemilik produk):**
- Perangkat dikelola di `/kelola/perangkat` (izin `perangkat.lihat` / `perangkat.kelola`; peran bawaan Pemilik, Admin, Manajer Outlet; Manajer hanya outlet yang ditugaskan). Jenis: Kasir, Kds, Gudang, Pelayan (Salesman menyusul). Perangkat tidak pernah dihapus: status Belum diaktifkan → Aktif → Dicabut (final).
- Kode perangkat `{KodeOutlet}-{Huruf}{NN}` (K = Kasir, D = KDS, G = Gudang, P = Pelayan; misal `JKT1-K02`), nomor urut per outlet & jenis, unik per tenant dan **tidak pernah dipakai ulang**, juga setelah dicabut. Perangkat pertama di outlet mengisi `Outlet.KodeDikunciPada` (BR-02.2).
- BR-02.1: `BatasPerangkatPerOutlet` ditegakkan `PastikanBatasPaket` saat menambah perangkat; yang dihitung perangkat yang belum dicabut (termasuk yang belum diaktifkan). Perangkat hanya bisa ditambahkan di outlet aktif.
- Kode aktivasi: 8 karakter dari 32 karakter tanpa 0/O/1/I, berlaku 15 menit, sekali pakai, disimpan sebagai HMAC-SHA256 (kunci aplikasi); ditampilkan sekali bersama QR (isi QR = kode). Membuat kode baru membatalkan kode lama perangkat itu; mencabut perangkat membatalkan kode yang belum dipakai. Kode untuk perangkat yang sudah aktif = pindah/instal ulang: aktivasi berikutnya mengganti token sehingga instalasi lama keluar. Penukaran dibatasi 10 kali/menit per IP; kode salah, kedaluwarsa, dipakai, atau dibatalkan dijawab galat yang sama (`KodeAktivasiTidakBerlaku`). `KodeAktivasi` adalah data platform tanpa `MilikTenant` (seperti `UndanganPengguna`) karena tenant belum diketahui saat kode ditukar.
- Device token: `{IdTenant}|{rahasia}` dengan rahasia 64 byte acak; server hanya menyimpan SHA-256 rahasia di `Perangkat.HashToken`. Bagian `IdTenant` hanya menetapkan scope pencarian (token tenant A dengan IdTenant diganti tidak cocok di tenant lain). Dipilih alih-alih Sanctum karena pencarian tokenable Sanctum memuat `Perangkat` (MilikTenant) sebelum tenant diketahui dan akan memerlukan melewati scope tenant di luar `Domain/Pengelola`. Perantara `AutentikasiPerangkat` menetapkan tenant & perangkat, memperbarui `TerakhirAktifPada` (maks. sekali per menit) dan `VersiAplikasi` dari header `X-Versi-Aplikasi`.
- BR-02.3: token perangkat yang dicabut langsung ditolak (`PerangkatDicabut`, 403). Penerimaan batch offline yang dibuat sebelum pencabutan dibangun bersama sinkron (F-07).
- Langganan `Ditangguhkan`/`Berhenti`: aktivasi perangkat dan endpoint berjualan (mulai `kasir/masuk-pin`) ditolak `LanggananTidakAktif` (403); `konfigurasi-aplikasi` tetap terbuka dengan `Langganan.BolehBertransaksi = false`. Tenant yang turun ke paket Gratis boleh berjualan lagi.
- PIN kasir: 6 angka per keanggotaan tenant (`TenantPengguna.HashPin`, `Hash::make`), ditolak bila angka sama semua atau deret naik/turun (misal 123456, 654321). Setiap anggota mengatur PIN sendiri di `/kelola/keamanan/pin`; pemegang izin `pengguna.pin.atur` (Pemilik, Admin, Manajer Outlet) mengatur ulang PIN anggota lain tanpa bisa melihatnya (bukan PIN Pemilik bila pelaku bukan Pemilik; pelaku berakses outlet terbatas hanya untuk anggota yang semua outletnya ada di outletnya). Verifikasi online `POST /api/pos/v1/kasir/masuk-pin`: hanya anggota aktif dengan akses ke outlet perangkat; 5 kali salah per perangkat + pengguna → terkunci 5 menit (`PinTerkunci`, 429). Distribusi hash PIN ke perangkat untuk verifikasi offline menyusul F-06.
- Log audit: `perangkat.buat`, `perangkat.ubah`, `perangkat.kode-aktivasi.buat`, `perangkat.aktivasi`, `perangkat.cabut`, `outlet.kunci-kode`, `pengguna.pin.atur`, `pengguna.pin.atur-ulang`, `kasir.masuk-pin`, `kasir.pin.terkunci` (tanpa kode, token, atau PIN).
- Versi aplikasi POS per platform (`VersiTerbaru`, `VersiMinimal`, `TautanUnduh`) dari `RilisAplikasi` per perangkat sejak P-10 (v1.82); `config/aplikasi.php` tetap menjadi cadangan selama belum ada rilis.
