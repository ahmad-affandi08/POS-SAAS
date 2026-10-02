<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Mitra;

use App\Domain\Pengelola\Mitra\Aksi\BatalkanKomisiMitra;
use App\Domain\Pengelola\Mitra\Aksi\CatatPencairanKomisi;
use App\Domain\Pengelola\Mitra\Aksi\SimpanMitra;
use App\Domain\Pengelola\Mitra\Kueri\DaftarMitra;
use App\Domain\Tenant\Enum\JenisMitra;
use App\Domain\Tenant\Enum\StatusMitra;
use App\Domain\Tenant\Model\KomisiMitra;
use App\Domain\Tenant\Model\Mitra;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * P-12 Mitra, reseller & referral di konsol: daftar & formulir mitra (`mitra.kelola`), rincian rujukan/komisi/
 * pencairan (`mitra.lihat`), pencairan bulanan (`mitra.pencairan`), dan pembatalan komisi tertunda (`mitra.kelola`).
 */
final class MitraKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarMitra $kueri): Response
    {
        return Inertia::render('Pengelola/Mitra/Daftar', [
            'Mitra' => $kueri->AmbilSemua(),
            'OpsiJenis' => array_map(fn (JenisMitra $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()], JenisMitra::cases()),
        ]);
    }

    public function Tampilkan(Mitra $mitra, DaftarMitra $kueri): Response
    {
        return Inertia::render('Pengelola/Mitra/Tampil', [
            'Mitra' => $kueri->AmbilRincian($mitra),
            'OpsiJenis' => array_map(fn (JenisMitra $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()], JenisMitra::cases()),
        ]);
    }

    public function Simpan(Request $request, SimpanMitra $simpan): RedirectResponse
    {
        $mitra = $simpan->Jalankan($this->AmbilPelaku(), $this->Validasi($request));

        return redirect()->route('pengelola.mitra.tampil', $mitra)->with('Kilat', "Mitra {$mitra->Kode} ditambahkan.");
    }

    public function Ubah(Request $request, Mitra $mitra, SimpanMitra $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $this->Validasi($request), $mitra);

        return back()->with('Kilat', "Mitra {$mitra->Kode} diperbarui.");
    }

    public function Cairkan(Request $request, Mitra $mitra, CatatPencairanKomisi $cairkan): RedirectResponse
    {
        $valid = $request->validate([
            'Periode' => ['required', 'string', 'size:7'],
            'PotonganPajak' => ['required', 'decimal:0,2', 'min:0'],
            'DibayarPada' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'Catatan' => ['nullable', 'string', 'max:255'],
        ]);
        $p = $cairkan->Jalankan($this->AmbilPelaku(), $mitra, (string) $valid['Periode'], (string) $valid['PotonganPajak'], (string) $valid['DibayarPada'], $valid['Catatan'] ?? null);

        return back()->with('Kilat', "Pencairan {$p->Periode} dicatat.");
    }

    public function BatalkanKomisi(Request $request, KomisiMitra $komisi, BatalkanKomisiMitra $batalkan): RedirectResponse
    {
        $valid = $request->validate(['Alasan' => ['required', 'string', 'min:5', 'max:255']]);
        $batalkan->Jalankan($this->AmbilPelaku(), $komisi, (string) $valid['Alasan']);

        return back()->with('Kilat', "Komisi tagihan {$komisi->NomorTagihan} dibatalkan.");
    }

    /** @return array<string, mixed> */
    private function Validasi(Request $request): array
    {
        return $request->validate([
            'Kode' => ['required', 'string', 'max:20'],
            'Nama' => ['required', 'string', 'max:120'],
            'Jenis' => ['required', Rule::enum(JenisMitra::class)],
            'Status' => ['required', Rule::enum(StatusMitra::class)],
            'Email' => ['nullable', 'email', 'max:191'],
            'NoHp' => ['nullable', 'string', 'max:30'],
            'Npwp' => ['nullable', 'string', 'max:30'],
            'NamaBank' => ['nullable', 'string', 'max:100'],
            'NomorRekening' => ['nullable', 'string', 'max:40'],
            'NamaPemilikRekening' => ['nullable', 'string', 'max:120'],
            'PersenKomisi' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'KomisiBerulang' => ['required', 'boolean'],
            'Catatan' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
