<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Laporan\Kueri\DasborAplikasiPemilik;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Http\Permintaan\Pemilik\V1\DasborPermintaan;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/pemilik/v1/dasbor?tanggal=&outlet=` (OWN-02, izin `laporan.penjualan.lihat`): omzet, laba kotor (hanya
 * dengan izin `laporan.keuangan.lihat`), transaksi, rata-rata, pembanding kemarin & minggu lalu, per outlet, per jam,
 * produk teratas, dan hal yang perlu ditindaklanjuti. Dibatasi outlet akses pengguna.
 */
final class DasborKontroler extends DasarPemilikKontroler
{
    public function Tampilkan(DasborPermintaan $permintaan, DasborAplikasiPemilik $dasbor): JsonResponse
    {
        $idOutletBoleh = $this->IdOutletBoleh($permintaan);
        $idOutlet = $this->SaringOutlet($permintaan->query('outlet') === null ? null : (string) $permintaan->string('outlet'), $idOutletBoleh);
        $hariIni = $this->TanggalHariIni($idOutlet);
        $tanggal = $this->BacaTanggal($permintaan->query('tanggal') === null ? null : (string) $permintaan->string('tanggal'), $idOutlet);

        return response()->json($dasbor->Ambil(
            $tanggal,
            $idOutletBoleh,
            $idOutlet,
            $this->CekIzin($permintaan, IzinTenant::LaporanKeuanganLihat),
            $this->CekIzin($permintaan, IzinTenant::PersediaanLihat),
            $tanggal->toDateString() === $hariIni->toDateString(),
        ));
    }
}
