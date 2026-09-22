<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### P-10 · Rilis Aplikasi & Flag Fitur

**Tujuan:** Versi baru Aplikasi POS dan Aplikasi Owner sampai ke pengguna dengan aman, bertahap, dan bisa dihentikan bila bermasalah.
**Aktor:** Teknis, Super Admin.

**Langkah rilis:**
1. CI mengunggah build dan membuat catatan `RilisAplikasi` (status `Draf`).
2. Uji internal di kanal `Beta` (perangkat lab & tenant uji).
3. **Rollout bertahap** 10% → 50% → 100% (Play Store staged rollout. Windows lewat `RilisAplikasi.PersenRollout`).
4. Pantau crash-free sessions, galat sinkron, dan tiket terkait versi.
5. Bila bermasalah: **hentikan rollout** dan matikan fitur bermasalah lewat flag (*kill switch*) tanpa menunggu review store.
6. Naikkan `VersiMinimum` bila diperlukan (§14.6).

**Flag fitur:** cakupan global, per paket, per tenant, atau persentase tenant. Dipakai untuk peluncuran bertahap fitur baru dan kill switch.

**Pengumuman & pemeliharaan:** banner in-app per segmen (paket, sektor, platform, versi), jadwal pemeliharaan, catatan rilis ("Yang baru").

**Aturan Bisnis:**
- BR-P10.1 Menaikkan `VersiMinimum` wajib diumumkan ≥ 7 hari sebelumnya, kecuali perbaikan keamanan.
- BR-P10.2 Sebelum menaikkan `VersiMinimum`, sistem menampilkan jumlah perangkat di versi lama yang masih punya outbox tertunda. Perangkat tersebut tetap diizinkan mengirim outbox (§14.6).
- BR-P10.3 Setiap perubahan flag produksi tercatat di audit, dengan alasan.
