<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TerimaPesananGrosirPos;

/**
 * Item outbox `PesananGrosir.Buat` (Modul Salesman bagian 1, §9.7, SLS-11): salesman mengambil pesanan grosir di
 * lapangan. Bentuk `Data`: `{UuidPelanggan, UuidPengguna, DibuatPada, Catatan?, UuidKunjungan?, Baris: [{UuidProduk,
 * UuidSatuan? (kosong = satuan dasar), Jumlah (string desimal)}]}`. **Tanpa harga**: harga ditentukan server. Uuid item
 * = Uuid pesanan grosir. Aturan bisnis di `TerimaPesananGrosirPos`.
 *
 * Jenis perangkat tidak dibatasi (Salesman maupun Kasir boleh mengirim): server tidak pernah menegakkan jenis perangkat
 * per item (mode Pelayan pun hanya mengandalkan izin pengguna), dan toko kecil wajar memakai satu HP kasir untuk
 * salesman. Yang menentukan adalah pengguna yang masuk dengan PIN beserta izinnya.
 */
final class PenanganSinkronBuatPesananGrosir implements PenanganItemSinkron
{
    public const JENIS = 'PesananGrosir.Buat';

    /** Sama dengan batas formulir SO back-office (`SimpanPesananGrosirPermintaan::MAKS_BARIS`). */
    public const MAKS_BARIS = 200;

    public function __construct(private readonly TerimaPesananGrosirPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'UuidKunjungan' => ['nullable', 'string', 'ulid'],
            'Baris' => ['required', 'array', 'min:1', 'max:'.self::MAKS_BARIS],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidSatuan' => ['nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/', 'not_regex:/^0+(\.0+)?$/'],
        ]);

        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $valid['Baris']);

        return $this->terima->Jalankan(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidPelanggan: strtoupper((string) $valid['UuidPelanggan']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
            catatan: self::Teks($valid['Catatan'] ?? null),
            baris: array_map(fn (array $b): array => [
                'UuidProduk' => strtoupper((string) $b['UuidProduk']),
                'UuidSatuan' => is_string($b['UuidSatuan'] ?? null) ? strtoupper($b['UuidSatuan']) : null,
                'Jumlah' => Kuantitas::Dari((string) $b['Jumlah']),
            ], $baris),
            uuidKunjungan: is_string($valid['UuidKunjungan'] ?? null) ? strtoupper($valid['UuidKunjungan']) : null,
        );
    }

    private static function Teks(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}
