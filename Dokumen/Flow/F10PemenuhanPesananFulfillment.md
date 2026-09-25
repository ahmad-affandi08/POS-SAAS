<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-10 · Pemenuhan Pesanan (Fulfillment)

- **F&B Dine-in/Takeaway:** order dikirim ke **KDS** atau **printer dapur** per *station* (Dapur, Bar, Pastry) berdasarkan kategori produk. Status item: `Antre → Dimasak → Siap → Disajikan`. Tampilkan timer & warna (hijau < 10 menit, kuning, merah).
- **Nomor antrian / pager:** layar *Customer Display* menampilkan nomor siap.
- **Pengiriman (retail/grosir):** status `SiapKemas → Dikemas → Dikirim → Diterima`, surat jalan, kurir internal/pihak ketiga, bukti foto.
- **Pre-order & Pesanan kustom (bakery, percetakan):** DP, tanggal ambil, status produksi, pelunasan saat ambil.
- **Laundry:** status `Diterima → Dicuci → Dikeringkan → Disetrika → Siap → Diambil`, notifikasi WA saat `Siap`.
- **Bengkel:** Perintah Kerja `Masuk → Diagnosis → MenungguPersetujuan → Dikerjakan → Qc → Selesai → Diambil`.

**Rincian F-10a (v1.56, data master mode meja & dapur; diputuskan agen atas mandat D-12):**
- **Area & meja per outlet** (domain Organisasi, dikelola di halaman detail outlet `/kelola/outlet/{outlet}`, izin `outlet.kelola`): `AreaMeja` (Nama unik per outlet, Urutan) dan `Meja` (Nama/nomor unik per outlet maks. 30 karakter, area opsional yang harus aktif di outlet yang sama, Kapasitas 1–99, Bentuk `Persegi|Bundar|Panjang`, Urutan; `PosisiX/PosisiY` disiapkan untuk editor denah). Status `Aktif|Diarsipkan` (tidak pernah dihapus karena dirujuk pesanan). Area yang masih punya meja aktif tidak bisa diarsipkan; meja hanya bisa dipulihkan bila areanya aktif. **Status pakai meja** (kosong/terisi/minta bill/perlu dibersihkan) bukan kolom, melainkan diturunkan dari pesanan terbuka di F-07 mode meja. `TokenQr` dibuat F-17.
- **Gerbang fitur:** menambah area/meja butuh fitur paket `pos.mode-meja` aktif di outlet itu (paket/add-on/override ∩ modul template outlet, BR-P04.7, BR-01.3). Setelah turun paket, data lama tetap tampil dan bisa diubah/diarsipkan (tidak ada data yang terkunci), tetapi meja baru ditolak `FiturTidakAktif`. Bagian meja di halaman outlet tampil bila fitur aktif atau sudah ada data.
- **Stasiun dapur tingkat tenant** (penyesuaian §15: tanpa `IdOutlet` dan tanpa `KonfigurasiPrinter`): karena kategori produk berlaku di seluruh tenant, `Kategori.IdStasiunDapur` merujuk stasiun tenant ("Dapur", "Bar", "Pastry"; nama unik per tenant, maks. 20 aktif). Setiap perangkat KDS/printer dapur di outlet memilih stasiun yang dilayaninya dan menyimpan konfigurasi printernya di profil perangkat (F-10b). Halaman `/kelola/stasiun-dapur` (lihat `produk.lihat`, ubah `produk.kelola`); stasiun kategori dipilih di form kategori. Kategori tanpa stasiun, atau yang stasiunnya diarsipkan, dirutekan ke **stasiun bawaan** = stasiun aktif dengan urutan terkecil. Form kategori yang tidak mengirim bidang stasiun (klien lama) tidak mengubah rujukannya.
- **Template sektor:** daftar `StasiunDapur` template (misal FNB-CAF: Bar, Dapur) dibuat saat template diterapkan, aditif & idempoten (BR-01.1; nama yang sudah ada, termasuk yang diarsipkan, dilewati).
- Audit: `area-meja.*`, `meja.*`, `stasiun-dapur.*` (buat, ubah, arsipkan, pulihkan, tambah-template), perubahan stasiun kategori ikut `kategori.ubah`.
- **Belum (F-10b/F-07 mode meja):** editor denah seret-lepas, status pakai meja, paket data meja & stasiun untuk aplikasi kasir, pesanan terbuka tersinkron, tiket dapur (`TiketDapur`), layar KDS, dan printer dapur per stasiun.
