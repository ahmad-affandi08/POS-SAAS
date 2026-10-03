<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\DataBawaan\Aksi\SiapkanDataBawaanLisensi;
use Illuminate\Console\Command;

/**
 * D-35: memuat & menerbitkan data master bawaan rilis (pajak, satuan, wilayah, katalog fitur, template sektor) di
 * server pembeli. Dijalankan otomatis oleh `lisensi:pasang` pertama; jalankan lagi setelah memperbarui aplikasi agar
 * template & fitur baru dari rilis itu ikut aktif.
 */
final class SiapkanDataLisensiPerintah extends Command
{
    protected $signature = 'lisensi:siapkan-data';

    protected $description = 'Memuat dan menerbitkan data master bawaan rilis di edisi Lisensi (D-35).';

    public function handle(SiapkanDataBawaanLisensi $siapkan): int
    {
        try {
            $hasil = $siapkan->Jalankan();
        } catch (PelanggaranAturanBisnis $galat) {
            $this->error($galat->getMessage());

            return self::FAILURE;
        }

        $this->info("Data master siap: {$hasil['TarifTerbit']} tarif pajak & {$hasil['TemplateTerbit']} template sektor baru diterbitkan.");

        if ($hasil['TemplateGagal'] !== []) {
            $this->warn('Template belum lolos validasi (tetap draf): '.implode(', ', $hasil['TemplateGagal']).'.');
        }

        return self::SUCCESS;
    }
}
