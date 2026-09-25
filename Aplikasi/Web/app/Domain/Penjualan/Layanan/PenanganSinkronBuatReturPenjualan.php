<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TerimaReturPenjualanPos;
use App\Domain\Penjualan\Data\DataBarisReturPenjualanPos;
use App\Domain\Penjualan\Data\DataRefundReturPenjualanPos;
use App\Domain\Penjualan\Data\DataReturPenjualanPos;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;
use Illuminate\Validation\Rule;

/**
 * Item outbox `ReturPenjualan.Buat` (F-09 fase 1, PRD "Rincian F-09 fase 1"). Bentuk `Data`: `{UuidPenjualanAsal,
 * UuidShift, UuidPengguna, UuidPenyetuju, Nomor, Alasan (≥ 5 karakter), DibuatPada, Baris [{Uuid, UuidPenjualanDetail,
 * Jumlah, Kondisi (LayakJual|Rusak)}], Refund [{Uuid, UuidMetodePembayaran, Jumlah}], Ringkasan {TotalRefund}}`. Uuid
 * item = Uuid `ReturPenjualan`. Uang & jumlah string desimal.
 */
final class PenanganSinkronBuatReturPenjualan implements PenanganItemSinkron
{
    public const JENIS = 'ReturPenjualan.Buat';

    /** Jumlah barang positif, maks. 14 digit bulat & 4 desimal (DECIMAL(18,4)). */
    private const POLA_JUMLAH = '/^\d{1,14}(\.\d{1,4})?$/';

    public function __construct(private readonly TerimaReturPenjualanPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        if (is_string($data['Alasan'] ?? null)) {
            $data['Alasan'] = trim($data['Alasan']);
        }

        $uang = 'regex:'.ValidasiItemSinkron::POLA_UANG;
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidPenjualanAsal' => ['required', 'string', 'ulid'],
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'UuidPenyetuju' => ['required', 'string', 'ulid'],
            'Nomor' => ['required', 'string', 'max:80'],
            'Alasan' => ['required', 'string', 'min:5', 'max:255'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'Baris' => ['required', 'array', 'min:1', 'max:500'],
            'Baris.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Baris.*.UuidPenjualanDetail' => ['required', 'string', 'ulid', 'distinct'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:'.self::POLA_JUMLAH],
            'Baris.*.Kondisi' => ['required', 'string', Rule::enum(KondisiBarangRetur::class)],
            'Refund' => ['present', 'array', 'max:5'],
            'Refund.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Refund.*.UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'Refund.*.Jumlah' => ['required', 'string', $uang],
            'Ringkasan' => ['required', 'array'],
            'Ringkasan.TotalRefund' => ['required', 'string', $uang],
        ]);

        return $this->terima->Jalankan(new DataReturPenjualanPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidPenjualanAsal: strtoupper((string) $valid['UuidPenjualanAsal']),
            uuidShift: strtoupper((string) $valid['UuidShift']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            uuidPenyetuju: strtoupper((string) $valid['UuidPenyetuju']),
            nomor: trim((string) $valid['Nomor']),
            alasan: (string) $valid['Alasan'],
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
            baris: self::AmbilBaris((array) $valid['Baris']),
            refund: array_values(array_map(fn (array $r): DataRefundReturPenjualanPos => new DataRefundReturPenjualanPos(
                strtoupper((string) $r['Uuid']),
                strtoupper((string) $r['UuidMetodePembayaran']),
                Uang::Dari((string) $r['Jumlah']),
            ), (array) $valid['Refund'])),
            totalRefund: Uang::Dari((string) $valid['Ringkasan']['TotalRefund']),
        ));
    }

    /**
     * @param  array<int|string, mixed>  $daftar
     * @return list<DataBarisReturPenjualanPos>
     */
    private static function AmbilBaris(array $daftar): array
    {
        $hasil = [];

        foreach (array_values($daftar) as $indeks => $b) {
            if (! is_array($b)) {
                continue;
            }

            $jumlah = Kuantitas::Dari((string) $b['Jumlah']);

            if ($jumlah->KeDesimal()->isZero()) {
                throw new PelanggaranAturanBisnis('DataTidakValid', 'Jumlah retur harus lebih dari 0.', "Baris.{$indeks}.Jumlah");
            }

            $hasil[] = new DataBarisReturPenjualanPos(
                strtoupper((string) $b['Uuid']),
                strtoupper((string) $b['UuidPenjualanDetail']),
                $jumlah,
                KondisiBarangRetur::from((string) $b['Kondisi']),
            );
        }

        return $hasil;
    }
}
