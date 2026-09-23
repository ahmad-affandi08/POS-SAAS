<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Autentikasi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\PemilikTenant;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Aksi\SetujuiDokumenLegal;
use App\Domain\Tenant\Kueri\PersetujuanLegalTertunda;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Autentikasi\SetujuiDokumenLegalPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Persetujuan ulang versi materiil dokumen legal oleh Owner saat membuka back-office (BR-P06.5).
 */
final class PersetujuanLegalKontroler extends Kontroler
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemilikTenant $pemilik,
        private readonly PersetujuanLegalTertunda $tertunda,
    ) {}

    public function Tampilkan(Request $permintaan): Response|RedirectResponse
    {
        $dokumen = $this->AmbilTertunda($permintaan);

        if ($dokumen === []) {
            return redirect()->route('kelola.beranda');
        }

        return Inertia::render('Autentikasi/PersetujuanLegal', [
            'Dokumen' => array_map(fn (DokumenLegal $baris): array => [
                'Uuid' => $baris->Uuid,
                'Label' => $baris->Jenis->AmbilLabel(),
                'Judul' => $baris->Judul,
                'Versi' => $baris->Versi,
                'BerlakuMulai' => $baris->BerlakuMulai->toDateString(),
                'RingkasanPerubahan' => $baris->RingkasanPerubahan,
                'Tautan' => route('legal.tampil', ['jenis' => Str::kebab($baris->Jenis->value), 'versi' => $baris->Versi]),
            ], $dokumen),
        ]);
    }

    public function Setujui(SetujuiDokumenLegalPermintaan $permintaan, SetujuiDokumenLegal $setujui): RedirectResponse
    {
        $pengguna = $permintaan->user('web');
        $idTenant = $this->konteks->Ambil();
        abort_unless($pengguna instanceof Pengguna && $idTenant !== null && $this->pemilik->CekPemilik($pengguna->Id, $idTenant), 403);

        $setujui->Jalankan($idTenant, $pengguna->Id, $permintaan->AmbilUuidDokumen(), $permintaan->ip());

        return redirect()->intended(route('kelola.beranda'))->with('Kilat', 'Terima kasih. Persetujuan Anda sudah tercatat.');
    }

    /**
     * @return list<DokumenLegal>
     */
    private function AmbilTertunda(Request $permintaan): array
    {
        $pengguna = $permintaan->user('web');
        $idTenant = $this->konteks->Ambil();

        if (! $pengguna instanceof Pengguna || $idTenant === null || ! $this->pemilik->CekPemilik($pengguna->Id, $idTenant)) {
            return [];
        }

        return $this->tertunda->Ambil($idTenant, $pengguna->Id, now());
    }
}
