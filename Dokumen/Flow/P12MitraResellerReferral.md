<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-12 · Mitra, Reseller & Referral

**Tujuan:** Mempercepat akuisisi tenant lewat mitra daerah dan rujukan, dengan komisi yang transparan.
**Aktor:** Mitra & Penjualan, Keuangan, Mitra (eksternal).

**Jenis mitra:**

| Jenis | Peran | Imbalan |
|---|---|---|
| Reseller/Agen daerah | Menjual, onboarding, dukungan tingkat pertama | Komisi berulang (% dari tagihan lunas) |
| Referral | Tenant atau individu yang merekomendasikan | Kredit langganan / komisi sekali |
| Mitra hardware | Distributor perangkat POS & printer | Bundel, masuk daftar perangkat kompatibel (HCL) |
| Mitra implementasi | Migrasi data & pelatihan berbayar | Tarif jasa |

**Langkah:**
1. Pendaftaran mitra → verifikasi identitas (KTP/NPWP, rekening) → persetujuan kontrak (P-06).
2. Mitra mendapat **kode mitra** dan tautan pendaftaran.
3. **Atribusi:** tenant yang mendaftar dengan kode/tautan mitra tercatat di `AtribusiMitra` (berlaku 90 hari sejak klik pertama).
4. Komisi dihitung otomatis dari **tagihan langganan yang lunas** (P-08).
5. Pencairan bulanan oleh Keuangan, dengan pemotongan pajak sesuai ketentuan (dikonsultasikan dengan konsultan pajak).
6. **Portal mitra** (`/mitra`, fase 3): daftar tenant rujukan, status, komisi, materi pemasaran.

**Aturan Bisnis:**
- BR-P12.1 Komisi hanya dari tagihan lunas. Jika tagihan di-refund, komisi terkait dibatalkan (*clawback*).
- BR-P12.2 Satu tenant hanya diatribusikan ke satu mitra.
- BR-P12.3 Mitra tidak otomatis punya akses ke data tenant. Akses mengikuti mekanisme izin P-09.
