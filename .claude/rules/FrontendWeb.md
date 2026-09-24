---
paths:
  - "Aplikasi/Web/resources/js/**"
  - "Aplikasi/Web/resources/css/**"
---

# Aturan back-office & web publik (PRD §13.5, §17.4, §17.5, §17.6)

- React + TypeScript strict. Folder & file PascalCase: `Halaman/`, `Komponen/`, `Fitur/`, `TataLetak/`, `Pustaka/`, `Tipe/`. Function PascalCase; hook React diawali `use` (`useDaftarProduk`).
- `Komponen/Ui/` adalah hasil CLI shadcn/ui: jangan diubah manual kecuali lewat token tema, dengan satu pengecualian: teks yang terlihat/dibacakan (label, `aria-label`, sr-only) diterjemahkan ke Bahasa Indonesia (disetujui pemilik produk). Setelah memasang ulang komponen lewat CLI, ulangi terjemahannya (test `KomponenUiTes` menjaganya). Tanpa varian `dark:` (D-14).
- Navigasi & form CRUD pakai Inertia. Data yang di-polling, **semua tabel data**, dan pencarian pakai TanStack Query dengan key dari `Pustaka/KunciKueri.ts`.
- **Tabel (D-16, §17.4.3):** semua tabel memakai `Komponen/TabelData/` (TanStack Table v8 + TanStack Query). Dilarang merakit `<table>`/`<Table>` sendiri di halaman. Mode server (bawaan): endpoint JSON `/internal/...`, parameter `cari`, `urut` (`Kolom`/`-Kolom`), `halaman`, `perHalaman` (maks 100), `saring[Kolom]`; respons `{Data, Meta}`; `placeholderData: keepPreviousData`; keadaan tabel di URL. Fitur: cari (debounce 300 ms), saring per kolom + chip, urut (bertingkat dengan Shift), atur kolom (disimpan per pengguna), kolom identitas & aksi menempel, pilih baris + aksi massal, aksi baris, ekspor sesuai saring. Mode lokal hanya untuk ≤ 200 baris yang sudah ada di halaman.
- **Responsif (D-16, §17.4.4):** setiap halaman rapi dari 360px sampai layar lebar tanpa gulir horizontal halaman. < 640px: menu Sheet, satu kolom, dialog jadi lembar bawah, tombol utama menempel di bawah, `TabelData` jadi daftar bertumpuk dengan saring di Sheet. 640–1023px: kolom prioritas rendah disembunyikan, kolom identitas menempel. Target sentuh ≥ 44px pada `pointer: coarse`. Uji di 360/768/1280px.
- Web publik (self-order, toko online) **tidak menghitung harga sendiri**: panggil endpoint server.
- Tipografi dari token §17.5: Atkinson Hyperlegible Next (UI) & Mono (kode, nomor dokumen, SKU). Uang: `tabular-nums`, rata kanan.
- Warna & ukuran **hanya dari token** (`@theme`). Dilarang hex lepas, gradien, efek kaca, bayangan dekoratif, emoji di UI. Palet final merek PAYOU (D-15) sudah ada di token; logo PAYOU dipakai lewat `Komponen/Merek/LogoMerek.tsx`, bukan file gambar langsung.
- Setiap layar punya keadaan: memuat (skeleton), kosong, galat, tanpa izin, data ekstrem (§17.6.6).
- Microcopy Bahasa Indonesia konkret, tombol kata kerja spesifik ("Simpan produk"), format Rupiah `Rp 1.250.000` (§17.6.7).
- Aksesibilitas: elemen interaktif asli (`button`, `a`, `label`), kontras WCAG AA, fokus keyboard terlihat.
