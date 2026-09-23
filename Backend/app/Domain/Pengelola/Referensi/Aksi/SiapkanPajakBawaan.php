<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;

/**
 * Jenis pajak bawaan (PRD §12.1) dan DRAF tarif PPN default. Idempoten.
 *
 * Draf PPN (12% dengan DPP nilai lain 11/12) wajib diverifikasi konsultan pajak lalu diajukan & disetujui
 * lewat alur four-eyes sebelum terbit (§12, BR-P02.2). Tidak ada tarif yang langsung terbit dari seeder.
 */
final class SiapkanPajakBawaan
{
    public function Jalankan(): void
    {
        $ppn = JenisPajak::query()->firstOrCreate(['Kode' => 'Ppn'], ['Nama' => 'PPN', 'Cakupan' => CakupanPajak::Nasional]);
        JenisPajak::query()->firstOrCreate(
            ['Kode' => 'PbjtMakananMinuman'],
            ['Nama' => 'PBJT makanan & minuman (PB1)', 'Cakupan' => CakupanPajak::Daerah],
        );

        if (TarifPajak::query()->where('IdJenisPajak', $ppn->Id)->exists()) {
            return;
        }

        TarifPajak::query()->create([
            'IdJenisPajak' => $ppn->Id,
            'Tarif' => '12',
            'PengaliDppPembilang' => 11,
            'PengaliDppPenyebut' => 12,
            'BerlakuMulai' => '2025-01-01',
            'Status' => StatusDataMaster::Draf,
            'NomorDasarHukum' => 'PMK 131 Tahun 2024 (verifikasi konsultan pajak sebelum diajukan)',
        ]);
    }
}
