<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Penjualan\Aksi\PindahBarisPesananTerbukaPos;

/**
 * `PesananTerbuka.PindahBaris {UuidPesanan (asal), UuidTujuan, UuidBaris [..], TutupAsal, UuidPengguna, DipindahPada}`
 * (v1.99): pisah tagihan (pindah sebagian item ke pesanan baru) dan gabung meja/tagihan (pindah semua + tutup asal).
 */
final class PenanganSinkronPindahBarisPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.PindahBaris';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = self::Validasi($data, [
            'UuidPesanan' => ['required', 'string', 'ulid'],
            'UuidTujuan' => ['required', 'string', 'ulid'],
            'UuidBaris' => ['required', 'array', 'min:1', 'max:200'],
            'UuidBaris.*' => ['required', 'string', 'ulid', 'distinct'],
            'TutupAsal' => ['sometimes', 'boolean'],
        ], 'DipindahPada');

        return $this->container->make(PindahBarisPesananTerbukaPos::class)->Jalankan(self::Data($valid, (string) $valid['UuidPesanan'], 'DipindahPada', $konteks, [
            'uuidTujuan' => self::Uuid($valid['UuidTujuan']),
            'uuidBaris' => array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) $valid['UuidBaris'])),
            'tutupAsal' => (bool) ($valid['TutupAsal'] ?? false),
        ]));
    }
}
