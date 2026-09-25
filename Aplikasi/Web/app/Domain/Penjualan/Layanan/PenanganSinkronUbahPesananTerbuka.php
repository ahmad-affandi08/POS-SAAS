<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Penjualan\Aksi\UbahPesananTerbukaPos;

/**
 * `PesananTerbuka.Ubah {UuidPesanan, UuidMeja?, Label?, JumlahTamu?, UuidPengguna, DiubahPada}`: hanya bidang yang
 * dikirim yang diubah (UuidMeja null = lepas dari meja). Last-writer-wins menurut DiubahPada.
 */
final class PenanganSinkronUbahPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.Ubah';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = self::Validasi($data, [
            'UuidPesanan' => ['required', 'string', 'ulid'],
            'UuidMeja' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'JumlahTamu' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
        ], 'DiubahPada');

        return $this->container->make(UbahPesananTerbukaPos::class)->Jalankan(self::Data($valid, (string) $valid['UuidPesanan'], 'DiubahPada', $konteks, [
            'ubahMeja' => array_key_exists('UuidMeja', $data),
            'uuidMeja' => self::Uuid($valid['UuidMeja'] ?? null),
            'ubahLabel' => array_key_exists('Label', $data),
            'label' => self::Teks($valid['Label'] ?? null),
            'jumlahTamu' => is_int($valid['JumlahTamu'] ?? null) ? $valid['JumlahTamu'] : null,
        ]));
    }
}
