<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use Illuminate\Contracts\Container\Container;

/**
 * Item outbox `PesananTerbuka.*` (F-07 mode meja fase 1, PRD "Rincian F-07 mode meja & F-10b fase 1"). Satu kelas
 * penangan per jenis (lihat turunan di bawah); validasi bentuk data di sini, aturan bisnis di Aksi masing-masing.
 * Bentuk `Data` bersama: `UuidPesanan` (Buka: Uuid item = Uuid pesanan), `UuidPengguna`, dan waktu perangkat.
 */
abstract class PenanganSinkronPesananTerbuka implements PenanganItemSinkron
{
    /** Jumlah barang positif, maks. 14 digit bulat & 4 desimal (DECIMAL(18,4)). */
    protected const POLA_JUMLAH = '/^\d{1,14}(\.\d{1,4})?$/';

    public function __construct(protected readonly Container $container) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<mixed>>  $aturan
     * @return array<string, mixed>
     */
    protected static function Validasi(array $data, array $aturan, string $bidangWaktu): array
    {
        return ValidasiItemSinkron::Validasi($data, [
            'UuidPengguna' => ['required', 'string', 'ulid'],
            $bidangWaktu => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            ...$aturan,
        ]);
    }

    /**
     * @param  array<string, mixed>  $valid
     * @param  array<string, mixed>  $tambahan
     */
    protected static function Data(array $valid, string $uuidPesanan, string $bidangWaktu, DataKonteksSinkron $konteks, array $tambahan = []): DataPesananTerbukaPos
    {
        return new DataPesananTerbukaPos(...[
            'idPerangkat' => $konteks->idPerangkat,
            'idOutlet' => $konteks->idOutlet,
            'uuidPesanan' => strtoupper($uuidPesanan),
            'uuidPengguna' => strtoupper((string) $valid['UuidPengguna']),
            'waktu' => ValidasiItemSinkron::AmbilWaktu((string) $valid[$bidangWaktu]),
            ...$tambahan,
        ]);
    }

    protected static function Teks(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }

    protected static function Uuid(mixed $nilai): ?string
    {
        return is_string($nilai) && $nilai !== '' ? strtoupper($nilai) : null;
    }
}
