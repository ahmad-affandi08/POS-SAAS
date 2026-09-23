<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Data;

/**
 * Estimasi HPP resep versi terbaru (BR-03.5, F-03 C.4). `status`: Tersedia | BelumTersedia | TanpaResep.
 * Semua angka string desimal: jumlah kotor 4 desimal, HPP 6 desimal.
 */
final readonly class DataHppResep
{
    public const TERSEDIA = 'Tersedia';

    public const BELUM_TERSEDIA = 'BelumTersedia';

    public const TANPA_RESEP = 'TanpaResep';

    /**
     * @param  list<array{NamaBahan: string, JumlahKotor: string, HppSatuanBahan: string|null, Subtotal: string|null}>  $baris
     */
    public function __construct(
        public string $status,
        public ?string $hppSatuan,
        public array $baris,
    ) {}

    /**
     * Prop `Hpp` halaman resep (tipe FE `PropsResepProduk.Hpp`).
     *
     * @return array{Status: string, HppSatuan: string|null, Baris: list<array{NamaBahan: string, JumlahKotor: string, HppSatuanBahan: string|null, Subtotal: string|null}>}
     */
    public function KeArray(): array
    {
        return ['Status' => $this->status, 'HppSatuan' => $this->hppSatuan, 'Baris' => $this->baris];
    }
}
