## Flow & aturan bisnis

- Flow: <!-- misal F-07 · Transaksi Penjualan -->
- Aturan bisnis yang dikerjakan: <!-- misal BR-07.1, BR-07.6 -->
- Keputusan terkait: <!-- misal D-05, D-06 -->
- Sengaja tidak dikerjakan di PR ini:

## Ringkasan perubahan

<!-- Apa yang berubah dan kenapa. Sebutkan tabel, Aksi, endpoint, dan layar baru. -->

## Dampak stok & jurnal

<!-- Sesuai tabel PRD §11.3, atau "tidak ada". -->

## Bukti pengecekan

- [ ] `python3 Alat/CekKonvensi.py --berubah` → OK
- [ ] `python3 Alat/PecahPrd.py --cek` → sinkron
- [ ] Lint & analisis stack terkait → hijau
- [ ] Test (termasuk isolasi tenant, idempotensi, invariant bila relevan) → hijau
- [ ] Tinjauan subagent `penjaga-konvensi` → tidak ada temuan "Wajib"

## Definition of Done (PRD §23.3)

- [ ] Nama tabel, kolom, folder, file, function, URL sesuai §13.7 (Bahasa Indonesia)
- [ ] Tidak ada uang/kuantitas float/double, tidak ada tarif pajak di-hard-code
- [ ] Dokumen terposting tidak diedit; stok & jurnal lewat peristiwa
- [ ] UI: keadaan wajib §17.6.6 & checklist §17.6.11 (jika ada layar)
- [ ] Tidak ada file penjaga yang diubah, tidak ada test yang di-skip/dilemahkan

## Usulan perubahan keputusan (opsional)

<!-- Jika implementasi menemukan aturan PRD yang bertabrakan/tidak masuk akal, tulis usulan di sini.
     Agent tidak mengubah keputusan D-xx sendiri; pemilik produk yang memutuskan. -->
