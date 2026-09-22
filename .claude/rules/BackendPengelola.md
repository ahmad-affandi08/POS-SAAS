---
paths:
  - "Backend/app/Domain/Pengelola/**"
  - "Backend/app/Http/Kontroler/Pengelola/**"
  - "Backend/routes/Pengelola.php"
  - "Backend/resources/js/Halaman/Pengelola/**"
---

# Aturan Platform Pengelola (PRD §8 Bagian A, §13.8, §19.3)

- Akun `PenggunaPengelola` dan guard `pengelola` terpisah dari tenant. Subdomain `pengelola.`. 2FA wajib.
- Akses lintas tenant hanya lewat `KonteksPengelola::JalankanLintasTenant(alasan, fn)`, yang mencatat `LogAuditPengelola`.
- Setiap aksi pengelola tercatat di `LogAuditPengelola` (pelaku, aksi, objek, tenant, nilai lama/baru, alasan, IP).
- Akses dukungan ke data tenant hanya dengan izin `AksesDukungan` yang aktif (cakupan BacaSaja/BacaUbah, berbatas waktu). Akses darurat hanya Super Admin + notifikasi Owner.
- Data master bertanggal (tarif pajak, template sektor) tidak pernah diedit setelah terbit. Perubahan = versi/baris baru + alur tinjauan dua orang bila disyaratkan.
- Pengelola tidak pernah melihat kata sandi, PIN, atau kredensial integrasi tenant. Data pribadi pelanggan tersamar secara default.
- Hak per peran internal mengikuti tabel §19.3 (kolom "Tidak boleh").
