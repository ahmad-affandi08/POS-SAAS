<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TandaiMejaBersihPos;

/**
 * Item outbox `Meja.Bersih {UuidMeja, UuidPengguna, DibersihkanPada}` (K-12): meja yang perlu dibersihkan setelah
 * dibayar ditandai siap dipakai lagi.
 */
final class PenanganSinkronBersihkanMeja implements PenanganItemSinkron
{
    public const JENIS = 'Meja.Bersih';

    public function __construct(private readonly TandaiMejaBersihPos $aksi) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidMeja' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DibersihkanPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);

        return $this->aksi->Jalankan(
            $konteks->idOutlet,
            strtoupper((string) $valid['UuidMeja']),
            strtoupper((string) $valid['UuidPengguna']),
            ValidasiItemSinkron::AmbilWaktu((string) $valid['DibersihkanPada']),
        );
    }
}
