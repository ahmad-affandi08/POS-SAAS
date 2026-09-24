<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Aksi\BukaShift;
use App\Domain\Kasir\Data\DataBukaShift;

/**
 * Item outbox `Shift.Buka` (F-06): `{UuidPengguna, DibukaPada, KasAwal, Pecahan?: [{Nominal, Jumlah}], Bersama?}`.
 */
final class PenanganSinkronBukaShift implements PenanganItemSinkron
{
    public function __construct(private readonly BukaShift $bukaShift) {}

    public function AmbilJenis(): string
    {
        return 'Shift.Buka';
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DibukaPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'KasAwal' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'Pecahan' => ['nullable', 'array', 'max:30'],
            'Pecahan.*.Nominal' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'Pecahan.*.Jumlah' => ['required', 'integer', 'min:0', 'max:100000'],
            'Bersama' => ['sometimes', 'boolean'],
        ]);

        $pecahan = null;

        if (is_array($valid['Pecahan'] ?? null)) {
            $pecahan = [];

            foreach ($valid['Pecahan'] as $baris) {
                $pecahan[] = ['Nominal' => (string) $baris['Nominal'], 'Jumlah' => (int) $baris['Jumlah']];
            }
        }

        return $this->bukaShift->Jalankan(new DataBukaShift(
            uuid: $uuid,
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidPembuka: (string) $valid['UuidPengguna'],
            dibukaPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibukaPada']),
            kasAwal: Uang::Dari((string) $valid['KasAwal']),
            pecahan: $pecahan,
            bersama: (bool) ($valid['Bersama'] ?? false),
        ));
    }
}
