<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Aksi\BukaUlangShift;
use App\Domain\Kasir\Data\DataBukaUlangShift;

/**
 * Item outbox `Shift.BukaUlang` (K-18): `{UuidShift, UuidPengguna, UuidPenyetuju, Alasan, DibukaUlangPada}`; `Uuid`
 * item = Uuid log buka ulang (idempoten).
 */
final class PenanganSinkronBukaUlangShift implements PenanganItemSinkron
{
    public function __construct(private readonly BukaUlangShift $bukaUlang) {}

    public function AmbilJenis(): string
    {
        return 'Shift.BukaUlang';
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'UuidPenyetuju' => ['required', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'max:255'],
            'DibukaUlangPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);

        return $this->bukaUlang->Jalankan(new DataBukaUlangShift(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            uuidShift: strtoupper((string) $valid['UuidShift']),
            uuidPeminta: strtoupper((string) $valid['UuidPengguna']),
            uuidPenyetuju: strtoupper((string) $valid['UuidPenyetuju']),
            alasan: (string) $valid['Alasan'],
            dibukaUlangPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibukaUlangPada']),
        ));
    }
}
