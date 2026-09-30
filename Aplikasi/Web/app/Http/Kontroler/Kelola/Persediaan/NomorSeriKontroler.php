<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Persediaan\Kueri\RiwayatNomorSeri;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F-05h riwayat nomor seri/IMEI, izin `persediaan.lihat`: cari nomor (`?cari=`, potongan nomor cukup) lalu pilih satu unit
 * (`?unit={uuid}`) untuk melihat riwayatnya dari masuk sampai terjual atau diretur. Nomor yang tidak pernah bergerak di
 * lokasi stok outlet pelaku atau milik tenant lain = tidak ditemukan.
 */
final class NomorSeriKontroler extends DasarPersediaanKontroler
{
    public function Tampilkan(Request $permintaan, RiwayatNomorSeri $riwayat): Response
    {
        $cari = trim($permintaan->string('cari')->toString());
        $uuid = trim($permintaan->string('unit')->toString());
        $detail = $uuid === '' ? null : $riwayat->Ambil($uuid, $this->IdOutletBoleh());

        return Inertia::render('Kelola/Persediaan/NomorSeri', [
            'Saring' => ['Cari' => $cari, 'Unit' => $detail === null ? '' : $uuid],
            'Hasil' => $cari === '' ? [] : $riwayat->Cari($cari, $this->IdOutletBoleh()),
            'Detail' => $detail,
            'BatasHasil' => RiwayatNomorSeri::BATAS_HASIL,
        ]);
    }
}
