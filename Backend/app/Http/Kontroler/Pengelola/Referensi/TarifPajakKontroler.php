<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\AjukanTarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\SimpanDrafTarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\TinjauTarifPajak;
use App\Domain\Pengelola\Referensi\Kueri\DaftarTarifPajak;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanTarifPajakPermintaan;
use App\Http\Permintaan\Pengelola\Referensi\TinjauDataMasterPermintaan;
use App\Http\Respons\DaftarBerhalaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tarif pajak master bertanggal dengan persetujuan four-eyes (P-02, BR-P02.1, BR-P02.2).
 */
final class TarifPajakKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan, DaftarTarifPajak $kueri): Response
    {
        $status = StatusDataMaster::tryFrom($permintaan->string('status')->toString());

        return Inertia::render('Pengelola/Referensi/TarifPajak', [
            'Tarif' => DaftarBerhalaman::Buat($kueri->Cari($status), fn (TarifPajak $tarif) => $kueri->Petakan($tarif)),
            'JenisPajak' => $kueri->AmbilJenisPajak(),
            'Saring' => ['Status' => $status?->value],
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
            StatusDataMaster::MenungguTinjauan => 'Persetujuan dicatat. Masih menunggu penyetuju lain.',
        });
    }
}
