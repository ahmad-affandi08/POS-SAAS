---
paths:
  - "Backend/routes/**"
  - "Backend/app/Http/**"
---

# Aturan rute & API (PRD §13.6, §16, D-06)

- URL huruf kecil kebab-case Bahasa Indonesia, kata benda tunggal: `/kelola/produk`, `/api/pos/v1/sinkron/kirim`. Parameter `{camelCase}`. Query `?sejak=`, `?kata=`, `saring[...]`, `urut=`.
- Nama route: titik + kebab-case, misal `kelola.produk.daftar`, `pos.sinkron.kirim`.
- Tiga lapisan API: `/internal/*` (sesi, back-office), `/api/pos/v1/*` (device token), `/api/pemilik/v1/*` (user token), `/api/v1/*` (token publik bercakupan). Jangan mencampur mekanismenya.
- Key JSON = nama kolom PascalCase. Uang sebagai **string desimal** (`"15000.00"`). Tanggal ISO-8601 UTC.
- Format galat seragam: `{"Galat": {"Kode": "...", "Pesan": "...", "Detail": {...}}}`.
- Endpoint mutasi POS wajib menerima `Idempotency-Key` dan idempoten.
- Header kustom: `X-Id-Kasir`, `X-Versi-Aplikasi`, `X-Skema-Sinkron`, `X-Tanda-Tangan`.
- API POS & Pemilik kompatibel mundur 2 versi minor aplikasi. Perubahan yang merusak kontrak → `/v2`. Kontrak OpenAPI (Scramble) ikut diperbarui.
- Kontroler tipis: validasi lewat `...Permintaan`, panggil Aksi, kembalikan `...Respons`/Inertia. Tanpa logika bisnis.
- Setiap endpoint tenant diuji isolasi tenant (user tenant A tidak bisa mengakses data tenant B).
