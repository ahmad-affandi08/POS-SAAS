---
paths:
  - "Backend/app/**/*.php"
---

# Aturan kode domain Backend (PRD §13.2, §13.3, §13.4)

- Struktur: `Backend/app/Domain/{Domain}/{Aksi,Data,Enum,Peristiwa,Penangan,Model,Kebijakan,Kueri,Status}/`.
- **Satu use case = satu class Aksi** di folder `Aksi/`, nama kalimat kerja (`SelesaikanPenjualan`), method publik tunggal `Jalankan(...)`. Kontroler hanya memvalidasi (Permintaan) lalu memanggil Aksi.
- Aksi yang mengubah data membungkus pekerjaannya dalam `DB::transaction`. Idempotensi: cek `UuidKlien` dulu.
- Model mewarisi `ModelDasar` (PK `Id`, `DibuatPada`/`DiubahPada`/`DihapusPada`, FK `Id{Model}`). Relasi `belongsTo` **selalu** menyebut kolom eksplisit.
- Model data tenant memakai trait `MilikTenant`. Jangan memakai `withoutGlobalScope(s)` di luar `Domain/Pengelola`.
- Antar domain hanya lewat Aksi/Layanan publik atau Peristiwa. Jangan query tabel milik domain lain, jangan import Model domain lain di Kueri.
- Perubahan status dokumen hanya lewat enum status dengan `BisaBerubahKe()`, dicatat di `RiwayatStatusDokumen`.
- Uang: `Uang`, jumlah: `Kuantitas` (brick/math). Dilarang `float`, `floatval`, `round()` untuk uang.
- Tarif pajak dari `TarifPajak` (bertanggal berlaku) lewat `KalkulatorPajak`. Dilarang angka tarif di kode.
- Penangan stok & jurnal: sinkron, di transaksi yang sama. Penangan lain: `ShouldQueue` + `afterCommit`, membawa `IdTenant`.
- Nama class per jenis: `{Objek}{Jenis}` → `PenjualanKontroler`, `PenjualanKebijakan`, `SimpanProdukPermintaan`, `ProdukRespons`, `KirimStrukWaTugas`. Peristiwa: `PenjualanSelesai`. Penangan: `KurangiStokPenjualan`.
- Setiap aksi penting mencatat `LogAudit` (siapa, apa, nilai lama/baru, perangkat).
