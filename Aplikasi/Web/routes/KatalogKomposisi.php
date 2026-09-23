<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Perantara\WajibIzinTenant;

/*
 * Rute back-office F-03 Tim 3 Modifier & Resep (kelompok pilihan, pilihan produk, resep, komponen paket), PRD §13.6,
 * D-06, DesainF03 D.1. Didaftarkan dari routes/web.php di dalam grup `/kelola` (auth + IdentifikasiTenantSesi …
 * BatasiTenantDitangguhkan). Rute memakai `SiapkanAuditTenant` dan izin lewat `$izin`. Parameter ULID dibatasi pola
 * ULID. Diisi Tim 3 pada Wave 1.
 */

$izin = static fn (IzinTenant $izin): string => WajibIzinTenant::class.':'.$izin->value;
