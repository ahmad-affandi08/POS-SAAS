<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Pembelian;

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pembelian\Model\Pemasok;
use App\Http\Kontroler\Kelola\Persediaan\DasarPersediaanKontroler;
use Illuminate\Database\Eloquent\Model;

/**
 * Bantuan bersama kontroler pembelian F-04 fase 1: lokasi stok & batas outlet (dari `DasarPersediaanKontroler`),
 * dokumen lewat Uuid di dalam scope tenant (dokumen outlet di luar akses pelaku, atau tanpa outlet saat akses dibatasi
 * = 404), pemasok lewat Uuid, hari bisnis, dan prop `Izin` (tipe FE `IzinPembelian`). Izin rute dijaga
 * `WajibIzinTenant`; prop hanya untuk tampilan.
 */
abstract class DasarPembelianKontroler extends DasarPersediaanKontroler
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

    protected function CariPemasok(string $uuid): Pemasok
    {
        return Pemasok::query()->where('Uuid', $uuid)->firstOrFail();
    }

    protected function HariIni(): string
    {
        return app(TanggalBisnisOutlet::class)->Hitung(null)->format('Y-m-d');
    }

    /**
     * @return array{Kelola: bool, Setujui: bool, LihatJurnal: bool, Persediaan: bool}
     */
    protected function AmbilIzinPembelian(): array
    {
        return [
            'Kelola' => $this->CekIzin(IzinTenant::PembelianKelola),
            'Setujui' => $this->CekIzin(IzinTenant::PembelianPoSetujui),
            'LihatJurnal' => $this->CekIzin(IzinTenant::LaporanKeuanganLihat),
            'Persediaan' => $this->CekIzin(IzinTenant::PersediaanLihat),
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
