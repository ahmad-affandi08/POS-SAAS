# Backend

Laravel 13 (PHP 8.3) untuk API, back-office Inertia React, web publik, dan Platform Pengelola.
Aturan kerja ada di `../CLAUDE.md` dan `../.claude/rules/`. Spesifikasi di `../PRD.md` (potongan di `../Dokumen/`).

## Prasyarat

- PHP 8.3 (Composer dikunci ke platform 8.3 agar sama dengan Hostinger)
- MySQL 8 **Linux** (Docker/WSL2) dengan `lower_case_table_names=0`
- Node 22

## Menjalankan

```bash
composer install
npm ci
cp .env.example .env && php artisan key:generate   # isi kredensial DB lokal di .env
php artisan migrate
composer run dev
```

## Pengecekan (wajib hijau sebelum PR)

```bash
composer analisis     # Pint + Larastan level 8
composer tes:cepat    # Pest: Unit, Fitur (MySQL PosSaasTes), Arsitektur
npm run periksa       # tsc, ESLint (aturan penamaan), Prettier, Vitest
npm run build
```

Database test: `PosSaasTes`, user `pos_saas` / `rahasia-lokal-tes` (lihat `phpunit.xml`).
