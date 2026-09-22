---
name: cek-dod
description: Memeriksa Definition of Done sebelum menyatakan pekerjaan selesai atau membuat PR. Menjalankan pengecek konvensi, test, lint, lalu meminta tinjauan subagent penjaga-konvensi. Pakai setiap selesai mengerjakan flow atau perubahan kode.
---

# Cek Definition of Done (PRD §23.3)

Jalankan berurutan. Jangan melewati langkah yang gagal. Perbaiki kodenya, bukan pengeceknya.

1. `python3 Alat/CekKonvensi.py --berubah` → harus "Konvensi OK".
2. `python3 Alat/PecahPrd.py --cek` → harus sinkron (jika PRD.md berubah, manusia menjalankan ulang skripnya).
3. Pengecekan stack yang tersedia (lewati yang belum di-scaffold, sebutkan di laporan):
   - `Backend/`: `composer analisis` lalu `composer tes:cepat` (Pint, Larastan, Pest termasuk `arch()`)
   - `Backend/` web: `npm run periksa`
   - Flutter: `melos run periksa`
4. Delegasikan ke subagent **penjaga-konvensi** untuk meninjau diff terhadap PRD & aturan. Perbaiki semua temuan "Wajib".
5. Periksa checklist DoD:
   - [ ] Flow & BR yang dikerjakan jelas; tidak ada perluasan cakupan
   - [ ] Migrasi + model + Aksi + kebijakan + peristiwa/penangan sesuai pola
   - [ ] Dampak stok & jurnal sesuai §11.3, invariant test lulus
   - [ ] Test isolasi tenant & idempotensi (jika relevan)
   - [ ] UI: keadaan wajib §17.6.6 & checklist §17.6.11 (jika ada layar)
   - [ ] Log audit & permission
   - [ ] Tidak ada file penjaga yang diubah; tidak ada test yang di-skip/dilemahkan
6. Laporkan ke pengguna: apa yang dikerjakan (Flow/BR), hasil setiap pengecekan **apa adanya** (termasuk yang gagal atau belum bisa dijalankan), dan pertanyaan terbuka.
