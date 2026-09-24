---
paths:
  - "Aplikasi/Web/resources/js/**"
  - "Aplikasi/Web/resources/css/**"
---

# Aturan back-office & web publik (PRD §13.5, §17.4, §17.5, §17.6)

- React + TypeScript strict. Folder & file PascalCase: `Halaman/`, `Komponen/`, `Fitur/`, `TataLetak/`, `Pustaka/`, `Tipe/`. Function PascalCase; hook React diawali `use` (`useDaftarProduk`).
- `Komponen/Ui/` adalah hasil CLI shadcn/ui: jangan diubah manual kecuali lewat token tema, dengan satu pengecualian: teks yang terlihat/dibacakan (label, `aria-label`, sr-only) diterjemahkan ke Bahasa Indonesia (disetujui pemilik produk). Setelah memasang ulang komponen lewat CLI, ulangi terjemahannya (test `KomponenUiTes` menjaganya). Tanpa varian `dark:` (D-14).
- Navigasi & form CRUD pakai Inertia. Data yang di-polling, tabel laporan besar, dan pencarian pakai TanStack Query dengan key dari `Pustaka/KunciKueri.ts`.
- Web publik (self-order, toko online) **tidak menghitung harga sendiri**: panggil endpoint server.
- Tipografi dari token §17.5: Atkinson Hyperlegible Next (UI) & Mono (kode, nomor dokumen, SKU). Uang: `tabular-nums`, rata kanan.
- Warna & ukuran **hanya dari token** (`@theme`). Dilarang hex lepas, gradien, efek kaca, bayangan dekoratif, emoji di UI. Palet final merek PAYOU (D-15) sudah ada di token; logo PAYOU dipakai lewat `Komponen/Merek/LogoMerek.tsx`, bukan file gambar langsung.
- Setiap layar punya keadaan: memuat (skeleton), kosong, galat, tanpa izin, data ekstrem (§17.6.6).
- Microcopy Bahasa Indonesia konkret, tombol kata kerja spesifik ("Simpan produk"), format Rupiah `Rp 1.250.000` (§17.6.7).
- Aksesibilitas: elemen interaktif asli (`button`, `a`, `label`), kontras WCAG AA, fokus keyboard terlihat.
