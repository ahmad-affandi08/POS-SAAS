<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Persediaan\Aksi\TerimaBahanTerbuangPos;
use App\Domain\Persediaan\Enum\AlasanBahanTerbuang;
use Illuminate\Validation\Rule;

/**
 * Item outbox `BahanTerbuang.Catat` (F-05f): bahan/menu terbuang dicatat di kasir/dapur. Bentuk `Data`:
 * `{UuidProduk, Jumlah (satuan dasar, string desimal), Alasan, Catatan?, UuidPengguna, DibuatPada}`. Uuid item = Uuid
 * catatan. Aturan bisnis di `TerimaBahanTerbuangPos`.
 */
final class PenanganSinkronBahanTerbuang implements PenanganItemSinkron
{
    public const JENIS = 'BahanTerbuang.Catat';

    public function __construct(private readonly TerimaBahanTerbuangPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidProduk' => ['required', 'string', 'ulid'],
            'Jumlah' => ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
            'Alasan' => ['required', 'string', Rule::enum(AlasanBahanTerbuang::class)],
            'Catatan' => ['nullable', 'string', 'max:255'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);

        return $this->terima->Jalankan(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidProduk: strtoupper((string) $valid['UuidProduk']),
            jumlah: Kuantitas::Dari((string) $valid['Jumlah']),
            alasan: AlasanBahanTerbuang::from((string) $valid['Alasan']),
            catatan: isset($valid['Catatan']) && is_string($valid['Catatan']) ? $valid['Catatan'] : null,
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
        );
    }
}
