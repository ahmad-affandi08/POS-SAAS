<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Penjualan\Aksi\BatalkanPesananTerbukaPos;

/**
 * `PesananTerbuka.Batal {UuidPesanan, Alasan, UuidPengguna, UuidPenyetuju|null, DibatalkanPada}`.
 */
final class PenanganSinkronBatalPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.Batal';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = self::Validasi($data, [
            'UuidPesanan' => ['required', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'min:3', 'max:255'],
            'UuidPenyetuju' => ['sometimes', 'nullable', 'string', 'ulid'],
        ], 'DibatalkanPada');

        return $this->container->make(BatalkanPesananTerbukaPos::class)->Jalankan(self::Data($valid, (string) $valid['UuidPesanan'], 'DibatalkanPada', $konteks, [
            'alasan' => self::Teks($valid['Alasan']),
            'uuidPenyetuju' => self::Uuid($valid['UuidPenyetuju'] ?? null),
        ]));
    }
}
