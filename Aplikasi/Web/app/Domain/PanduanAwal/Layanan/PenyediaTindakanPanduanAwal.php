<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\PanduanAwal\Kueri\LangkahBerikutnya;

/**
 * Kotak Tindakan domain Panduan awal (D-24): checklist "Langkah berikutnya" yang dulu tampil di Beranda kini menjadi
 * butir Info (panduan awal, aktifkan perangkat kasir, stok awal, undang staf, PIN, metode bayar selain tunai). Status
 * tetap dihitung dari data; butir hilang sendiri setelah selesai. Izin per butir mengikuti `LangkahBerikutnya`.
 */
final class PenyediaTindakanPanduanAwal implements PenyediaTindakan
{
    public function __construct(private readonly LangkahBerikutnya $langkah) {}

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        $butir = [];

        foreach ($this->langkah->Ambil($konteks->idTenant, $konteks->idPengguna) as $item) {
            if ($item['Selesai']) {
                continue;
            }

            $butir[] = new DataButirTindakan(
                'awal.'.$item['Kunci'],
                'Persiapan toko',
                TingkatTindakan::Info,
                $item['Judul'],
                $item['Keterangan'],
                1,
                $item['Tautan'],
                'Kerjakan',
            );
        }

        return $butir;
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}
