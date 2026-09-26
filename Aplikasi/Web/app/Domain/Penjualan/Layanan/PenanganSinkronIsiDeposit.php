<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TerimaIsiDepositPos;
use App\Domain\Penjualan\Data\DataIsiDepositPos;

/**
 * Item outbox `Deposit.Isi` (F-16d bagian 1): isi saldo deposit pelanggan di kasir. Bentuk `Data`: `{UuidPelanggan,
 * Jumlah, UuidMetodePembayaran, Referensi|null, UuidShift, UuidPengguna, Nomor, DibuatPada}`. Uuid item = Uuid dokumen;
 * uang string desimal. Aturan bisnis di `TerimaIsiDepositPos`.
 */
final class PenanganSinkronIsiDeposit implements PenanganItemSinkron
{
    public const JENIS = 'Deposit.Isi';

    public function __construct(private readonly TerimaIsiDepositPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'Jumlah' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'Referensi' => ['sometimes', 'nullable', 'string', 'max:100'],
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'Nomor' => ['required', 'string', 'max:80'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
        ]);
        $referensi = is_string($valid['Referensi'] ?? null) && trim($valid['Referensi']) !== '' ? trim($valid['Referensi']) : null;

        return $this->terima->Jalankan(new DataIsiDepositPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            uuidShift: strtoupper((string) $valid['UuidShift']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            uuidPelanggan: strtoupper((string) $valid['UuidPelanggan']),
            nomor: trim((string) $valid['Nomor']),
            jumlah: Uang::Dari((string) $valid['Jumlah']),
            uuidMetodePembayaran: strtoupper((string) $valid['UuidMetodePembayaran']),
            referensi: $referensi,
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
        ));
    }
}
