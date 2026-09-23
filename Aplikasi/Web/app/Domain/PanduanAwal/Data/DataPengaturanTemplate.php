<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Data;

/**
 * Pengaturan default template sektor (P-03 `Isi.Pengaturan`). Null = tidak ada/rusak di template.
 * PembulatanTunai, StokBolehMinus, MetodeHpp masuk `Tenant.Pengaturan`; sisanya hanya usulan langkah pajak.
 */
final readonly class DataPengaturanTemplate
{
    /**
     * @param  array{Kelipatan: int, Arah: string}|null  $pembulatanTunai
     */
    public function __construct(
        public ?array $pembulatanTunai = null,
        public ?bool $stokBolehMinus = null,
        public ?string $metodeHpp = null,
        public ?string $persenBiayaLayanan = null,
        public ?bool $hargaTermasukPajak = null,
        public ?bool $biayaLayananMasukDpp = null,
    ) {}

    /**
     * Nilai untuk `Tenant.Pengaturan` (hanya yang terisi).
     *
     * @return array<string, mixed>
     */
    public function AmbilPengaturanTenant(): array
    {
        return array_filter([
            'PembulatanTunai' => $this->pembulatanTunai,
            'StokBolehMinus' => $this->stokBolehMinus,
            'MetodeHpp' => $this->metodeHpp,
        ], fn (mixed $nilai) => $nilai !== null);
    }
}
