<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Data\SumberFitur;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\OverrideTenant;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Carbon;

/**
 * Merakit masukan EvaluatorFitur dari data satu tenant (P-04 BR-P04.3, P-07): fitur & batas paket langganan, lalu
 * override pengelola yang masih berlaku. Override yang `BerakhirPada`-nya sudah lewat diabaikan (berakhir otomatis).
 * Bila ada lebih dari satu override aktif untuk kunci yang sama, baris terbaru yang menang.
 *
 * Modul outlet (F-01, BR-01.3) diisi bila outlet disebut: kunci `OutletFitur` aktif outlet itu, atau null (tanpa
 * batasan) bila outlet belum punya baris sama sekali. Flag fitur (P-10) dari `FlagFiturTenant`; add-on (F-19) belum
 * tersedia.
 */
final class SumberFiturTenant
{
    public function __construct(
        private readonly FiturOutlet $fiturOutlet,
        private readonly FlagFiturTenant $flagFitur,
    ) {}

    /**
     * @param  bool  $kunci  kunci baris langganan (FOR UPDATE) agar penambahan outlet/pengguna bersamaan dari satu
     *                       tenant diproses berurutan (F-02, BR-P04.3); hanya di dalam transaksi.
     * @param  int|null  $idOutlet  outlet yang modulnya ikut dievaluasi (F-01); null = tanpa batasan modul outlet
     */
    public function Ambil(int $idTenant, ?Carbon $pada = null, bool $kunci = false, ?int $idOutlet = null): SumberFitur
    {
        $langganan = Langganan::query()
            ->with('Paket.Fitur')
            ->where('IdTenant', $idTenant)
            ->when($kunci, fn ($kueri) => $kueri->lockForUpdate())
            ->first();
        $paket = $langganan?->Paket;

        $override = $this->AmbilOverrideAktif($idTenant, $pada);
        $overrideFitur = [];
        $overrideBatas = [];

        foreach ($override as $baris) {
            if ($baris->Jenis === JenisOverride::Fitur) {
                $overrideFitur[] = $baris->Kunci;
            } elseif ($baris->Jenis === JenisOverride::Batas && in_array($baris->Kunci, Paket::KOLOM_BATAS, true)) {
                $overrideBatas[$baris->Kunci] = $baris->Nilai === null ? null : (int) $baris->Nilai;
            }
        }

        return new SumberFitur(
            fiturPaket: $paket?->AmbilKunciFitur() ?? [],
            batasPaket: $paket?->AmbilBatas() ?? array_fill_keys(Paket::KOLOM_BATAS, 0),
            overrideFitur: array_values(array_unique($overrideFitur)),
            overrideBatas: $overrideBatas,
            modulOutletAktif: $idOutlet === null ? null : $this->fiturOutlet->AmbilKunciAktifAtauNull($idTenant, $idOutlet),
            flagFitur: $this->flagFitur->Ambil($idTenant, $paket?->Id),
        );
    }

    /**
     * Override batas & fitur yang masih berlaku, terlama dulu (sehingga yang terbaru menimpa).
     *
     * @return list<OverrideTenant>
     */
    public function AmbilOverrideAktif(int $idTenant, ?Carbon $pada = null): array
    {
        return array_values(OverrideTenant::query()
            ->where('IdTenant', $idTenant)
            ->whereIn('Jenis', [JenisOverride::Batas->value, JenisOverride::Fitur->value])
            ->where('BerakhirPada', '>', $pada ?? now())
            ->orderBy('Id')
            ->get()
            ->all());
    }

    public function AmbilNamaPaket(int $idTenant): ?string
    {
        $langganan = Langganan::query()->where('IdTenant', $idTenant)->with('Paket')->first();

        return $langganan?->Paket->Nama;
    }
}
