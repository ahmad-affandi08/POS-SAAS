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
 * Add-on (F-19), flag fitur (P-10), dan modul outlet (F-02) belum tersedia sehingga masih kosong.
 */
final class SumberFiturTenant
{
    public function Ambil(int $idTenant, ?Carbon $pada = null): SumberFitur
    {
        $langganan = Langganan::query()->with('Paket.Fitur')->where('IdTenant', $idTenant)->first();
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
}
