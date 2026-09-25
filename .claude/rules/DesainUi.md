---
paths:
  - "Aplikasi/Web/resources/js/Halaman/**"
  - "Aplikasi/Web/resources/js/Komponen/**"
  - "Aplikasi/Web/resources/js/TataLetak/**"
  - "Aplikasi/*/lib/Fitur/**"
  - "Aplikasi/*/lib/Tampilan/**"
  - "Paket/SistemDesain/**"
---

# Checklist desain sebelum layar dianggap selesai (PRD §17.6.11)

- [ ] Layar tetap bisa dipahami jika semua warna dihapus.
- [ ] Warna hanya untuk aksi utama & status; status selalu disertai teks/ikon.
- [ ] Tanpa gradien (kecuali kepala sidebar merek `BrandGelap` → `Brand`, D-15), efek kaca, bayangan dekoratif, emoji, ilustrasi dekoratif.
- [ ] Tidak ada kartu yang lebih jelas bila dijadikan baris tabel.
- [ ] Web: tabel memakai `TabelData` (TanStack Table + Query, §17.4.3) dengan cari, saring, urut, atur kolom, paginasi server, keadaan di URL.
- [ ] Web: rapi di 360 / 768 / 1280px tanpa gulir horizontal halaman (§17.4.4).
- [ ] POS: layar berada di bingkai Ruang Kerja Kasir, maksimal dua ketukan dari layar Jual, rapi di 360 / 800 / 1280dp (§17.2.7).
- [ ] Font & ukuran dari token §17.5; uang tabular rata kanan; kode memakai font Mono.
- [ ] Keadaan §17.6.6 lengkap (memuat, kosong, galat, offline, tertunda sinkron, butuh persetujuan, tanpa izin, data ekstrem).
- [ ] Microcopy §17.6.7 & kamus istilah §13.7.1.
- [ ] Mode kepadatan benar: Nyaman (POS, KDS, Owner, web publik) / Ringkas (back-office, pengelola).
