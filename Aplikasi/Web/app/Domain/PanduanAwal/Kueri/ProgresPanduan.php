<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Kueri;

use App\Domain\Organisasi\Data\DataOutletRingkas;
use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\PanduanAwal\Enum\LangkahPanduan;
use App\Domain\PanduanAwal\Enum\StatusLangkahPanduan;
use App\Domain\PanduanAwal\Model\ProgresPanduanAwal;

/**
 * Progres wizard panduan awal tenant aktif (F-01). Outlet wizard = outlet progres bila masih aktif, selain itu outlet
 * aktif pertama (Outlet Utama dari F-00).
 */
final class ProgresPanduan
{
    public function __construct(private readonly OutletUtama $outletUtama) {}

    public function AmbilBaris(): ?ProgresPanduanAwal
    {
        return ProgresPanduanAwal::query()->first();
    }

    public function AmbilOutlet(): ?DataOutletRingkas
    {
        return $this->outletUtama->CariAktifRingkas($this->AmbilBaris()?->IdOutlet)
            ?? $this->outletUtama->CariAktifRingkas($this->outletUtama->AmbilId());
    }

    public function AmbilStatus(LangkahPanduan $langkah): StatusLangkahPanduan
    {
        return $this->AmbilBaris()?->AmbilStatus($langkah) ?? StatusLangkahPanduan::Belum;
    }

    /** D-24: tenant baru yang panduan awalnya belum selesai (back-office dialihkan ke panduan). */
    public function CekWajibBelumSelesai(): bool
    {
        $baris = $this->AmbilBaris();

        return $baris !== null && $baris->Wajib && $baris->SelesaiPada === null;
    }

    /**
     * Bentuk `ProgresPanduan` (kontrak frontend §E).
     *
     * @return array{Langkah: list<array{Kunci: string, Slug: string, Judul: string, Status: string, Tautan: string}>, SelesaiPada: string|null, Outlet: array{Uuid: string, Kode: string, Nama: string}|null, Wajib: bool, WajibBelumSelesai: list<string>}
     */
    public function Ambil(): array
    {
        $baris = $this->AmbilBaris();
        $outlet = $this->AmbilOutlet();

        return [
            'Langkah' => array_map(fn (LangkahPanduan $langkah): array => [
                'Kunci' => $langkah->value,
                'Slug' => $langkah->AmbilSlug(),
                'Judul' => $langkah->AmbilJudul(),
                'Status' => ($baris?->AmbilStatus($langkah) ?? StatusLangkahPanduan::Belum)->value,
                'Tautan' => route($langkah->AmbilNamaRute()),
            ], LangkahPanduan::cases()),
            'SelesaiPada' => $baris?->SelesaiPada?->toIso8601ZuluString(),
            'Outlet' => $outlet === null ? null : ['Uuid' => $outlet->uuid, 'Kode' => $outlet->kode, 'Nama' => $outlet->nama],
            'Wajib' => $baris !== null && $baris->Wajib && $baris->SelesaiPada === null,
            'WajibBelumSelesai' => array_map(fn (LangkahPanduan $l): string => $l->value, $baris?->AmbilLangkahWajibBelumSelesai() ?? []),
        ];
    }
}
