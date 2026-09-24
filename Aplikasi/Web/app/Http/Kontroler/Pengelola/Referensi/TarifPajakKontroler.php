<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\AjukanTarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\SimpanDrafTarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\TinjauTarifPajak;
use App\Domain\Pengelola\Referensi\Kueri\DaftarTarifPajak;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanTarifPajakPermintaan;
use App\Http\Permintaan\Pengelola\Referensi\TinjauDataMasterPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Tarif pajak master bertanggal dengan persetujuan four-eyes (P-02, BR-P02.1, BR-P02.2).
 */
final class TarifPajakKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan, DaftarTarifPajak $kueri): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarTarifPajak::KOLOM_URUT, DaftarTarifPajak::URUT_BAWAAN, DaftarTarifPajak::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Pengelola/Referensi/TarifPajak', 'Tarif', fn (): array => $kueri->AmbilTabel($tabel), fn (): array => [
            'JenisPajak' => $kueri->AmbilJenisPajak(),
            'IdPengguna' => $this->AmbilPelaku()->Id,
        ]);
    }

    public function Simpan(SimpanTarifPajakPermintaan $permintaan, SimpanDrafTarifPajak $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', 'Draf tarif pajak disimpan. Ajukan untuk ditinjau bila sudah lengkap.');
    }

    public function Ubah(TarifPajak $tarifPajak, SimpanTarifPajakPermintaan $permintaan, SimpanDrafTarifPajak $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $tarifPajak);

        return back()->with('Kilat', 'Draf tarif pajak diperbarui.');
    }

    public function Ajukan(TarifPajak $tarifPajak, AjukanTarifPajak $ajukan): RedirectResponse
    {
        $ajukan->Jalankan($this->AmbilPelaku(), $tarifPajak);

        return back()->with('Kilat', 'Tarif pajak diajukan dan menunggu tinjauan.');
    }

    public function Tinjau(TarifPajak $tarifPajak, TinjauDataMasterPermintaan $permintaan, TinjauTarifPajak $tinjau): RedirectResponse
    {
        $status = $tinjau->Jalankan($this->AmbilPelaku(), $tarifPajak, $permintaan->AmbilKeputusan(), $permintaan->AmbilCatatan());

        return back()->with('Kilat', match ($status) {
            StatusDataMaster::Terbit => 'Tarif pajak terbit.',
            StatusDataMaster::Draf => 'Tarif pajak ditolak dan dikembalikan ke draf.',
            // Dibatalkan hanya dipakai hari libur (BR-P02.6); tarif tidak pernah berakhir di status itu.
            StatusDataMaster::MenungguTinjauan, StatusDataMaster::Dibatalkan => 'Persetujuan dicatat. Masih menunggu penyetuju lain.',
        });
    }
}
