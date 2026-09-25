<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TerimaVoidPenjualanPos;
use App\Domain\Penjualan\Data\DataVoidPenjualanPos;

/**
 * Item outbox `Penjualan.Void` (F-09 fase 1, PRD "Rincian F-09 fase 1"): void seluruh penjualan di shift yang sama.
 * Bentuk `Data`: `{UuidPenjualan, UuidPengguna, UuidPenyetuju, Alasan (≥ 5 karakter), DivoidPada}`. Uuid item = Uuid
 * `VoidPenjualan`.
 */
final class PenanganSinkronVoidPenjualan implements PenanganItemSinkron
{
    public const JENIS = 'Penjualan.Void';

    public function __construct(private readonly TerimaVoidPenjualanPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        if (is_string($data['Alasan'] ?? null)) {
            $data['Alasan'] = trim($data['Alasan']);
        }

        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidPenjualan' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'UuidPenyetuju' => ['required', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'min:5', 'max:255'],
            'DivoidPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);

        return $this->terima->Jalankan(new DataVoidPenjualanPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidPenjualan: strtoupper((string) $valid['UuidPenjualan']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            uuidPenyetuju: strtoupper((string) $valid['UuidPenyetuju']),
            alasan: (string) $valid['Alasan'],
            divoidPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DivoidPada']),
        ));
    }
}
