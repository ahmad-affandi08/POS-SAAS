<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Aksi\CatatBukaLaci;
use App\Domain\Kasir\Data\DataBukaLaci;

/**
 * Item outbox `Laci.Buka` (cetak struk bagian 4, POS-17): `{UuidShift, Alasan, UuidPembuka, DibukaPada,
 * UuidPenyetuju?}`.
 */
final class PenanganSinkronBukaLaci implements PenanganItemSinkron
{
    public function __construct(private readonly CatatBukaLaci $catat) {}

    public function AmbilJenis(): string
    {
        return 'Laci.Buka';
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidShift' => ['required', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'max:255'],
            'UuidPembuka' => ['required', 'string', 'ulid'],
            'DibukaPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'UuidPenyetuju' => ['nullable', 'string', 'ulid'],
        ]);

        return $this->catat->Jalankan(new DataBukaLaci(
            uuid: $uuid,
            idPerangkat: $konteks->idPerangkat,
            uuidShift: (string) $valid['UuidShift'],
            alasan: (string) $valid['Alasan'],
            uuidPembuka: (string) $valid['UuidPembuka'],
            dibukaPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibukaPada']),
            uuidPenyetuju: isset($valid['UuidPenyetuju']) ? (string) $valid['UuidPenyetuju'] : null,
        ));
    }
}
