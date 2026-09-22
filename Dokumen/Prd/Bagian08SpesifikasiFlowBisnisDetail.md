<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 8. Spesifikasi Flow Bisnis Detail

> Konvensi: **BR** = Business Rule, **AC** = Acceptance Criteria, **Dr/Cr** = Debit/Kredit.
> Semua nominal dalam Rupiah (IDR). Semua waktu disimpan dalam UTC dan ditampilkan sesuai zona waktu outlet (WIB/WITA/WIT).

### Bagian A — Flow Platform Pengelola (P-01 s.d. P-12)

> **Platform Pengelola** adalah lapisan yang dipakai **tim internal {{APP}}** (bukan tenant) untuk mengoperasikan bisnis SaaS: menyiapkan data master, paket, template, regulasi, mengelola tenant, tagihan, dukungan, rilis aplikasi, dan mitra. Flow P-01 s.d. P-06 **wajib selesai sebelum** flow tenant F-00 bisa berjalan. Flow P-07 s.d. P-12 berjalan paralel selama platform beroperasi.
>
> Diakses melalui `https://pengelola.{{app}}.id` (atau `/pengelola`), dengan akun, autentikasi, dan layout terpisah dari back-office tenant (§13.8).

```mermaid
flowchart LR
    subgraph Persiapan["Persiapan (sebelum tenant pertama)"]
      P1[P-01 Tim Internal & Peran] --> P2[P-02 Master Regulasi & Referensi]
      P2 --> P3[P-03 Template Sektor]
      P1 --> P4[P-04 Katalog Paket & Fitur]
      P1 --> P5[P-05 Konfigurasi Integrasi]
      P1 --> P6[P-06 Legal & Template Komunikasi]
    end
    P3 & P4 & P5 & P6 --> F0[F-00 Registrasi Tenant]
    subgraph Operasi["Operasi (paralel, terus-menerus)"]
      P7[P-07 Siklus Hidup Tenant]
      P8[P-08 Billing & Dunning]
      P9[P-09 Dukungan & Akses Dukungan]
      P10[P-10 Rilis Aplikasi & Flag Fitur]
      P11[P-11 Monitoring Operasional]
      P12[P-12 Mitra, Reseller & Referral]
    end
    F0 --> P7
    P7 <--> P8
    P7 <--> P9
```

---


Detail setiap flow ada di folder `Dokumen/Flow/` (lihat Indeks.md).
