<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tiga template sektor MVP dari file data `database/Data/TemplateSektorAwal.json` (P-03, §5.1). Idempoten: template
 * yang sudah ada tidak diubah. Isi dimuat sebagai DRAF versi 1 yang belum divalidasi; penerbitan tetap lewat
 * validasi otomatis (BR-P03.3) dan izin terbitkan (BR-P03.5).
 */
final class SiapkanTemplateSektorBawaan
{
    public function Jalankan(?string $path = null): void
    {
        $path ??= database_path('Data/TemplateSektorAwal.json');
        $isiFile = is_readable($path) ? file_get_contents($path) : false;
        $data = $isiFile === false ? null : json_decode($isiFile, true);

        if (! is_array($data) || ! is_array($data['Template'] ?? null)) {
            throw new RuntimeException("File data template sektor tidak valid: {$path}");
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['Template'] as $baris) {
                if (! is_array($baris) || ! is_string($baris['Kode'] ?? null) || ! is_string($baris['Nama'] ?? null)
                    || ! is_array($baris['Isi'] ?? null)) {
                    throw new RuntimeException('Setiap template wajib punya Kode, Nama, dan Isi.');
                }

                if (TemplateSektor::query()->where('Kode', $baris['Kode'])->exists()) {
                    continue;
                }

                $template = TemplateSektor::query()->create([
                    'Kode' => $baris['Kode'],
                    'Nama' => $baris['Nama'],
                    'Keterangan' => is_string($baris['Keterangan'] ?? null) ? $baris['Keterangan'] : null,
                ]);

                $template->Versi()->create(['Versi' => 1, 'Status' => StatusTemplateSektor::Draf, 'Isi' => $baris['Isi']]);
            }
        });
    }
}
