<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 6. Prinsip Pengembangan: Flow-First

### 6.1 Definisi

**Flow-First Development** berarti setiap unit pekerjaan dimulai dari **flow bisnis** yang terdefinisi (aktor, pemicu, langkah, aturan, hasil, dan dampak akuntansi/stok), baru kemudian diturunkan menjadi:

```
Flow Bisnis (F-xx)
   └─ State Machine dokumen (Draf → Diposting → ...)
        └─ Domain Events (SaleCompleted, GoodsReceived, ...)
             └─ Efek samping: Stok (ledger) + Jurnal (akuntansi) + Notifikasi
                  └─ Skema DB + Action class + Test
                       └─ UI (Inertia page + komponen)
```

### 6.2 Aturan Tim

1. **Tidak ada fitur tanpa ID Flow.** Setiap issue/PR mencantumkan `P-xx.langkah` (Platform Pengelola) atau `F-xx.langkah` (Tenant).
2. **Urutan implementasi mengikuti urutan flow induk** (§7). Sebuah flow hanya boleh dikerjakan setelah flow prasyaratnya berstatus *Done*. Contoh: Penjualan (F-07) butuh Master Produk (F-03) dan Shift (F-06). **Flow tenant F-00 baru boleh dikerjakan setelah flow persiapan Platform Pengelola P-01 s.d. P-06 selesai.**
3. **Setiap flow wajib memiliki**: diagram alur, state machine dokumen, daftar aturan bisnis (BR-xx), dampak stok, dampak jurnal, hak akses, acceptance criteria (Gherkin), dan test otomatis.
4. **Dokumen transaksi bersifat immutable setelah *posted*.** Koreksi dilakukan dengan dokumen pembalik (void/retur/adjustment), bukan edit/hapus.
5. **Stok dan akuntansi adalah turunan (derived) dari event.** Tidak ada input manual ke tabel saldo stok atau saldo akun.
6. **Definition of Done per flow**: happy path + edge case lulus test, jurnal seimbang (debit = kredit), ledger stok konsisten, audit log tercatat, UI bisa dipakai di tablet 10".

### 6.3 Template Spesifikasi Flow

Setiap flow di §8 memakai struktur berikut:

- **Tujuan**, **Aktor**, **Pemicu**, **Prasyarat**
- **Langkah** (bernomor)
- **Aturan Bisnis** (BR)
- **State Machine**
- **Dampak Stok** / **Dampak Jurnal**
- **Acceptance Criteria**
