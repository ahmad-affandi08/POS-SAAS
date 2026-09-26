<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Kueri;

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;

/**
 * Halaman integrasi (P-05): satu slot per jenis × lingkungan. Kredensial tidak pernah ikut, hanya petunjuknya
 * (BR-P05.1).
 */
final class DaftarIntegrasi
{
    /**
     * @return list<array<string, mixed>>
     */
    public function Ambil(): array
    {
        $tersimpan = KonfigurasiIntegrasi::query()->get()->keyBy(
            fn (KonfigurasiIntegrasi $konfigurasi): string => $konfigurasi->Jenis->value.'|'.$konfigurasi->Lingkungan->value,
        );
        $hasil = [];

        foreach (JenisIntegrasi::cases() as $jenis) {
            foreach (LingkunganIntegrasi::cases() as $lingkungan) {
                $konfigurasi = $tersimpan->get($jenis->value.'|'.$lingkungan->value);
                $penyedia = $konfigurasi instanceof KonfigurasiIntegrasi ? $konfigurasi->Penyedia : $jenis->AmbilPenyedia();
                $hasil[] = [
                    'Jenis' => $jenis->value,
                    'LabelJenis' => $jenis->AmbilLabel(),
                    'Lingkungan' => $lingkungan->value,
                    'LingkunganServer' => $lingkungan === LingkunganIntegrasi::AmbilSaatIni(),
                    'Penyedia' => ['Nilai' => $penyedia->value, 'Label' => $penyedia->AmbilLabel()],
                    'BidangPengaturan' => $penyedia->AmbilBidangPengaturan(),
                    'BidangKredensial' => $penyedia->AmbilBidangKredensial(),
                    // v2.04: katalog penyedia yang bisa dipilih untuk jenis ini.
                    'DaftarPenyedia' => array_map(fn (PenyediaIntegrasi $p): array => [
                        'Nilai' => $p->value,
                        'Label' => $p->AmbilLabel(),
                        'Keterangan' => $p->AmbilKeterangan(),
                        'Resmi' => $p->CekResmi(),
                        'BidangPengaturan' => $p->AmbilBidangPengaturan(),
                        'BidangKredensial' => $p->AmbilBidangKredensial(),
                    ], $jenis->AmbilDaftarPenyedia()),
                    'Konfigurasi' => $konfigurasi instanceof KonfigurasiIntegrasi ? [
                        'Uuid' => $konfigurasi->Uuid,
                        'Pengaturan' => $konfigurasi->Pengaturan,
                        'PetunjukKredensial' => $konfigurasi->PetunjukKredensial,
                        'Aktif' => $konfigurasi->Aktif,
                        'Status' => $konfigurasi->Status->value,
                        'LabelStatus' => $konfigurasi->Status->AmbilLabel(),
                        'TerakhirDiujiPada' => $konfigurasi->TerakhirDiujiPada?->toIso8601String(),
                        'HasilUji' => $konfigurasi->HasilUji,
                        'KredensialDiubahPada' => $konfigurasi->KredensialDiubahPada->toIso8601String(),
                        'RotasiSetiapHari' => $konfigurasi->RotasiSetiapHari,
                        'PerluRotasi' => $konfigurasi->CekPerluRotasi(),
                    ] : null,
                ];
            }
        }

        return $hasil;
    }
}
