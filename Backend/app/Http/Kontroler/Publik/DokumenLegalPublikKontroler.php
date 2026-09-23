<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Kueri\DokumenLegalBerlaku;
use App\Http\Kontroler\Kontroler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman publik dokumen legal versi yang berlaku (P-06, F-00 langkah 1), misal `/legal/syarat-ketentuan`.
 * `?versi=N` menampilkan versi terbit tertentu, termasuk yang terjadwal selama masa pengumuman (BR-P06.5).
 */
final class DokumenLegalPublikKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan, string $jenis, DokumenLegalBerlaku $berlaku): Response
    {
        $jenisDokumen = collect(JenisDokumenLegal::cases())->first(fn (JenisDokumenLegal $kasus) => Str::kebab($kasus->value) === $jenis);
        abort_if($jenisDokumen === null, 404);
        $versi = $permintaan->integer('versi');
        $dokumen = $versi > 0 ? $berlaku->CariVersiTerbit($jenisDokumen, $versi) : $berlaku->Cari($jenisDokumen, now());
        abort_if($dokumen === null, 404);

        return Inertia::render('Publik/DokumenLegal', [
            'Dokumen' => [
                'Label' => $jenisDokumen->AmbilLabel(),
                'Judul' => $dokumen->Judul,
                'Versi' => $dokumen->Versi,
                'BerlakuMulai' => $dokumen->BerlakuMulai->toDateString(),
                'Isi' => $dokumen->Isi,
                'Terjadwal' => $dokumen->BerlakuMulai->toDateString() > now('Asia/Jakarta')->toDateString(),
            ],
        ]);
    }
}
