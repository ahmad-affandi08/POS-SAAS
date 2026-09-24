<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Aksi\CatatMutasiKas;
use App\Domain\Kasir\Data\DataMutasiKas;
use App\Domain\Kasir\Enum\JenisMutasiKas;
use Illuminate\Validation\Rule;

/**
 * Item outbox `MutasiKas.Catat` (F-06): `{UuidShift, Jenis, UuidKategori?, Jumlah, Catatan?, UuidPencatat,
 * DicatatPada, UuidPenyetuju?}`.
 */
final class PenanganSinkronMutasiKas implements PenanganItemSinkron
{
    public function __construct(private readonly CatatMutasiKas $catat) {}

    public function AmbilJenis(): string
    {
        return 'MutasiKas.Catat';
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidShift' => ['required', 'string', 'ulid'],
            'Jenis' => ['required', 'string', Rule::enum(JenisMutasiKas::class)],
            'UuidKategori' => ['nullable', 'string', 'ulid'],
            'Jumlah' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'Catatan' => ['nullable', 'string', 'max:255'],
            'UuidPencatat' => ['required', 'string', 'ulid'],
            'DicatatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'UuidPenyetuju' => ['nullable', 'string', 'ulid'],
        ]);

        $catatan = isset($valid['Catatan']) ? trim((string) $valid['Catatan']) : '';

        return $this->catat->Jalankan(new DataMutasiKas(
            uuid: $uuid,
            idPerangkat: $konteks->idPerangkat,
            uuidShift: (string) $valid['UuidShift'],
            jenis: JenisMutasiKas::from((string) $valid['Jenis']),
            uuidKategori: isset($valid['UuidKategori']) ? (string) $valid['UuidKategori'] : null,
            jumlah: Uang::Dari((string) $valid['Jumlah']),
            catatan: $catatan === '' ? null : $catatan,
            uuidPencatat: (string) $valid['UuidPencatat'],
            dicatatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DicatatPada']),
            uuidPenyetuju: isset($valid['UuidPenyetuju']) ? (string) $valid['UuidPenyetuju'] : null,
        ));
    }
}
