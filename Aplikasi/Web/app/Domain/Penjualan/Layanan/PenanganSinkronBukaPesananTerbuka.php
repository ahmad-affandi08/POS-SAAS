<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Penjualan\Aksi\BukaPesananTerbukaPos;

/** `PesananTerbuka.Buka {Nomor, UuidMeja|null, Label|null, JumlahTamu, UuidPengguna, DibukaPada}`; Uuid item = Uuid pesanan. */
final class PenanganSinkronBukaPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.Buka';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = self::Validasi($data, [
            'Nomor' => ['required', 'string', 'max:80'],
            'UuidMeja' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'JumlahTamu' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
        ], 'DibukaPada');

        return $this->container->make(BukaPesananTerbukaPos::class)->Jalankan(self::Data($valid, $uuid, 'DibukaPada', $konteks, [
            'nomor' => (string) $valid['Nomor'],
            'uuidMeja' => self::Uuid($valid['UuidMeja'] ?? null),
            'label' => self::Teks($valid['Label'] ?? null),
            'jumlahTamu' => is_int($valid['JumlahTamu'] ?? null) ? $valid['JumlahTamu'] : 1,
        ]));
    }
}
