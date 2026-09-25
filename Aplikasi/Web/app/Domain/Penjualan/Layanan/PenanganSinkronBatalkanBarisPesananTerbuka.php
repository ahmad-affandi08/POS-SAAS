<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Penjualan\Aksi\BatalkanBarisPesananTerbukaPos;

/**
 * `PesananTerbuka.BatalkanBaris {UuidPesanan, UuidBaris [..], Alasan|null, UuidPengguna, UuidPenyetuju|null,
 * DibatalkanPada}` (BR-07.5).
 */
final class PenanganSinkronBatalkanBarisPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.BatalkanBaris';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = self::Validasi($data, [
            'UuidPesanan' => ['required', 'string', 'ulid'],
            'UuidBaris' => ['required', 'array', 'min:1', 'max:200'],
            'UuidBaris.*' => ['required', 'string', 'ulid', 'distinct'],
            'Alasan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'UuidPenyetuju' => ['sometimes', 'nullable', 'string', 'ulid'],
        ], 'DibatalkanPada');

        return $this->container->make(BatalkanBarisPesananTerbukaPos::class)->Jalankan(self::Data($valid, (string) $valid['UuidPesanan'], 'DibatalkanPada', $konteks, [
            'uuidBaris' => array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) $valid['UuidBaris'])),
            'alasan' => self::Teks($valid['Alasan'] ?? null),
            'uuidPenyetuju' => self::Uuid($valid['UuidPenyetuju'] ?? null),
        ]));
    }
}
