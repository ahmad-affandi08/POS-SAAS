<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Kueri;

use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisPengumuman;
use App\Domain\Tenant\Enum\PlatformPengumuman;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\PengumumanPlatform;

/** Pengumuman platform (P-10 PGL-19) untuk halaman konsol beserta pilihan jenis, paket, sektor, dan platform. */
final class DaftarPengumumanPlatform
{
    /**
     * @return array{Pengumuman: list<array<string, mixed>>, OpsiJenis: list<array{Nilai: string, Label: string}>, OpsiPaket: list<array{Nilai: string, Label: string}>, OpsiSektor: list<array{Nilai: string, Label: string}>, OpsiPlatform: list<array{Nilai: string, Label: string}>}
     */
    public function Ambil(): array
    {
        $daftar = PengumumanPlatform::query()->orderByDesc('Id')->limit(200)->get();
        $nama = PenggunaPengelola::query()->whereIn('Id', $daftar->pluck('DiterbitkanOleh')->merge($daftar->pluck('DibuatOleh'))->filter()->unique()->all())->pluck('Nama', 'Id');

        return [
            'Pengumuman' => array_values($daftar->map(fn (PengumumanPlatform $p): array => [
                'Uuid' => $p->Uuid,
                'Judul' => $p->Judul,
                'Isi' => $p->Isi,
                'Jenis' => $p->Jenis->value,
                'LabelJenis' => $p->Jenis->AmbilLabel(),
                'Sasaran' => [
                    'KodePaket' => $p->Sasaran['KodePaket'] ?? [],
                    'Sektor' => $p->Sasaran['Sektor'] ?? [],
                    'Platform' => $p->Sasaran['Platform'] ?? [],
                    'VersiMinimal' => $p->Sasaran['VersiMinimal'] ?? null,
                    'VersiMaksimal' => $p->Sasaran['VersiMaksimal'] ?? null,
                ],
                'Tautan' => $p->Tautan,
                'TampilMulai' => $p->TampilMulai->toIso8601ZuluString(),
                'TampilSampai' => $p->TampilSampai->toIso8601ZuluString(),
                'PemeliharaanMulai' => $p->PemeliharaanMulai?->toIso8601ZuluString(),
                'PemeliharaanSelesai' => $p->PemeliharaanSelesai?->toIso8601ZuluString(),
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'DiterbitkanPada' => $p->DiterbitkanPada?->toIso8601ZuluString(),
                'DiterbitkanOleh' => $p->DiterbitkanOleh === null ? null : ($nama[$p->DiterbitkanOleh] ?? null),
                'AlasanCabut' => $p->AlasanCabut,
            ])->all()),
            'OpsiJenis' => array_map(fn (JenisPengumuman $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()], JenisPengumuman::cases()),
            'OpsiPaket' => array_values(Paket::query()->orderBy('Urutan')->get(['Kode', 'Nama'])->map(fn (Paket $p): array => ['Nilai' => $p->Kode, 'Label' => $p->Nama])->all()),
            'OpsiSektor' => array_values(TemplateSektor::query()->orderBy('Nama')->get(['Kode', 'Nama'])->map(fn (TemplateSektor $t): array => ['Nilai' => $t->Kode, 'Label' => $t->Nama])->all()),
            'OpsiPlatform' => array_map(fn (PlatformPengumuman $p): array => ['Nilai' => $p->value, 'Label' => $p->AmbilLabel()], PlatformPengumuman::cases()),
        ];
    }
}
