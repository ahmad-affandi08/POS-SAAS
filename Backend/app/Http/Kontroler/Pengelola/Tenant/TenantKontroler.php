<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Tenant;

use App\Domain\Pengelola\Katalog\Kueri\DaftarKatalog;
use App\Domain\Pengelola\Tenant\Aksi\BuatOverrideTenant;
use App\Domain\Pengelola\Tenant\Aksi\PerpanjangTrial;
use App\Domain\Pengelola\Tenant\Enum\KategoriPenangguhan;
use App\Domain\Pengelola\Tenant\Kueri\DaftarTenant;
use App\Domain\Pengelola\Tenant\Kueri\TampilanTenant;
use App\Domain\Tenant\Enum\PenandaTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Respons\DaftarBerhalaman;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar tenant & tampilan 360° dasar (P-07). Tindakan pada tenant ada di TindakanTenantKontroler.
 */
final class TenantKontroler extends Kontroler
{
    public function Daftar(Request $permintaan, DaftarTenant $kueri): Response
    {
        $kata = trim($permintaan->string('kata')->toString());
        $status = StatusLangganan::tryFrom($permintaan->string('status')->toString());
        $nilaiPenanda = $permintaan->string('penanda')->toString();
        $penanda = $nilaiPenanda === DaftarTenant::SARING_TANPA_PENANDA ? $nilaiPenanda : PenandaTenant::tryFrom($nilaiPenanda);

        $halaman = $kueri->Cari($kata, $status, $penanda);
        $email = $kueri->AmbilEmailPemilik(array_values(array_map(fn (Tenant $tenant): int => $tenant->Id, $halaman->items())));

        return Inertia::render('Pengelola/Tenant/Daftar', [
            'Tenant' => DaftarBerhalaman::Buat($halaman, fn (Tenant $tenant): array => [
                'Uuid' => $tenant->Uuid,
                'Nama' => $tenant->Nama,
                'Slug' => $tenant->Slug,
                'EmailPemilik' => $email[$tenant->Id] ?? null,
                'KodePaket' => $tenant->Langganan?->Paket->Kode,
                'StatusLangganan' => $tenant->Langganan?->Status->value,
                'TrialBerakhirPada' => $tenant->Langganan?->TrialBerakhirPada?->toIso8601String(),
                'Penanda' => $tenant->Penanda?->value,
                'DibuatPada' => $tenant->DibuatPada->toIso8601String(),
            ]),
            'Saring' => [
                'Kata' => $kata,
                'Status' => $status === null ? '' : $status->value,
                'Penanda' => $penanda instanceof PenandaTenant ? $penanda->value : ($penanda ?? ''),
            ],
            'PilihanStatus' => array_map(fn (StatusLangganan $item) => ['Nilai' => $item->value, 'Label' => $item->AmbilLabel()], StatusLangganan::cases()),
            'PilihanPenanda' => [
                ['Nilai' => DaftarTenant::SARING_TANPA_PENANDA, 'Label' => 'Tanpa penanda'],
                ...array_map(fn (PenandaTenant $item) => ['Nilai' => $item->value, 'Label' => $item->AmbilLabel()], PenandaTenant::cases()),
            ],
        ]);
    }

    public function Tampilkan(Tenant $tenant, TampilanTenant $kueri, DaftarKatalog $katalog): Response
    {
        return Inertia::render('Pengelola/Tenant/Tampil', [
            'Tenant' => $kueri->Ambil($tenant),
            'Pilihan' => [
                'KategoriPenangguhan' => array_map(
                    fn (KategoriPenangguhan $item) => ['Nilai' => $item->value, 'Label' => $item->AmbilLabel()],
                    KategoriPenangguhan::cases(),
                ),
                'Penanda' => array_map(fn (PenandaTenant $item) => ['Nilai' => $item->value, 'Label' => $item->AmbilLabel()], PenandaTenant::cases()),
                'KolomBatas' => Paket::KOLOM_BATAS,
                'Fitur' => array_map(fn (array $fitur) => ['Nilai' => $fitur['Kunci'], 'Label' => "{$fitur['Nama']} ({$fitur['Kunci']})"], $katalog->AmbilFitur()),
            ],
            'Aturan' => [
                'MaksHariTrial' => PerpanjangTrial::MAKS_HARI,
                'MaksKaliTrial' => PerpanjangTrial::MAKS_KALI,
                'MaksHariOverride' => BuatOverrideTenant::MAKS_HARI,
            ],
        ]);
    }
}
