<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Model\MutasiStok;

/**
 * True bila tenant aktif sudah punya MutasiStok (mengunci perubahan MetodeHpp, DesainF05a H-4). Bacaan mengunci
 * (`sharedLock`) supaya tidak membaca snapshot usang saat dipanggil di bawah kunci X Tenant oleh
 * `UbahPengaturanPersediaan`; mutasi baru selalu mengambil kunci S Tenant lebih dulu (L1), jadi keduanya berurutan.
 */
final class CekAdaMutasi
{
    public function Jalankan(): bool
    {
        return MutasiStok::query()->select('Id')->limit(1)->sharedLock()->first() !== null;
    }
}
