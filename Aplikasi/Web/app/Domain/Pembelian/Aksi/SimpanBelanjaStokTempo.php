<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pembelian\Data\DataBelanjaStok;
use App\Domain\Pembelian\Data\DataFakturPembelian;
use App\Domain\Pembelian\Data\DataPenerimaanBarang;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use Illuminate\Support\Facades\DB;

/**
 * Audit kemudahan pakai #20 (F-04): belanja stok "Bayar nanti (tempo X hari)". Satu simpan = penerimaan barang tanpa
 * PO (`TerimaBarang`) + faktur pembelian belum dibayar (`SimpanFakturPembelian`) dalam satu transaksi, memakai alur &
 * jurnal yang sama dengan penerimaan + faktur biasa (J-04.1/J-04.2), sehingga hutangnya muncul di halaman Hutang dan
 * dilunasi lewat pembayaran hutang biasa. Pemasok wajib; nomor nota opsional (kosong = nomor penerimaan). Termin
 * 0–365 hari menentukan jatuh tempo dari tanggal belanja.
 */
final class SimpanBelanjaStokTempo
{
    public const MAKS_TERMIN = 365;

    public function __construct(
        private readonly TerimaBarang $terima,
        private readonly SimpanFakturPembelian $faktur,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PemasokWajib, TerminTidakValid, dan semua galat penerimaan/faktur
     */
    public function Jalankan(DataBelanjaStok $data, int $terminHari): PenerimaanBarang
    {
        if ($data->uuidPemasok === null) {
            throw new PelanggaranAturanBisnis('PemasokWajib', 'Pilih pemasok untuk belanja yang dibayar nanti (hutang dicatat atas pemasok).', 'UuidPemasok');
        }

        if ($terminHari < 0 || $terminHari > self::MAKS_TERMIN) {
            throw new PelanggaranAturanBisnis('TerminTidakValid', 'Tempo pembayaran 0–'.self::MAKS_TERMIN.' hari.', 'TerminHari');
        }

        return DB::transaction(function () use ($data, $terminHari): PenerimaanBarang {
            $grn = $this->terima->Jalankan(new DataPenerimaanBarang(
                null,
                $data->uuidPemasok,
                $data->idGudang,
                $data->tanggal,
                $data->nomorNota,
                $data->ongkir,
                $data->catatan,
                $data->baris,
                $data->lampiran,
                $data->idPengguna,
            ));
            $nomorNota = trim((string) $data->nomorNota);

            $this->faktur->Jalankan(new DataFakturPembelian(
                $data->uuidPemasok,
                $nomorNota !== '' ? $nomorNota : $grn->Nomor,
                $data->tanggal,
                $data->tanggal->addDays($terminHari),
                [$grn->Uuid],
                [],
                null,
                $data->catatan,
                null,
                $data->idPengguna,
            ));

            return $grn->refresh();
        }, 3);
    }
}
