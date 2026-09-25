<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Penjualan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Kueri\DaftarPenjualan;
use App\Domain\Penjualan\Kueri\DaftarVoidRetur;
use App\Domain\Penjualan\Kueri\DetailPenjualan;
use App\Domain\Penjualan\Kueri\DetailReturPenjualan;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman penjualan back-office (baca saja, F-07b), izin `laporan.penjualan.lihat`: daftar & detail penjualan, serta
 * (F-09) daftar Void & Retur dan detail retur. Dokumen tenant lain atau di outlet di luar akses pelaku = 404.
 */
final class PenjualanKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarPenjualan $daftar, PetaUuidOutlet $outlet): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPenjualan::KOLOM_URUT, DaftarPenjualan::URUT_BAWAAN, DaftarPenjualan::KOLOM_SARING);

        return ResponsTabel::Kirim(
            $permintaan,
            'Kelola/Penjualan/Daftar',
            'Penjualan',
            fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh(), $this->IdTenant()),
            fn (): array => [
                'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
                'OpsiStatus' => array_map(fn (StatusPenjualan $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusPenjualan::cases()),
                'OpsiKanal' => array_map(fn (KanalPenjualan $k): array => ['Nilai' => $k->value, 'Label' => $k->AmbilLabel()], KanalPenjualan::cases()),
            ],
        );
    }

    public function Detail(string $penjualan, DetailPenjualan $detail): Response
    {
        $props = $detail->Ambil($penjualan, $this->IdOutletBoleh());
        abort_if($props === null, 404);

        return Inertia::render('Kelola/Penjualan/Detail', $props);
    }

    public function VoidRetur(Request $permintaan, DaftarVoidRetur $daftar, PetaUuidOutlet $outlet): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarVoidRetur::KOLOM_URUT, DaftarVoidRetur::URUT_BAWAAN, DaftarVoidRetur::KOLOM_SARING);

        return ResponsTabel::Kirim(
            $permintaan,
            'Kelola/Penjualan/VoidRetur',
            'VoidRetur',
            fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh(), $this->IdTenant()),
            fn (): array => [
                'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
            ],
        );
    }

    public function DetailRetur(string $retur, DetailReturPenjualan $detail): Response
    {
        $props = $detail->Ambil($retur, $this->IdOutletBoleh());
        abort_if($props === null, 404);

        return Inertia::render('Kelola/Penjualan/Retur', $props);
    }
}
