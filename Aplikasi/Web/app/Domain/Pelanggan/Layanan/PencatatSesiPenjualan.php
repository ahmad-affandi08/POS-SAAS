<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\DefinisiPaketSesi;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Pelanggan untuk domain Penjualan (F-16d bagian 2, paket sesi, J-16.2): saldo sesi dibuat di
 * transaksi DB penerimaan penjualan (idempoten per baris penjualan), dan dibatalkan saat penjualannya di-void.
 * - Pembelian: satu `SaldoSesi` per baris paket, sesi = jumlah × `JumlahSesi` paket, nilai = pendapatan bersih baris
 *   (setelah diskon, tanpa pajak) yang dikredit ke Pendapatan Diterima Dimuka oleh jurnal penjualan.
 * - Void: sisa sesi dibatalkan (jurnal pembalik penjualan sudah mendebit seluruh nilai awal); nilai sesi yang sudah
 *   dipakai diakui balik (Dr Pendapatan Jasa, Cr Pendapatan Diterima Dimuka) agar saldo akun tetap = Σ nilai tersisa.
 *   Void ditolak bila sisa paket sudah dikembalikan atau hangus dari back-office.
 */
final class PencatatSesiPenjualan
{
    public const KUNCI_JURNAL_VOID = 'SesiVoid';

    public function __construct(
        private readonly DefinisiPaketSesi $definisi,
        private readonly BukuSesi $buku,
        private readonly PostingJurnal $posting,
    ) {}

    /**
     * @param  list<int>  $idProduk
     * @return array<int, array{IdPaketSesi: int, JumlahSesi: int, MasaBerlakuHari: int|null, Aktif: bool}>
     */
    public function AmbilPaketPerProduk(array $idProduk): array
    {
        return $this->definisi->AmbilPerProduk($idProduk);
    }

    /**
     * Catat saldo sesi satu baris paket. Hasil: masalah untuk tinjauan (kosong = normal).
     *
     * @param  array{IdPaketSesi: int, JumlahSesi: int, MasaBerlakuHari: int|null, Aktif: bool}  $paket
     * @return list<string>
     */
    public function CatatPembelian(
        array $paket,
        ?int $idPelanggan,
        int $idOutlet,
        int $idPenjualan,
        int $idPenjualanDetail,
        string $nomorPenjualan,
        string $namaPaket,
        int $jumlahPaket,
        Uang $nilai,
        CarbonImmutable $tanggal,
        int $idPengguna,
    ): array {
        if (SaldoSesi::query()->where('IdPenjualanDetail', $idPenjualanDetail)->exists()) {
            return [];
        }

        $sesi = $jumlahPaket * $paket['JumlahSesi'];
        $saldo = SaldoSesi::query()->create([
            'IdPelanggan' => $idPelanggan,
            'IdPaketSesi' => $paket['IdPaketSesi'],
            'IdOutlet' => $idOutlet,
            'IdPenjualan' => $idPenjualan,
            'IdPenjualanDetail' => $idPenjualanDetail,
            'NomorPenjualan' => mb_substr($nomorPenjualan, 0, 80),
            'NamaPaket' => mb_substr($namaPaket, 0, 200),
            'JumlahSesi' => $sesi,
            'SisaSesi' => 0,
            'NilaiAwal' => $nilai->KeString(),
            'NilaiTersisa' => '0',
            'TanggalBeli' => $tanggal->toDateString(),
            'BerlakuSampai' => $paket['MasaBerlakuHari'] === null ? null : $tanggal->addDays($paket['MasaBerlakuHari'])->toDateString(),
            'Status' => StatusSaldoSesi::Aktif,
        ]);
        $this->buku->Catat($saldo, JenisMutasiSesi::Beli, $sesi, $nilai, SumberMutasiSesi::Penjualan, $idPenjualanDetail, $nomorPenjualan, $tanggal, idPengguna: $idPengguna);

        return array_values(array_filter([
            $idPelanggan === null ? "paket {$namaPaket}: pelanggan belum diterima server, saldo sesi belum bertuan" : null,
            $paket['Aktif'] ? null : "paket {$namaPaket} sudah tidak aktif saat penjualan diterima",
        ]));
    }

    /** Pesan galat bila void penjualan ini tidak boleh (sisa paket sudah direfund/hangus); null = boleh. */
    public function PeriksaBisaVoid(int $idPenjualan): ?string
    {
        $tertutup = SaldoSesi::query()
            ->where('IdPenjualan', $idPenjualan)
            ->whereIn('Status', [StatusSaldoSesi::Hangus->value, StatusSaldoSesi::Dibatalkan->value])
            ->value('NamaPaket');

        return $tertutup === null ? null : "Sisa paket sesi {$tertutup} sudah dikembalikan atau hangus, penjualan ini tidak bisa di-void.";
    }

    public function BatalkanPenjualan(int $idPenjualan, string $uuidPenjualan, string $nomor, int $idOutlet, CarbonImmutable $tanggal, int $idPengguna): void
    {
        $dipakai = Uang::Nol();

        foreach (SaldoSesi::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->get() as $saldo) {
            if ($saldo->Status === StatusSaldoSesi::Dibatalkan) {
                continue;
            }

            $dipakai = $dipakai->Tambah(Uang::Dari($saldo->NilaiAwal)->Kurangi(Uang::Dari($saldo->NilaiTersisa)));
            $this->buku->Catat(
                $saldo,
                JenisMutasiSesi::Batal,
                -$saldo->SisaSesi,
                Uang::Nol()->Kurangi(Uang::Dari($saldo->NilaiTersisa)),
                SumberMutasiSesi::Void,
                $idPenjualan,
                $nomor,
                $tanggal,
                'Void penjualan',
                $idPengguna,
            );
        }

        if ($dipakai->Bandingkan(Uang::Nol()) <= 0) {
            return;
        }

        $this->posting->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::Penjualan,
            idSumber: $idPenjualan,
            uuidSumber: $uuidPenjualan,
            nomorSumber: $nomor,
            tanggal: $tanggal,
            keterangan: mb_substr("Void {$nomor}: pengakuan sesi terpakai dibalik", 0, 255),
            baris: [
                DataBarisJurnal::Debit(PeranAkun::PendapatanJasa, $dipakai, $idOutlet),
                DataBarisJurnal::Kredit(PeranAkun::PendapatanDiterimaDimuka, $dipakai, $idOutlet),
            ],
            idPengguna: $idPengguna,
            kunciSumber: self::KUNCI_JURNAL_VOID,
        ));
    }

    /**
     * Uuid baris penjualan paket sesi (untuk menolak retur baris itu; sisa paket ditutup lewat back-office).
     *
     * @param  list<int>  $idPenjualanDetail
     * @return list<int>
     */
    public function SaringBarisPaket(array $idPenjualanDetail): array
    {
        return $idPenjualanDetail === [] ? [] : array_values(array_map('intval', SaldoSesi::query()->whereIn('IdPenjualanDetail', $idPenjualanDetail)->pluck('IdPenjualanDetail')->all()));
    }
}
