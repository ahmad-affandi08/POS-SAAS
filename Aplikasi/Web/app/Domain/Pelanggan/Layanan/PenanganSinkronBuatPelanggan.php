<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Pelanggan\Aksi\TerimaPelangganPos;
use App\Domain\Pelanggan\Data\DataPelangganPos;

/**
 * Item outbox `Pelanggan.Buat` (F-16a): `{Nama, NoHp, Email?, UuidPengguna, DibuatPada}`; Uuid item = Uuid pelanggan.
 * Dikirim sebelum `Penjualan.Buat` yang merujuknya (outbox FIFO).
 */
final class PenanganSinkronBuatPelanggan implements PenanganItemSinkron
{
    public const JENIS = 'Pelanggan.Buat';

    public function __construct(private readonly TerimaPelangganPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi(['Uuid' => $uuid, ...$data], [
            'Uuid' => ['required', 'string', 'ulid'],
            'Nama' => ['required', 'string', 'max:150'],
            'NoHp' => ['required', 'string', 'max:30'],
            'Email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);
        $email = isset($valid['Email']) ? trim((string) $valid['Email']) : '';

        return $this->terima->Jalankan(new DataPelangganPos(
            uuid: strtoupper($uuid),
            idTenant: $konteks->idTenant,
            idOutlet: $konteks->idOutlet,
            idPerangkat: $konteks->idPerangkat,
            nama: trim((string) $valid['Nama']),
            noHp: (string) $valid['NoHp'],
            email: $email === '' ? null : $email,
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
        ));
    }
}
