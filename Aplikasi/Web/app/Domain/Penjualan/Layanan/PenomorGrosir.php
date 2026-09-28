<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Carbon\CarbonInterface;

/**
 * Nomor dokumen grosir (F-12, §9.7): `PG/{OUTLET}/{YYMM}/{SEQ4}`, berurut per outlet dan periode `YYYY-MM`.
 *
 * Cermin `PenomorPembelian::AmbilNomorLokasi`, tetapi berkode **outlet** bukan lokasi stok: SO grosir milik outlet
 * penjual, sedangkan gudang asal barang baru ditentukan per surat jalan. Urut tanpa celah lewat `PenomorDokumen`
 * (dipanggil di dalam transaksi dokumen).
 */
final class PenomorGrosir
{
    public function __construct(
        private readonly PenomorDokumen $penomor,
        private readonly PetaUuidOutlet $petaOutlet,
    ) {}

    public function AmbilNomorOutlet(JenisDokumenBernomor $jenis, CarbonInterface $tanggal, int $idOutlet): string
    {
        $kode = $this->petaOutlet->AmbilKode([$idOutlet])[$idOutlet] ?? (string) $idOutlet;
        $urut = $this->penomor->AmbilBerikutnya($jenis, $tanggal->format('Y-m'), $idOutlet);

        return sprintf(
            '%s/%s/%s/%s',
            $jenis->AmbilAwalan(),
            mb_strtoupper($kode),
            $tanggal->format('ym'),
            str_pad((string) $urut, $jenis->AmbilPanjangUrut(), '0', STR_PAD_LEFT),
        );
    }
}
