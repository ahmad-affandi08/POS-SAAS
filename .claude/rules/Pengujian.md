---
paths:
  - "Aplikasi/Web/tests/**"
  - "Aplikasi/*/test/**"
  - "Aplikasi/*/integration_test/**"
  - "Paket/*/test/**"
  - "Spesifikasi/**"
---

# Aturan pengujian (PRD §23)

- Test menguji perilaku dari flow (BR-xx di nama/deskripsi test). Setiap BR yang diimplementasikan punya test.
- Backend: Pest dengan **MySQL**, bukan SQLite (perilaku lock & tipe harus sama dengan produksi).
- Wajib ada untuk flow terkait: test isolasi tenant, test idempotensi (`UuidKlien` dikirim dua kali = satu transaksi), invariant test keuangan/stok.
- Test arsitektur (`Aplikasi/Web/tests/Arsitektur/`) dan test vector (`Spesifikasi/VektorUjiKalkulasi/`) **terlindungi**: hanya manusia yang mengubah. Kalau test tersebut gagal, perbaiki kodenya.
- **Dilarang**: `->skip()`, `markTestSkipped`, `skip: true`, menghapus assertion, melonggarkan toleransi, atau mengubah nilai harapan supaya test lolos tanpa alasan bisnis yang disetujui.
- Data uji realistis Indonesia (nama produk panjang, Rupiah jutaan, stok minus, offline).
