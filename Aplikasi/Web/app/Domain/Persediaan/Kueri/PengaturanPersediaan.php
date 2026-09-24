<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;

/**
 * Props halaman pengaturan persediaan (tipe FE `PropsPengaturanPersediaan`, DesainF05a C.8): metode HPP, izin stok
 * minus, dan apakah metode HPP sudah terkunci karena tenant punya riwayat stok (H-4).
 */
final class PengaturanPersediaan
{
    private const KETERANGAN_METODE = [
        'RataRata' => 'HPP dihitung ulang setiap ada barang masuk dari rata-rata nilai persediaan. Cocok untuk sebagian besar usaha.',
        'Fifo' => 'Barang yang masuk lebih dulu dianggap keluar lebih dulu. Cocok bila harga beli sering berubah atau barang punya kedaluwarsa.',
    ];

    public function __construct(
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly CekAdaMutasi $cekAdaMutasi,
    ) {}

    /**
     * @return array{MetodeHpp: string, StokBolehMinus: bool, MetodeHppTerkunci: bool, AlasanTerkunci: string|null, OpsiMetodeHpp: list<array{Nilai: string, Label: string, Keterangan: string}>}
     */
    public function Ambil(): array
    {
        $data = $this->pengaturan->Ambil();
        $terkunci = $this->cekAdaMutasi->Jalankan();

        return [
            'MetodeHpp' => $data->metodeHpp->value,
            'StokBolehMinus' => $data->stokBolehMinus,
            'MetodeHppTerkunci' => $terkunci,
            'AlasanTerkunci' => $terkunci ? 'Metode HPP tidak bisa diubah karena sudah ada riwayat stok.' : null,
            'OpsiMetodeHpp' => array_map(fn (MetodeHpp $metode): array => [
                'Nilai' => $metode->value,
                'Label' => $metode->AmbilLabel(),
                'Keterangan' => self::KETERANGAN_METODE[$metode->value],
            ], MetodeHpp::cases()),
        ];
    }
}
