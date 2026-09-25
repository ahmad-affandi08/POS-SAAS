<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TambahBarisPesananTerbukaPos;
use App\Domain\Penjualan\Data\DataBarisPesananTerbuka;

/**
 * `PesananTerbuka.Tambah {UuidPesanan, Ronde, KirimDapur, UuidPengguna, DikirimPada, Baris [{Uuid, UuidProduk,
 * UuidProdukSatuan|null, Jumlah, HargaSatuan, HargaPilihan, Pilihan [{UuidPilihan, Nama, Harga}], Catatan}]}`.
 */
final class PenanganSinkronTambahPesananTerbuka extends PenanganSinkronPesananTerbuka
{
    public const JENIS = 'PesananTerbuka.Tambah';

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $uang = 'regex:'.ValidasiItemSinkron::POLA_UANG;
        $valid = self::Validasi($data, [
            'UuidPesanan' => ['required', 'string', 'ulid'],
            'Ronde' => ['required', 'integer', 'min:1', 'max:99'],
            'KirimDapur' => ['required', 'boolean'],
            'Baris' => ['required', 'array', 'min:1', 'max:200'],
            'Baris.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:'.self::POLA_JUMLAH, 'not_in:0,0.0,0.00,0.000,0.0000'],
            'Baris.*.HargaSatuan' => ['required', 'string', $uang],
            'Baris.*.HargaPilihan' => ['sometimes', 'nullable', 'string', $uang],
            'Baris.*.Pilihan' => ['sometimes', 'nullable', 'array', 'max:30'],
            'Baris.*.Pilihan.*.UuidPilihan' => ['required', 'string', 'ulid'],
            'Baris.*.Pilihan.*.Nama' => ['required', 'string', 'max:100'],
            'Baris.*.Pilihan.*.Harga' => ['required', 'string', $uang],
            'Baris.*.Catatan' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], 'DikirimPada');

        return $this->container->make(TambahBarisPesananTerbukaPos::class)->Jalankan(self::Data($valid, (string) $valid['UuidPesanan'], 'DikirimPada', $konteks, [
            'ronde' => (int) $valid['Ronde'],
            'kirimDapur' => (bool) $valid['KirimDapur'],
            'baris' => array_values(array_map(fn (array $b): DataBarisPesananTerbuka => new DataBarisPesananTerbuka(
                strtoupper((string) $b['Uuid']),
                strtoupper((string) $b['UuidProduk']),
                self::Uuid($b['UuidProdukSatuan'] ?? null),
                (string) $b['Jumlah'],
                (string) $b['HargaSatuan'],
                is_string($b['HargaPilihan'] ?? null) ? $b['HargaPilihan'] : '0',
                array_values(array_map(fn (array $p): array => ['UuidPilihan' => strtoupper((string) $p['UuidPilihan']), 'Nama' => (string) $p['Nama'], 'Harga' => (string) $p['Harga']], (array) ($b['Pilihan'] ?? []))),
                self::Teks($b['Catatan'] ?? null),
            ), (array) $valid['Baris'])),
        ]));
    }
}
