<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Kueri;

use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\StatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;

/**
 * Isi banner status integrasi di Platform Pengelola: integrasi aktif yang gagal diuji (BR-P05.3) dan kredensial
 * yang lewat masa rotasi (BR-P05.5), untuk lingkungan server ini.
 */
final class PeringatanIntegrasi
{
    /**
     * @return list<string>
     */
    public function Ambil(): array
    {
        $peringatan = [];
        $daftar = KonfigurasiIntegrasi::query()
            ->where('Lingkungan', LingkunganIntegrasi::AmbilSaatIni()->value)
            ->where('Aktif', true)
            ->whereIn('Jenis', array_map(fn (JenisIntegrasi $jenis): string => $jenis->value, JenisIntegrasi::AmbilJenisPlatform()))
            ->orderBy('Jenis')
            ->get(['Id', 'Jenis', 'Status', 'KredensialDiubahPada', 'RotasiSetiapHari']);

        foreach ($daftar as $konfigurasi) {
            $label = $konfigurasi->Jenis->AmbilLabel();

            if ($konfigurasi->Status === StatusIntegrasi::Gagal) {
                $peringatan[] = "{$label} gagal saat diuji. Fitur yang memakainya bisa terganggu.";
            }

            if ($konfigurasi->CekPerluRotasi()) {
                $peringatan[] = "Kredensial {$label} sudah lewat masa rotasi {$konfigurasi->RotasiSetiapHari} hari. Ganti kuncinya.";
            }
        }

        return $peringatan;
    }
}
