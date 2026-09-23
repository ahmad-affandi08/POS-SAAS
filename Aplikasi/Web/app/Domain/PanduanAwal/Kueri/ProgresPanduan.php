<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Kueri;

use App\Domain\Organisasi\Kueri\OutletUtama;
use App\Domain\Organisasi\Model\Outlet;
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

    public function AmbilOutlet(): ?Outlet
    {
        return $this->outletUtama->CariAktif($this->AmbilBaris()?->IdOutlet)
            ?? $this->outletUtama->CariAktif($this->outletUtama->AmbilId());
    }

    public function AmbilStatus(LangkahPanduan $langkah): StatusLangkahPanduan
    {
        return $this->AmbilBaris()?->AmbilStatus($langkah) ?? StatusLangkahPanduan::Belum;
    }

    /**
     * Bentuk `ProgresPanduan` (kontrak frontend §E).
     *
     * @return array{Langkah: list<array{Kunci: string, Slug: string, Judul: string, Status: string, Tautan: string}>, SelesaiPada: string|null, Outlet: array{Uuid: string, Kode: string, Nama: string}|null}
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
            'Outlet' => $outlet === null ? null : ['Uuid' => $outlet->Uuid, 'Kode' => $outlet->Kode, 'Nama' => $outlet->Nama],
        ];
    }
}
