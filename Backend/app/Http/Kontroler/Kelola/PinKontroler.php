<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\AturPinSendiri;
use App\Domain\Organisasi\Aksi\AturUlangPinAnggota;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\DaftarPinAnggota;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Http\Permintaan\Kelola\AturPinPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PIN kasir (F-02 langkah 4, §20.2) di `/kelola/keamanan/pin`: setiap anggota mengatur PIN miliknya; pemegang izin
 * `pengguna.pin.atur` (Pemilik, Admin, Manajer Outlet) mengatur ulang PIN anggota lain tanpa bisa melihatnya.
 */
final class PinKontroler extends DasarKelolaKontroler
{
    public function Tampilkan(DaftarPinAnggota $daftar, AksesPengguna $akses): Response
    {
        $idTenant = $this->IdTenant();
        $pelaku = $this->Pelaku();
        $bolehAturUlang = $akses->CekIzin($idTenant, $pelaku->Id, IzinTenant::PenggunaPinAtur);

        return Inertia::render('Kelola/Pin', [
            'PinSayaDiatur' => $daftar->CekPinDiatur($idTenant, $pelaku->Id),
            'Anggota' => $bolehAturUlang ? $daftar->Ambil($idTenant, $pelaku->Id) : null,
        ]);
    }

    public function AturSendiri(AturPinPermintaan $permintaan, AturPinSendiri $atur): RedirectResponse
    {
        $atur->Jalankan($this->IdTenant(), $this->Pelaku()->Id, $permintaan->string('Pin')->toString());

        return back()->with('Kilat', 'PIN kasir Anda disimpan. Pakai PIN ini untuk masuk di aplikasi kasir.');
    }

    public function AturUlangAnggota(string $pengguna, AturPinPermintaan $permintaan, AturUlangPinAnggota $aturUlang): RedirectResponse
    {
        $baris = Pengguna::query()->where('Uuid', $pengguna)->firstOrFail();
        $anggota = TenantPengguna::query()->where('IdTenant', $this->IdTenant())->where('IdPengguna', $baris->Id)->firstOrFail();
        $aturUlang->Jalankan($this->Pelaku()->Id, $anggota, $permintaan->string('Pin')->toString());

        return back()->with('Kilat', "PIN kasir {$baris->Nama} diatur ulang. Beritahukan PIN baru langsung kepadanya.");
    }
}
