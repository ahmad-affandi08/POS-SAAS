<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 23. Strategi Pengujian & Quality Gate

### 23.1 Piramida Pengujian

| Level | Tool | Cakupan |
|---|---|---|
| Unit (PHP) | Pest | Uang, KalkulatorPajak, KalkulatorPenjualan, MesinPromo, HPP (moving average/FIFO), state machine, posting rules |
| Unit (Dart) | `dart test` (paket `MesinKasir`), `flutter_test` | Mesin keranjang/pajak/promo, Uang, penomoran dokumen, repository & sync service (dengan mock API) |
| Widget & Golden (Flutter) | `flutter_test` + golden files | Layar jual, bayar, struk (render ESC/POS ke gambar), di ukuran HP/tablet/desktop |
| Unit (TS) | Vitest | Komponen & util back-office, format uang |
| **Test vector bersama** | JSON fixtures di `Spesifikasi/VektorUjiKalkulasi` dijalankan oleh Pest **dan** `dart test` | Menjamin kalkulasi aplikasi POS (offline) = server. Minimal 200 kasus: pajak inklusif/eksklusif, DPP nilai lain, PB1+SC, pembulatan, promo bertumpuk, split bill |
| Feature/Integration | Pest + MySQL (bukan SQLite, agar perilaku lock & tipe sama) | Setiap flow F-xx: happy path + edge case; tenant isolation; idempotensi sync |
| **Test arsitektur** | Pest `arch()` | Hanya `App\Domain\Pengelola` yang boleh melewati scope `MilikTenant`; domain tidak saling query tabel; kontroler tidak memanggil model domain lain secara langsung |
| **Invariant test** | Pest | Setelah setiap skenario: Σ debit = Σ kredit; saldo `SaldoStok` = Σ `MutasiStok`; nilai persediaan di neraca = Σ nilai stok; kas shift = ekspektasi |
| E2E Web | Playwright | Daftar → onboarding → produk → aktivasi perangkat → laporan; self-order QR |
| E2E Aplikasi | `integration_test` / **Patrol** di emulator Android & Windows (CI), iOS simulator (nightly) | Aktivasi → login PIN → buka shift → jual (online & **offline** dengan API mock dimatikan) → sinkron (API staging) → tutup shift |
| **Kontrak API** | OpenAPI diff (Scramble) + test DTO Dart | Mencegah perubahan API POS yang merusak aplikasi versi lama |
| Uji perangkat nyata | Lab kecil: 2 tablet Android murah, 1 POS all-in-one, 1 iPad, 1 iPhone, 1 PC Windows, 4 printer, serta perangkat all-in-one dari merek berbeda (bertambah sesuai adaptor) | Checklist manual per rilis (cetak, laci, scanner, layar kedua) |
| Load | k6 | Simulasi 300 tenant × 3 perangkat, polling KDS, sync burst pagi hari, di lingkungan staging paket Hostinger yang sama dengan produksi |
| Keamanan | Larastan rules, `composer audit`, OWASP ZAP baseline, pentest | Sebelum GA |

### 23.2 Quality Gate CI (wajib hijau untuk merge)

- `pint --test`, `phpstan` (Larastan), `rector --dry-run`
- `tsc --noEmit`, `eslint`, `prettier --check`
- `pest --parallel` (coverage minimum 80% untuk `app/Domain/*/Aksi` dan kalkulator)
- `vitest run`, Playwright smoke (web)
- `dart format --set-exit-if-changed`, `flutter analyze`, `dart test Paket/MesinKasir`, `flutter test` (termasuk golden)
- Build Android (AAB/APK) & Windows sukses di setiap PR aplikasi. Build iOS di branch rilis & nightly. CI Aplikasi Owner (analyze, test, build Android/iOS) setara Aplikasi POS
- Integration test aplikasi (alur jual online + offline)
- Build Vite sukses dan ukuran bundle web publik di bawah anggaran

### 23.3 Definition of Done (per flow)

