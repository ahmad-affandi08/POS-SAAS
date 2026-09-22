---
paths:
  - "Backend/database/**/*.php"
---

# Aturan migrasi & database (PRD §15, §13.7)

- Nama file: `YYYY_MM_DD_HHMMSS_{KataKerjaPascalCase}.php`, misal `..._BuatTabelPenjualan.php`, `..._TambahKolomCatatanKePenjualan.php`.
- Tabel & kolom PascalCase Bahasa Indonesia, tabel bentuk tunggal. Detail pola `{Induk}Detail`.
- `$tabel->id('Id')`, `$tabel->char('Uuid', 26)`, `$tabel->foreignId('IdTenant')->constrained('Tenant', 'Id')`, `$tabel->WaktuStandar()`. **Jangan** `id()`, `timestamps()`, `softDeletes()` tanpa nama.
- Semua tabel tenant: kolom `IdTenant` + indeks komposit **diawali `IdTenant`**.
- Tipe: uang `decimal(18,2)`, HPP per unit `decimal(19,6)`, jumlah `decimal(18,4)`, tarif `decimal(9,6)`. **Dilarang** `float`/`double`.
- Nama indeks/constraint eksplisit: `Idx{Tabel}...`, `Uniq{Tabel}...`, `Fk{Tabel}{Kolom}` (maks 64 karakter).
- Migrasi yang sudah ada di branch `main` **tidak boleh diubah** (hook akan memblokir). Buat migrasi baru.
- Perubahan skema zero-downtime: *expand → contract* (kolom baru nullable dulu, hapus kolom lama di rilis berikutnya).
- Tabel dokumen transaksi tanpa soft delete. Tabel log audit append-only.
- MySQL produksi case-sensitive untuk nama tabel: tulis nama persis. Dev wajib MySQL Linux (Docker/WSL2).
