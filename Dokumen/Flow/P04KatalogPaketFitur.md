<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-04 · Katalog Paket & Fitur

**Tujuan:** Paket langganan, batas pemakaian, dan fitur ditentukan lewat data, bukan hard-code, sehingga harga dan isi paket bisa diubah tanpa rilis kode.
**Aktor:** Super Admin, Keuangan.

**Komponen:**
- **Katalog fitur**: setiap fitur punya kunci unik (misal `pos.mode-meja`, `promo.mesin`, `api.publik`, `persetujuan.jarak-jauh`), nama, modul, keterangan.
- **Paket**: kode, nama, harga bulanan & tahunan, masa trial, fitur yang termasuk, dan **batas**: jumlah outlet, perangkat per outlet, pengguna, SKU, kuota pesan WA, penyimpanan file.
- **Add-on**: fitur atau batas tambahan yang bisa dibeli terpisah (outlet tambahan, self-order QR, WA, insight).
- **Kupon langganan**: kode, diskon (% atau nominal), durasi (bulan), kuota, paket yang berlaku, masa berlaku.

**Evaluasi fitur untuk tenant:**
```
FiturAktif(tenant, kunci) =
    (fitur ada di paket tenant  ATAU  add-on aktif  ATAU  override pengelola aktif)
    DAN flag fitur global mengizinkan (P-10)
    DAN template/outlet mengaktifkan modul tersebut
```

**Aturan Bisnis:**
- BR-P04.1 Perubahan harga paket hanya berlaku untuk **tagihan berikutnya**. Tenant lama dapat dikunci pada harga lama (*grandfathering*) sesuai pilihan saat perubahan.
- BR-P04.2 Paket yang diarsipkan tidak bisa dipilih tenant baru, tetapi tenant yang sudah memakainya tidak terdampak.
- BR-P04.3 Batas ditegakkan di backend (perantara `PastikanBatasPaket`) dan dikirim ke aplikasi lewat `konfigurasi-aplikasi`. Penegakan di aplikasi hanya untuk UX, server tetap penentu.
- BR-P04.4 Saat downgrade melebihi batas (misal 5 outlet ke paket 1 outlet), **data tidak dihapus**. Tenant diminta memilih outlet/perangkat yang tetap aktif, sisanya menjadi hanya-baca.