- [ ] Spesifikasi flow (§8 format) disetujui PO
- [ ] Migrasi + model + action + policy + event/listener
- [ ] Dampak stok & jurnal sesuai tabel §11.3, dengan invariant test lulus
- [ ] UI responsif di semua lebar (web: 360/768/1280px, §17.4.4; POS: 360/800/1280dp di bingkai Ruang Kerja Kasir, §17.2.7), state kosong/loading/error. Tabel web memakai `TabelData` (§17.4.3). Untuk fitur aplikasi POS: diuji di Android, Windows, dan iPad, termasuk skenario offline
- [ ] Audit log & permission
- [ ] Nama tabel, kolom, folder, file, dan function sesuai konvensi §13.7 (istilah baru sudah masuk kamus)
- [ ] Desain lolos checklist review §17.6.11 dan semua keadaan wajib §17.6.6 terimplementasi
- [ ] Test (unit, feature, E2E untuk alur kritis)
- [ ] Dokumentasi pengguna singkat (help center)
- [ ] Demo di staging

### 23.4 Tata Kelola AI Agent (Keputusan D-10)

PRD tidak menjamin AI agent patuh. **Instruksi hanyalah saran; pengecekan otomatis adalah hukum.** Kepatuhan dijaga tiga lapis:

| Lapis | Mekanisme | Lokasi |
|---|---|---|
| **1. Konteks** | Aturan emas ringkas (< 200 baris) dibaca setiap sesi | `CLAUDE.md` |
| | Aturan per jenis file, dimuat hanya saat file yang cocok dibuka (`paths:`) | `.claude/rules/*.md` |
| | Potongan PRD per bagian & per flow (hasil generate, bukan diedit) | `Dokumen/` via `Alat/PecahPrd.py` |
| **2. Penjaga otomatis** | Pengecek konvensi: penamaan PascalCase/kebab-case, migrasi, float/double untuk uang, bypass scope tenant, URL & nama route | `Alat/CekKonvensi.py` (+ `Alat/KonvensiPengecualian.json`) |
| | Hook `PreToolUse`: tolak edit `.env`, `Dokumen/`, lockfile, migrasi yang sudah di-merge; minta persetujuan manusia untuk file penjaga; tolak force push, `--no-verify`, `migrate:fresh` di luar test | `.claude/hooks/LindungiFile.py`, `CekPerintah.py` |
| | Hook `PostToolUse`: format + cek konvensi setiap file yang diedit, pelanggaran dikirim balik ke agent | `.claude/hooks/CekSetelahEdit.py` |
| | Hook `Stop`: agent tidak boleh menyatakan selesai selama konvensi/dokumen melanggar | `.claude/hooks/CekSebelumSelesai.py` |
| | CI wajib hijau + CODEOWNERS untuk file penjaga | `.github/workflows/CekKepatuhan.yml`, `.github/CODEOWNERS` |
| | (Fase 0) Larastan, Pint, ESLint, lint Dart, test arsitektur Pest `arch()`, invariant test, test vector, test isolasi tenant | `Aplikasi/Web/`, `Aplikasi/`, `Paket/` |
| **3. Alur kerja** | Tugas terikat ID flow & BR, rencana dulu | skill `/mulai-flow` |
| | Pemeriksaan DoD sebelum selesai | skill `/cek-dod` |
| | Peninjau read-only yang terpisah dari penulis kode (menangkap kata Inggris, hard-code, perluasan cakupan) | subagent `penjaga-konvensi` |
| | Template PR menyebut Flow, BR, D-xx, dan bukti pengecekan | `.github/pull_request_template.md` |

**Aturan tata kelola:**
- File penjaga (`CLAUDE.md`, `.claude/`, `Alat/`, `.github/`, `PRD.md`, `Dokumen/`, konfigurasi lint/test, test arsitektur, test vector) hanya diubah atas persetujuan manusia dan ditinjau CODEOWNERS.
- Agent yang menemukan aturan bertabrakan atau tidak masuk akal **tidak menyimpang diam-diam**. Ia berhenti dan bertanya, atau menulis usulan di bagian "Usulan perubahan keputusan" pada PR.
- Setiap aturan baru di PRD yang penting **wajib punya pengecek otomatis**. Aturan yang tidak bisa dicek dimasukkan ke daftar tinjauan subagent `penjaga-konvensi`.
- Branch protection `main`: wajib PR, CI hijau, dan "Require review from Code Owners".
- Batasan yang diketahui: pengecek hanya memeriksa **format** nama, bukan bahasanya. Kata Bahasa Inggris dalam nama berformat benar ditangkap oleh subagent peninjau dan review manusia.
