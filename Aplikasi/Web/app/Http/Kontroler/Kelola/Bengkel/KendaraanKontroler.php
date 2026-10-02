<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Bengkel;

use App\Domain\Bengkel\Aksi\SimpanKendaraan;
use App\Domain\Bengkel\Kueri\DaftarKendaraan;
use App\Domain\Bengkel\Kueri\DaftarPerintahKerja;
use App\Domain\Bengkel\Model\Kendaraan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Http\Permintaan\Kelola\Bengkel\SimpanKendaraanPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kendaraan pelanggan bengkel (§9.10, `/kelola/bengkel/kendaraan`, izin `bengkel.kelola`): daftar (TabelData), tambah
 * & ubah (dialog), arsipkan, dan detail berisi riwayat servis (perintah kerja + penjualan yang menagihnya).
 * Kendaraan data tenant (bukan per outlet); riwayat servisnya tetap dibatasi outlet akses pelaku.
 */
final class KendaraanKontroler extends DasarBengkelKontroler
{
    public function Daftar(Request $permintaan, DaftarKendaraan $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarKendaraan::KOLOM_URUT, DaftarKendaraan::URUT_BAWAAN, DaftarKendaraan::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Bengkel/Kendaraan/Daftar', 'Kendaraan', fn (): array => $daftar->AmbilTabel($tabel));
    }

    public function Simpan(SimpanKendaraanPermintaan $permintaan, SimpanKendaraan $simpan): RedirectResponse
    {
        $k = $simpan->Jalankan($permintaan->AmbilData(), $this->Pelaku()->Id);

        return back()->with('Kilat', "Kendaraan {$k->NomorPolisi} disimpan.");
    }

    public function Perbarui(SimpanKendaraanPermintaan $permintaan, string $kendaraan, SimpanKendaraan $simpan): RedirectResponse
    {
        $k = $simpan->Jalankan($permintaan->AmbilData(), $this->Pelaku()->Id, $this->CariKendaraan($kendaraan)->Uuid);

        return back()->with('Kilat', "Kendaraan {$k->NomorPolisi} disimpan.");
    }

    /** Riwayat servis per kendaraan: semua perintah kerja (terbaru dulu) beserta penjualan yang menagihnya. */
    public function Detail(string $kendaraan, DaftarKendaraan $daftar, DaftarPerintahKerja $perintahKerja): Response
    {
        $k = $this->CariKendaraan($kendaraan);

        return Inertia::render('Kelola/Bengkel/Kendaraan/Detail', [
            'Kendaraan' => $daftar->Petakan(collect([$k]))[0],
            'Riwayat' => $perintahKerja->RiwayatKendaraan($k->Id, $this->IdOutletBoleh()),
        ]);
    }

    private function CariKendaraan(string $uuid): Kendaraan
    {
        $k = Kendaraan::query()->where('Uuid', strtoupper($uuid))->first();
        abort_if($k === null, 404);

        return $k;
    }
}
