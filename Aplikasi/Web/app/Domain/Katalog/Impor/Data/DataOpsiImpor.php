<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Data;

use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Enum\ModeImpor;

/**
 * Opsi impor dari langkah pemetaan (F-03, DesainF03 C.6): mode, kelompok pajak bawaan untuk baris tanpa pajak,
 * jenis bawaan untuk baris tanpa jenis, serta izin membuat kategori/satuan yang belum ada.
 */
final readonly class DataOpsiImpor
{
    public function __construct(
        public ModeImpor $mode,
        public ?int $idKelompokPajakBawaan,
        public JenisProduk $jenisBawaan,
        public bool $buatKategoriBaru,
        public bool $buatSatuanBaru,
    ) {}

    public static function Bawaan(): self
    {
        return new self(ModeImpor::TambahDanPerbarui, null, JenisProduk::Stok, true, true);
    }

    /**
     * @param  array<string, mixed>  $opsi  isi `ImporProduk.Opsi`
     */
    public static function DariArray(array $opsi): self
    {
        $bawaan = self::Bawaan();
        $idKelompokPajak = $opsi['IdKelompokPajakBawaan'] ?? null;

        return new self(
            ModeImpor::tryFrom((string) ($opsi['Mode'] ?? '')) ?? $bawaan->mode,
            is_int($idKelompokPajak) ? $idKelompokPajak : null,
            JenisProduk::tryFrom((string) ($opsi['JenisBawaan'] ?? '')) ?? $bawaan->jenisBawaan,
            (bool) ($opsi['BuatKategoriBaru'] ?? $bawaan->buatKategoriBaru),
            (bool) ($opsi['BuatSatuanBaru'] ?? $bawaan->buatSatuanBaru),
        );
    }

    /**
     * @return array{Mode: string, IdKelompokPajakBawaan: int|null, JenisBawaan: string, BuatKategoriBaru: bool, BuatSatuanBaru: bool}
     */
    public function KeArray(): array
    {
        return [
            'Mode' => $this->mode->value,
            'IdKelompokPajakBawaan' => $this->idKelompokPajakBawaan,
            'JenisBawaan' => $this->jenisBawaan->value,
            'BuatKategoriBaru' => $this->buatKategoriBaru,
            'BuatSatuanBaru' => $this->buatSatuanBaru,
        ];
    }
}
