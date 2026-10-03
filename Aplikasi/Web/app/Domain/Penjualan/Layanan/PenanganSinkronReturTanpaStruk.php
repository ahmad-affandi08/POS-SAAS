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
use App\Domain\Penjualan\Aksi\TerimaReturTanpaStrukPos;
use App\Domain\Penjualan\Data\DataBarisReturTanpaStrukPos;
use App\Domain\Penjualan\Data\DataRefundReturPenjualanPos;
use App\Domain\Penjualan\Data\DataReturTanpaStrukPos;
use App\Domain\Penjualan\Enum\KondisiBarangRetur;
use Illuminate\Validation\Rule;

/**
 * Item outbox `ReturPenjualan.TanpaStruk` (K28, PRD v4.01). Bentuk `Data`: `{UuidShift, UuidPengguna, UuidPenyetuju,
 * UuidPelanggan (opsional; wajib untuk refund deposit), Nomor, Alasan (≥ 5 karakter), DibuatPada, Baris [{Uuid,
 * UuidProduk, UuidProdukSatuan (opsional = satuan dasar), Jumlah, Kondisi (LayakJual|Rusak)}], Refund [{Uuid,
 * UuidMetodePembayaran, Jumlah}], Ringkasan {TotalRefund}}`. Uuid item = Uuid `ReturPenjualan`. Uang & jumlah string
 * desimal.
 */
final class PenanganSinkronReturTanpaStruk implements PenanganItemSinkron
{
    public const JENIS = 'ReturPenjualan.TanpaStruk';

    private const POLA_JUMLAH = '/^\d{1,14}(\.\d{1,4})?$/';

    public function __construct(private readonly TerimaReturTanpaStrukPos $terima) {}

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
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'UuidPenyetuju' => ['required', 'string', 'ulid'],
            'UuidPelanggan' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Nomor' => ['required', 'string', 'max:80'],
            'Alasan' => ['required', 'string', 'min:5', 'max:255'],
            'DibuatPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'Baris' => ['required', 'array', 'min:1', 'max:100'],
            'Baris.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['sometimes', 'nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:'.self::POLA_JUMLAH],
            'Baris.*.Kondisi' => ['required', 'string', Rule::enum(KondisiBarangRetur::class)],
            'Refund' => ['required', 'array', 'min:1', 'max:5'],
            'Refund.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Refund.*.UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'Refund.*.Jumlah' => ['required', 'string', $uang],
            'Ringkasan' => ['required', 'array'],
            'Ringkasan.TotalRefund' => ['required', 'string', $uang],
        ]);

        $baris = [];

        foreach (array_values((array) $valid['Baris']) as $indeks => $b) {
            $jumlah = Kuantitas::Dari((string) $b['Jumlah']);

            if ($jumlah->BernilaiNol()) {
                throw new PelanggaranAturanBisnis('DataTidakValid', 'Jumlah retur harus lebih dari 0.', "Baris.{$indeks}.Jumlah");
            }

            $baris[] = new DataBarisReturTanpaStrukPos(
                strtoupper((string) $b['Uuid']),
                strtoupper((string) $b['UuidProduk']),
                is_string($b['UuidProdukSatuan'] ?? null) ? strtoupper($b['UuidProdukSatuan']) : null,
                $jumlah,
                KondisiBarangRetur::from((string) $b['Kondisi']),
            );
        }

        return $this->terima->Jalankan(new DataReturTanpaStrukPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidShift: strtoupper((string) $valid['UuidShift']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            uuidPenyetuju: strtoupper((string) $valid['UuidPenyetuju']),
            uuidPelanggan: is_string($valid['UuidPelanggan'] ?? null) ? strtoupper($valid['UuidPelanggan']) : null,
            nomor: trim((string) $valid['Nomor']),
            alasan: (string) $valid['Alasan'],
            dibuatPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DibuatPada']),
            baris: $baris,
            refund: array_values(array_map(fn (array $r): DataRefundReturPenjualanPos => new DataRefundReturPenjualanPos(
                strtoupper((string) $r['Uuid']),
                strtoupper((string) $r['UuidMetodePembayaran']),
                Uang::Dari((string) $r['Jumlah']),
            ), (array) $valid['Refund'])),
            totalRefund: Uang::Dari((string) $valid['Ringkasan']['TotalRefund']),
        ));
    }
}
