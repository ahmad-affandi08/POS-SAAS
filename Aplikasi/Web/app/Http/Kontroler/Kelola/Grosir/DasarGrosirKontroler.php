<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Grosir;

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Http\Kontroler\Kelola\Persediaan\DasarPersediaanKontroler;
use Illuminate\Database\Eloquent\Model;

/**
 * Bantuan bersama kontroler grosir (F-12, §9.7): dokumen lewat Uuid di dalam scope tenant dengan batas outlet pelaku
 * (dokumen di outlet di luar akses = 404), lokasi stok (dari `DasarPersediaanKontroler`, dipakai surat jalan), hari
 * bisnis, dan prop `Izin` (tipe FE `IzinGrosir`). Izin rute dijaga `WajibIzinTenant`; prop hanya untuk tampilan.
 */
abstract class DasarGrosirKontroler extends DasarPersediaanKontroler
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $kelas
     * @return TModel
     */
    protected function CariDokumen(string $kelas, string $uuid): Model
    {
        $dokumen = $kelas::query()->where('Uuid', $uuid)->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        $idOutlet = $dokumen->getAttribute('IdOutlet');
        abort_if($boleh !== null && (! is_int($idOutlet) || ! in_array($idOutlet, $boleh, true)), 404);

        return $dokumen;
    }

    protected function HariIni(): string
    {
        return app(TanggalBisnisOutlet::class)->Hitung(null)->format('Y-m-d');
    }

    /**
     * @return array{Kelola: bool, SetujuiKredit: bool, LihatJurnal: bool, LihatPiutang: bool}
     */
    protected function AmbilIzinGrosir(): array
    {
        return [
            'Kelola' => $this->CekIzin(IzinTenant::GrosirKelola),
            'SetujuiKredit' => $this->CekIzin(IzinTenant::GrosirSetujuiKredit),
            'LihatJurnal' => $this->CekIzin(IzinTenant::LaporanKeuanganLihat),
            'LihatPiutang' => $this->CekIzin(IzinTenant::PelangganLihat),
        ];
    }

    /**
     * @return list<array{Nilai: string, Label: string}>
     */
    protected static function Opsi(string $enum): array
    {
        /** @var class-string<\BackedEnum> $enum */
        return array_map(fn ($s): array => ['Nilai' => (string) $s->value, 'Label' => method_exists($s, 'AmbilLabel') ? (string) $s->AmbilLabel() : (string) $s->value], $enum::cases());
    }
}
