<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Laporan\Kueri\LaporanStok;

/**
 * Kotak Tindakan stok (D-23 C, izin lihat `persediaan.lihat`, dibatasi outlet akses): produk yang stoknya di bawah
 * atau sama dengan stok minimum gudangnya. Selesai sendiri saat stok diisi.
 */
final class PenyediaTindakanStok implements PenyediaTindakan
{
    public function __construct(private readonly LaporanStok $laporan) {}

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        if (! $konteks->CekIzin('persediaan.lihat')) {
            return [];
        }

        $kritis = $this->laporan->StokKritis($konteks->idOutletBoleh, batas: DataButirTindakan::BATAS_RINCIAN);

        return [new DataButirTindakan(
            'stok.kritis',
            'Persediaan',
            TingkatTindakan::Perhatian,
            'Stok menipis',
            'Stok di bawah batas minimum. Pesan ke pemasok sebelum habis.',
            (int) $kritis['Jumlah'],
            '/kelola/laporan/stok?tab=kritis',
            'Lihat stok kritis',
            array_map(fn (array $b): DataRincianTindakan => new DataRincianTindakan(
                (string) $b['Kunci'],
                (string) $b['NamaProduk'],
                "Sisa {$b['Saldo']} {$b['SimbolSatuan']}, minimum {$b['StokMinimum']} · {$b['NamaGudang']}",
                null,
                null,
            ), $kritis['Baris']),
        )];
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
