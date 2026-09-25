<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Rilis;

use App\Domain\Pengelola\Rilis\Aksi\AturVersiMinimum;
use App\Domain\Pengelola\Rilis\Aksi\SimpanDrafRilis;
use App\Domain\Pengelola\Rilis\Aksi\UbahStatusRilis;
use App\Domain\Pengelola\Rilis\Kueri\DaftarRilis;
use App\Domain\Pengelola\Rilis\Kueri\DampakVersiMinimum;
use App\Domain\Tenant\Model\RilisAplikasi;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Rilis\SimpanRilisPermintaan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rilis aplikasi (P-10): daftar (izin `rilis.lihat`), catat draf, terbitkan, ubah rollout, hentikan, dan atur versi
 * minimum (izin `rilis.kelola`). Dampak versi minimum (BR-P10.2) diambil terpisah saat dialog dibuka.
 */
final class RilisAplikasiKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarRilis $kueri): Response
    {
        return Inertia::render('Pengelola/Rilis/Daftar', ['Rilis' => $kueri->Ambil()]);
    }

    public function Simpan(SimpanRilisPermintaan $permintaan, SimpanDrafRilis $simpan): RedirectResponse
    {
        $rilis = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Draf rilis {$rilis->Versi} dicatat.");
    }

    public function Ubah(RilisAplikasi $rilis, SimpanRilisPermintaan $permintaan, SimpanDrafRilis $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $rilis);

        return back()->with('Kilat', "Draf rilis {$rilis->Versi} diperbarui.");
    }

    public function Terbitkan(RilisAplikasi $rilis, Request $permintaan, UbahStatusRilis $ubah): RedirectResponse
    {
        $permintaan->validate(['PersenRollout' => ['required', 'integer', 'min:1', 'max:100']], ['PersenRollout.*' => 'Persen rollout 1 sampai 100.']);
        $ubah->Terbitkan($this->AmbilPelaku(), $rilis, $permintaan->integer('PersenRollout'));

        return back()->with('Kilat', "Rilis {$rilis->Versi} diterbitkan ({$rilis->PersenRollout}%).");
    }

    public function UbahRollout(RilisAplikasi $rilis, Request $permintaan, UbahStatusRilis $ubah): RedirectResponse
    {
        $permintaan->validate(['PersenRollout' => ['required', 'integer', 'min:1', 'max:100']], ['PersenRollout.*' => 'Persen rollout 1 sampai 100.']);
        $ubah->UbahRollout($this->AmbilPelaku(), $rilis, $permintaan->integer('PersenRollout'));

        return back()->with('Kilat', "Rollout {$rilis->Versi} menjadi {$rilis->PersenRollout}%.");
    }

    public function Hentikan(RilisAplikasi $rilis, Request $permintaan, UbahStatusRilis $ubah): RedirectResponse
    {
        $permintaan->validate(['Alasan' => ['required', 'string', 'max:500']], ['Alasan.*' => 'Tulis alasan menghentikan rilis.']);
        $ubah->Hentikan($this->AmbilPelaku(), $rilis, $permintaan->string('Alasan')->toString());

        return back()->with('Kilat', "Rollout {$rilis->Versi} dihentikan.");
    }

    public function DampakVersiMinimum(RilisAplikasi $rilis, DampakVersiMinimum $dampak): JsonResponse
    {
        return response()->json($dampak->Hitung($rilis->Platform, $rilis->Versi));
    }

    public function AturVersiMinimum(RilisAplikasi $rilis, Request $permintaan, AturVersiMinimum $atur): RedirectResponse
    {
        $permintaan->validate([
            'BerlakuPada' => ['required', 'date_format:Y-m-d'],
            'PerbaikanKeamanan' => ['boolean'],
            'Alasan' => ['required', 'string', 'max:500'],
        ], [
            'BerlakuPada.*' => 'Isi tanggal mulai berlaku.',
            'PerbaikanKeamanan.*' => 'Penanda perbaikan keamanan tidak valid.',
            'Alasan.*' => 'Tulis alasan menaikkan versi minimum.',
        ]);
        $berlaku = CarbonImmutable::createFromFormat('!Y-m-d', $permintaan->string('BerlakuPada')->toString(), 'Asia/Jakarta') ?: CarbonImmutable::now();
        $atur->Jalankan($this->AmbilPelaku(), $rilis, $berlaku, $permintaan->boolean('PerbaikanKeamanan'), $permintaan->string('Alasan')->toString());

        return back()->with('Kilat', "Versi minimum {$rilis->Platform} menjadi {$rilis->Versi} mulai {$berlaku->translatedFormat('j F Y')}.");
    }

    public function BatalkanVersiMinimum(RilisAplikasi $rilis, AturVersiMinimum $atur): RedirectResponse
    {
        $atur->Batalkan($this->AmbilPelaku(), $rilis);

        return back()->with('Kilat', 'Kenaikan versi minimum dibatalkan.');
    }
}
