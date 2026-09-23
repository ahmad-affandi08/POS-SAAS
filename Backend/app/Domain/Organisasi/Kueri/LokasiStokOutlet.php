<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Gudang;

/**
 * BR-02.4: setiap outlet wajib punya minimal satu lokasi stok aktif untuk barang jual (bukan Rusak/Dalam perjalanan).
 */
final class LokasiStokOutlet
{
    public function HitungLokasiStokJual(int $idOutlet, ?int $kecualiIdGudang = null): int
    {
        $jenisJual = array_values(array_map(
            fn (JenisGudang $jenis) => $jenis->value,
            array_filter(JenisGudang::cases(), fn (JenisGudang $jenis) => $jenis->CekLokasiStokJual()),
        ));

        return Gudang::query()
            ->where('IdOutlet', $idOutlet)
            ->where('Status', StatusOrganisasi::Aktif->value)
            ->whereIn('Jenis', $jenisJual)
            ->when($kecualiIdGudang !== null, fn ($kueri) => $kueri->whereKeyNot($kecualiIdGudang))
            ->lockForUpdate()
            ->count();
    }
}
