---
name: mulai-flow
description: Memulai pekerjaan pada satu flow bisnis (P-xx atau F-xx) dari PRD. Pakai setiap kali akan mengimplementasikan atau mengubah fitur, sebelum menulis kode apa pun. Argumen berupa ID flow, misal "F-07" atau "P-03".
---

# Mulai flow

Argumen: ID flow (`P-01` s.d. `P-12`, `F-00` s.d. `F-20`), bisa disertai bagian/BR tertentu.

1. **Baca konteks yang tepat, bukan PRD utuh:**
   - `Dokumen/Flow/<ID tanpa tanda hubung>*.md` (misal `Dokumen/Flow/F07*.md`)
   - `Dokumen/Keputusan.md`
   - Bagian PRD yang dirujuk flow itu di `Dokumen/Prd/` (misal §11 untuk jurnal, §15 untuk tabel, §16 untuk API, §17.6 untuk UI)
   - Aturan di `.claude/rules/` untuk folder yang akan disentuh
2. **Cek prasyarat** di `Dokumen/Prd/Bagian07*.md` (urutan implementasi). Jika flow prasyarat belum ada di kode, **berhenti** dan laporkan ke pengguna.
3. **Susun rencana singkat** berisi:
   - Langkah flow & aturan bisnis yang dikerjakan (`BR-xx`), dan yang sengaja **tidak** dikerjakan
   - Keputusan terkait (`D-xx`) dan aturan emas CLAUDE.md yang relevan
   - Tabel/kolom baru (nama PascalCase sesuai §15), Aksi, Peristiwa, endpoint (URL Indonesia kebab-case), layar
   - Dampak stok & jurnal (tabel §11.3) jika ada
   - Test yang akan ditulis: perilaku per BR, isolasi tenant, idempotensi, invariant
   - Hal yang ambigu atau bertabrakan dengan PRD → tulis sebagai pertanyaan, **jangan diputuskan sendiri**
4. **Minta persetujuan pengguna** atas rencana bila pekerjaan menyentuh lebih dari satu domain, skema database, kontrak API, atau data master. Pekerjaan kecil yang jelas boleh langsung dikerjakan.
5. Kerjakan dalam langkah kecil. Setelah selesai, jalankan `/cek-dod`.
