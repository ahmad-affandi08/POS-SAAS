<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Rilis;

use App\Domain\Pengelola\Rilis\Aksi\UbahAturanFlagFitur;
use App\Domain\Pengelola\Rilis\Kueri\DaftarFlagFitur;
use App\Domain\Tenant\Enum\CakupanFlagFitur;
use App\Domain\Tenant\Model\FlagFitur;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Flag fitur & kill switch (P-10): lihat `rilis.lihat`, ubah `flag-fitur.kelola` dengan alasan (BR-P10.3). */
final class FlagFiturKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarFlagFitur $kueri): Response
    {
        return Inertia::render('Pengelola/Rilis/FlagFitur', $kueri->Ambil());
    }

    public function Simpan(Request $permintaan, UbahAturanFlagFitur $ubah): RedirectResponse
    {
        $permintaan->validate([
            'Kunci' => ['required', 'string', 'max:100'],
            'Cakupan' => ['required', Rule::enum(CakupanFlagFitur::class)],
            'Objek' => ['nullable', 'string', 'size:26'],
            'Nilai' => ['boolean'],
            'Persen' => ['nullable', 'integer', 'min:0', 'max:100'],
            'Alasan' => ['required', 'string', 'max:500'],
        ], [
            'Kunci.*' => 'Isi kunci flag.',
            'Cakupan.*' => 'Pilih cakupan.',
            'Objek.*' => 'Pilih paket atau tenant.',
            'Nilai.*' => 'Nilai flag tidak valid.',
            'Persen.*' => 'Persen tenant 0 sampai 100.',
            'Alasan.*' => 'Tulis alasan perubahan flag.',
        ]);
        $flag = $ubah->Simpan(
            $this->AmbilPelaku(),
            trim($permintaan->string('Kunci')->toString()),
            CakupanFlagFitur::from($permintaan->string('Cakupan')->toString()),
            $permintaan->filled('Objek') ? $permintaan->string('Objek')->toString() : null,
            $permintaan->boolean('Nilai'),
            $permintaan->filled('Persen') ? $permintaan->integer('Persen') : null,
            $permintaan->string('Alasan')->toString(),
        );

        return back()->with('Kilat', "Flag {$flag->Kunci} disimpan.");
    }

    public function Hapus(FlagFitur $flagFitur, Request $permintaan, UbahAturanFlagFitur $ubah): RedirectResponse
    {
        $permintaan->validate(['Alasan' => ['required', 'string', 'max:500']], ['Alasan.*' => 'Tulis alasan menghapus aturan flag.']);
        $ubah->Hapus($this->AmbilPelaku(), $flagFitur, $permintaan->string('Alasan')->toString());

        return back()->with('Kilat', "Aturan flag {$flagFitur->Kunci} dihapus.");
    }
}
