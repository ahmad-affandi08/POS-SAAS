<?php

declare(strict_types=1);

namespace App\Domain\Promo\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Penjualan\Enum\ModeResolusiPromo;
use App\Domain\Penjualan\Kalkulasi\DefinisiPromo;
use App\Domain\Promo\Enum\StatusPromo;
use App\Domain\Promo\Model\PengaturanPromo;
use App\Domain\Promo\Model\Promo;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Carbon\CarbonImmutable;

/**
 * Kueri publik domain Promo (F-16c): promo aktif tenant untuk POS (`GET /api/pos/v1/promo`) dan untuk validasi ulang
 * penjualan di server, beserta mode resolusi konflik. Tenant tanpa fitur paket `promo.mesin` = tanpa promo.
 */
final class PromoBerlaku
{
    public const KUNCI_FITUR = 'promo.mesin';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    public function CekFiturAktif(): bool
    {
        return $this->fitur->CekAktif($this->konteks->Wajib(), self::KUNCI_FITUR);
    }

    public function AmbilMode(): ModeResolusiPromo
    {
        return PengaturanPromo::query()->first()->ModeResolusi ?? ModeResolusiPromo::Terbaik;
    }

    /**
     * Promo aktif yang belum berakhir pada [sekarang] (untuk disimpan perangkat).
     *
     * @return list<array{Uuid: string, Kode: string, Nama: string, Prioritas: int, Eksklusif: bool, MulaiPada: string|null, SelesaiPada: string|null, KuotaTersisa: int|null, Definisi: array<string, mixed>}>
     */
    public function AmbilUntukPos(CarbonImmutable $sekarang): array
    {
        if (! $this->CekFiturAktif()) {
            return [];
        }

        return array_values(Promo::query()
            ->where('Status', StatusPromo::Aktif->value)
            ->where(fn ($k) => $k->whereNull('SelesaiPada')->orWhere('SelesaiPada', '>', $sekarang))
            ->orderByDesc('Prioritas')
            ->orderBy('Kode')
            ->get()
            ->map(fn (Promo $p): array => [
                'Uuid' => $p->Uuid,
                'Kode' => $p->Kode,
                'Nama' => $p->Nama,
                'Prioritas' => $p->Prioritas,
                'Eksklusif' => $p->Eksklusif,
                'MulaiPada' => $p->MulaiPada?->toIso8601ZuluString(),
                'SelesaiPada' => $p->SelesaiPada?->toIso8601ZuluString(),
                'KuotaTersisa' => $p->AmbilKuotaTersisa(),
                'Definisi' => $p->Definisi,
            ])->all());
    }

    /**
     * Semua promo aktif sebagai definisi mesin (kuota tersisa terkini); dipakai `TerimaPenjualanPos`.
     *
     * @return list<DefinisiPromo>
     */
    public function AmbilDefinisi(): array
    {
        if (! $this->CekFiturAktif()) {
            return [];
        }

        return array_values(Promo::query()->where('Status', StatusPromo::Aktif->value)->get()
            ->map(fn (Promo $p): DefinisiPromo => DefinisiPromo::Urai(
                $p->Uuid,
                $p->Kode,
                $p->Definisi,
                $p->Prioritas,
                $p->Eksklusif,
                $p->MulaiPada === null ? null : CarbonImmutable::instance($p->MulaiPada),
                $p->SelesaiPada === null ? null : CarbonImmutable::instance($p->SelesaiPada),
                $p->AmbilKuotaTersisa(),
            ))->all());
    }
}
