<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Model\MutasiSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Carbon\CarbonImmutable;

/**
 * Menutup sisa paket sesi (F-16d bagian 2) di transaksi pemanggil, dengan saldo yang sudah dikunci:
 * - `Refund`: sisa nilai dikembalikan ke pelanggan dari akun kas/bank: Dr Pendapatan Diterima Dimuka, Cr kas/bank.
 * - `Hangus`: sisa nilai diakui sebagai pendapatan lain (masa berlaku lewat / dihanguskan): Dr Pendapatan Diterima
 *   Dimuka, Cr Pendapatan Lain.
 * Mutasi (−sisa sesi, −nilai tersisa) menjadi sumber jurnal (`JenisSumberJurnal::MutasiSesi`), dimensi outlet penjualan
 * paket.
 */
final class PenutupSisaSesi
{
    public function __construct(
        private readonly BukuSesi $buku,
        private readonly PostingJurnal $postingJurnal,
    ) {}

    public function Tutup(
        SaldoSesi $saldo,
        JenisMutasiSesi $jenis,
        SumberMutasiSesi $sumber,
        CarbonImmutable $tanggal,
        string $keterangan,
        ?int $idPengguna,
        ?int $idAkunKasBank = null,
    ): MutasiSesi {
        $nilai = Uang::Dari($saldo->NilaiTersisa);
        $mutasi = $this->buku->Catat($saldo, $jenis, -$saldo->SisaSesi, Uang::Nol()->Kurangi($nilai), $sumber, $saldo->Id, $saldo->NomorPenjualan, $tanggal, $keterangan, $idPengguna, $idAkunKasBank);

        if ($mutasi->IdJurnal !== null || $nilai->Bandingkan(Uang::Nol()) <= 0) {
            return $mutasi;
        }

        $kredit = $jenis === JenisMutasiSesi::Refund && $idAkunKasBank !== null
            ? new DataBarisJurnal(null, $idAkunKasBank, $saldo->IdOutlet, Uang::Nol(), $nilai, $keterangan)
            : DataBarisJurnal::Kredit(PeranAkun::PendapatanLain, $nilai, $saldo->IdOutlet, $keterangan);
        $label = $jenis === JenisMutasiSesi::Refund ? 'Pengembalian' : 'Hangus';
        $mutasi->IdJurnal = $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::MutasiSesi,
            idSumber: $mutasi->Id,
            uuidSumber: $mutasi->Uuid,
            nomorSumber: $saldo->NomorPenjualan,
            tanggal: $tanggal,
            keterangan: mb_substr("{$label} sisa paket {$saldo->NamaPaket}", 0, 255),
            baris: [
                DataBarisJurnal::Debit(PeranAkun::PendapatanDiterimaDimuka, $nilai, $saldo->IdOutlet, $keterangan),
                $kredit,
            ],
            idPengguna: $idPengguna,
        ))->idJurnal;
        $mutasi->save();

        return $mutasi;
    }
}
