<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Penjualan\Aksi\KirimBarisPesananKeDapurPos;

/**
 * `PesananTerbuka.KirimDapur {UuidPesanan, Ronde, UuidBaris [..], UuidPengguna, DikirimPada}`: kirim baris yang
 * sebelumnya ditahan.
 */
final class PenanganSinkronKirimDapurPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.KirimDapur';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = self::Validasi($data, [
            'UuidPesanan' => ['required', 'string', 'ulid'],
            'Ronde' => ['required', 'integer', 'min:1', 'max:99'],
            'UuidBaris' => ['required', 'array', 'min:1', 'max:200'],
            'UuidBaris.*' => ['required', 'string', 'ulid', 'distinct'],
        ], 'DikirimPada');

        return $this->container->make(KirimBarisPesananKeDapurPos::class)->Jalankan(self::Data($valid, (string) $valid['UuidPesanan'], 'DikirimPada', $konteks, [
            'ronde' => (int) $valid['Ronde'],
            'uuidBaris' => array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) $valid['UuidBaris'])),
        ]));
    }
}
