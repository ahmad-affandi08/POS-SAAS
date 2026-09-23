<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Kueri\DokumenLegalBerlaku;
use App\Http\Kontroler\Kontroler;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman publik dokumen legal versi yang berlaku (P-06, F-00 langkah 1), misal `/legal/syarat-ketentuan`.
 */
final class DokumenLegalPublikKontroler extends Kontroler
{
    public function Tampilkan(string $jenis, DokumenLegalBerlaku $berlaku): Response
    {
        $jenisDokumen = collect(JenisDokumenLegal::cases())->first(fn (JenisDokumenLegal $kasus) => Str::kebab($kasus->value) === $jenis);
        abort_if($jenisDokumen === null, 404);
        $dokumen = $berlaku->Cari($jenisDokumen, now());
        abort_if($dokumen === null, 404);

        return Inertia::render('Publik/DokumenLegal', [
            'Dokumen' => [
                'Label' => $jenisDokumen->AmbilLabel(),
                'Judul' => $dokumen->Judul,
                'Versi' => $dokumen->Versi,
                'BerlakuMulai' => $dokumen->BerlakuMulai->toDateString(),
                'Isi' => $dokumen->Isi,
            ],
        ]);
    }
}
