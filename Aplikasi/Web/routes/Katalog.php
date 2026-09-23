<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Perantara\WajibIzinTenant;

/*
 * Rute back-office F-03 Tim 1 Katalog Inti (produk, kategori, satuan, varian, gambar, barcode internal, batas stok),
 * PRD §13.6, D-06, DesainF03 D.1. Didaftarkan dari routes/web.php di dalam grup `/kelola` (auth +
 * IdentifikasiTenantSesi … BatasiTenantDitangguhkan). Rute memakai `SiapkanAuditTenant` dan izin lewat `$izin`.
 * Parameter `{produk}` dan ULID lain dibatasi pola ULID agar `/kelola/produk/buat`, `/cari`, `/impor`, `/ekspor`
 * tidak bentrok. Diisi Tim 1 pada Wave 1.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
