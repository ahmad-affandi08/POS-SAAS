<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Pelanggan\Aksi\TerimaPemakaianSesiPos;
use App\Domain\Pelanggan\Data\DataPemakaianSesiPos;

/**
 * Item outbox `Sesi.Pakai` (F-16d bagian 2): pelanggan memakai sesi paket di kasir. Bentuk `Data`: `{UuidSaldoSesi,
 * UuidProduk, Jumlah, UuidPengguna, DibuatPada}`. Uuid item = Uuid dokumen. Aturan bisnis di `TerimaPemakaianSesiPos`.
 */
final class PenanganSinkronPakaiSesi implements PenanganItemSinkron
{
    public const JENIS = 'Sesi.Pakai';

    public function __construct(private readonly TerimaPemakaianSesiPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidSaldoSesi' => ['required', 'string', 'ulid'],
            'UuidProduk' => ['required', 'string', 'ulid'],
            'Jumlah' => ['required', 'integer', 'min:1', 'max:100'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);

        return $this->terima->Jalankan(new DataPemakaianSesiPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidSaldoSesi: strtoupper((string) $valid['UuidSaldoSesi']),
            uuidProduk: strtoupper((string) $valid['UuidProduk']),
            jumlah: (int) $valid['Jumlah'],
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
        ));
    }
}
