<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Katalog;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Katalog\Aksi\AjukanHargaPaket;
use App\Domain\Pengelola\Katalog\Aksi\SimpanDrafHargaPaket;
use App\Domain\Pengelola\Katalog\Aksi\TinjauHargaPaket;
use App\Domain\Pengelola\Katalog\Kueri\DaftarHargaPaket;
use App\Domain\Pengelola\Katalog\Kueri\DaftarKatalog;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Katalog\SimpanHargaPaketPermintaan;
use App\Http\Permintaan\Pengelola\Referensi\TinjauDataMasterPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Versi harga paket dengan four-eyes & grandfathering (P-04, BR-P04.1, BR-P04.5).
 */
final class HargaPaketKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Paket $paket, DaftarHargaPaket $kueri): Response
    {
        return Inertia::render('Pengelola/Katalog/HargaPaket', [
            'Paket' => DaftarKatalog::PetakanPaket($paket),
            'Harga' => $kueri->AmbilUntukPaket($paket),
            'IdPengguna' => $this->AmbilPelaku()->Id,
        ]);
    }

    public function Simpan(Paket $paket, SimpanHargaPaketPermintaan $permintaan, SimpanDrafHargaPaket $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $paket, $permintaan->AmbilData());

        return back()->with('Kilat', 'Draf harga disimpan. Ajukan untuk ditinjau Super Admin.');
    }

    public function Ubah(Paket $paket, HargaPaket $hargaPaket, SimpanHargaPaketPermintaan $permintaan, SimpanDrafHargaPaket $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $paket, $permintaan->AmbilData(), $hargaPaket);

        return back()->with('Kilat', 'Draf harga diperbarui.');
    }

    public function Ajukan(Paket $paket, HargaPaket $hargaPaket, AjukanHargaPaket $ajukan): RedirectResponse
    {
        abort_unless($hargaPaket->IdPaket === $paket->Id, 404);
        $ajukan->Jalankan($this->AmbilPelaku(), $hargaPaket);

        return back()->with('Kilat', 'Harga diajukan dan menunggu tinjauan.');
    }

    public function Tinjau(Paket $paket, HargaPaket $hargaPaket, TinjauDataMasterPermintaan $permintaan, TinjauHargaPaket $tinjau): RedirectResponse
    {
        abort_unless($hargaPaket->IdPaket === $paket->Id, 404);
        $status = $tinjau->Jalankan($this->AmbilPelaku(), $hargaPaket, $permintaan->AmbilKeputusan(), $permintaan->AmbilCatatan());

        return back()->with('Kilat', match ($status) {
            StatusDataMaster::Terbit => 'Harga terbit. Berlaku untuk tagihan berikutnya sesuai tanggal berlaku.',
            StatusDataMaster::Draf => 'Harga ditolak dan dikembalikan ke draf.',
            StatusDataMaster::MenungguTinjauan, StatusDataMaster::Dibatalkan => 'Persetujuan dicatat.',
        });
    }
}
