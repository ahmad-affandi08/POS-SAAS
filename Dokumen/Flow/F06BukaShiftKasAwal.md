<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-06 · Buka Shift & Kas Awal

**Tujuan:** Setiap uang di laci kas bisa dipertanggungjawabkan per kasir per shift.

**Langkah:**
1. Kasir login dengan PIN di aplikasi POS pada perangkat terdaftar. PIN diverifikasi lokal (hash PIN tersinkron ke perangkat, lihat §18) sehingga login tetap bisa saat offline.
2. Jika belum ada shift terbuka untuk perangkat tersebut → layar "Buka Shift": input **modal awal (kas awal)**, opsional hitung per pecahan.
3. Shift aktif. Semua transaksi menempel ke `IdShift`.
4. Selama shift: **Kas Masuk/Keluar** non-penjualan (beli es batu, bayar parkir, setor ke owner) dengan kategori & foto bukti.

**Aturan Bisnis:**
- BR-06.1 Satu perangkat hanya punya satu shift terbuka. Satu kasir boleh punya satu shift terbuka per outlet.
- BR-06.2 Mode opsional **"shift bersama"**: beberapa kasir berbagi satu laci (umum di kafe kecil). Transaksi tetap mencatat user kasir.
- BR-06.3 Shift bisa dibuka offline (lihat §18).
- BR-06.4 Kas keluar di atas batas butuh PIN supervisor.

**Rincian F-06a (v1.34, diputuskan agen atas mandat D-12):**
- Shift & mutasi kas dibuat di perangkat (ULID, bisa offline) dan dikirim lewat `POST /api/pos/v1/sinkron/kirim`: batch maks. 50 item berurutan `{Jenis, Uuid, Data}` (F-06: `Shift.Buka`, `MutasiKas.Catat`). Hasil per item dengan urutan yang sama: `Diterima`, `Duplikat` (Uuid sudah diterima, aman dihapus dari outbox), atau `Ditolak` + `Galat {Kode, Pesan, Bidang, Detail}` (masuk daftar "Perlu Tindakan"). Setiap item di transaksinya sendiri; galat server = seluruh batch dikirim ulang dan item yang sudah diterima kembali sebagai `Duplikat`. Endpoint ini tetap menerima data saat langganan ditangguhkan agar data offline tidak hilang.
- Membuka shift & mencatat kas memakai izin `penjualan.buat` ("berjualan & shift sendiri"). BR-06.1 per perangkat ditegakkan server (`ShiftSudahTerbuka`); per kasir lintas perangkat bisa terjadi saat offline, jadi shift tetap diterima tetapi ditandai `PerluTinjauan` dengan alasannya. Shift bersama (BR-06.2) adalah pengaturan tenant; di shift yang bukan bersama hanya pembuka shift atau supervisor (`kas.keluar.setujui`) yang boleh mencatat kas.
- Kas awal ≥ 0; hitungan pecahan opsional wajib berjumlah sama dengan kas awal. Kas awal tidak dijurnal (uang hanya berpindah di dalam kas usaha).
- Kas masuk/keluar wajib memilih `KategoriKas` aktif yang dipetakan ke satu akun (back-office, izin `akuntansi.kelola`; kategori tidak dihapus, cukup dinonaktifkan). Setoran tanpa kategori.
- BR-06.4: batas bawaan Rp 200.000 (§19.2), diatur per tenant di Pengaturan kasir (izin `outlet.kelola`, 0 = selalu butuh persetujuan). Kas keluar di atas batas wajib `UuidPenyetuju`; PIN diperiksa di perangkat, server memeriksa penyetuju punya izin `kas.keluar.setujui` di outlet itu.
- Jurnal diposting sinkron saat mutasi diterima (aturan #10): kas keluar J-06.1, kas masuk (Dr Kas Outlet / Cr akun kategori), setoran J-11.3 ke Kas Brankas. Mutasi di periode terkunci ditolak `PeriodeTerkunci`. Mutasi kas append-only; data pembukaan shift tidak bisa diubah.
- Back-office: daftar & detail shift (izin `laporan.penjualan.lihat`, dibatasi outlet akses) dengan ringkasan kas non-penjualan, dan tautan dari jurnal ke shift sumbernya.

**State Machine `Shift.Status`:** `Terbuka → Menutup (hitung kas) → Tertutup → (DibukaUlang oleh supervisor, dengan alasan)`.
