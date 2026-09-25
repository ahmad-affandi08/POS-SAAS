<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Laporan\Kueri\DasborPemilik;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\PanduanAwal\Kueri\LangkahBerikutnya;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda back-office. F-01 menambahkan checklist "Langkah Berikutnya" (kosong = bagian disembunyikan). F-14a:
 * dasbor pemilik (`Dasbor`) hanya untuk pemegang izin `laporan.penjualan.lihat`, dibatasi outlet akses; pengguna tanpa
 * izin melihat beranda tanpa angka (`Dasbor` = null). Wizard panduan awal ada di `PanduanAwalKontroler`.
 */
final class BerandaKelolaKontroler extends DasarKelolaKontroler
{
    public function Beranda(LangkahBerikutnya $langkahBerikutnya, AksesPengguna $akses, DasborPemilik $dasbor, TanggalBisnisOutlet $tanggal): Response
    {
        $idTenant = $this->IdTenant();
        $idPengguna = $this->Pelaku()->Id;

        return Inertia::render('Kelola/Beranda', [
            'LangkahBerikutnya' => $langkahBerikutnya->Ambil($idTenant, $idPengguna),
            'Dasbor' => $akses->CekIzin($idTenant, $idPengguna, IzinTenant::LaporanPenjualanLihat)
                ? $dasbor->Ambil($tanggal->Hitung(null), $this->IdOutletBoleh(), $akses->CekIzin($idTenant, $idPengguna, IzinTenant::PersediaanLihat))
                : null,
        ]);
    }
}
