<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Carbon\CarbonInterface;

/**
 * Nomor dokumen pembelian (PRD "Rincian F-04 fase 1"): `PO/{OUTLET}/{YYMM}/{SEQ4}`, `GR/…`, `RB/…` berurut per outlet
 * lokasi stok (lokasi tanpa outlet memakai kode lokasi stok dan penghitung tingkat usaha), `FB/{YYMM}/{SEQ4}` dan
 * `BH/{YYMM}/{SEQ4}` per tenant. Urut tanpa celah lewat `PenomorDokumen` (kunci L7, dipanggil di transaksi dokumen).
 */
final class PenomorPembelian
{
    public function __construct(
        private readonly PenomorDokumen $penomor,
        private readonly PetaUuidOutlet $petaOutlet,
    ) {}

    public function AmbilNomorLokasi(JenisDokumenBernomor $jenis, CarbonInterface $tanggal, DataInfoGudang $gudang): string
    {
        $kode = $gudang->idOutlet === null ? $gudang->kode : ($this->petaOutlet->AmbilKode([$gudang->idOutlet])[$gudang->idOutlet] ?? $gudang->kode);
        $urut = $this->penomor->AmbilBerikutnya($jenis, $tanggal->format('Y-m'), $gudang->idOutlet);

        return sprintf('%s/%s/%s/%s', $jenis->AmbilAwalan(), mb_strtoupper($kode), $tanggal->format('ym'), str_pad((string) $urut, $jenis->AmbilPanjangUrut(), '0', STR_PAD_LEFT));
    }

    public function AmbilNomorTenant(JenisDokumenBernomor $jenis, CarbonInterface $tanggal): string
    {
        $urut = $this->penomor->AmbilBerikutnya($jenis, $tanggal->format('Y-m'));

        return sprintf('%s/%s/%s', $jenis->AmbilAwalan(), $tanggal->format('ym'), str_pad((string) $urut, $jenis->AmbilPanjangUrut(), '0', STR_PAD_LEFT));
    }
}
