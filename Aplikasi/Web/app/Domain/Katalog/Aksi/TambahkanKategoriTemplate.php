<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Katalog\Model\Kategori;
use Illuminate\Support\Facades\DB;

/**
 * F-01 (BR-01.1): kategori template sektor menjadi kategori akar tenant. Aditif & idempoten: nama yang sudah ada di
 * akar (tanpa beda huruf besar/kecil & spasi tepi) dilewati; kategori yang ada tidak diubah atau dihapus.
 * Pemanggil sudah memegang kunci Tenant.
 */
final class TambahkanKategoriTemplate
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  list<string>  $nama
     * @return list<string> nama kategori yang ditambahkan
     */
    public function Jalankan(array $nama): array
    {
        return DB::transaction(function () use ($nama): array {
            $akar = Kategori::query()->whereNull('IdInduk')->get(['Nama', 'Urutan']);
            $ada = array_map(fn (mixed $satu): string => mb_strtolower(trim((string) $satu)), $akar->pluck('Nama')->all());
            $urutan = (int) $akar->max('Urutan');
            $ditambahkan = [];

            foreach ($nama as $satu) {
                $bersih = trim($satu);

                if ($bersih === '' || in_array(mb_strtolower($bersih), $ada, true)) {
                    continue;
                }

                Kategori::query()->create(['Nama' => $bersih, 'IdInduk' => null, 'Urutan' => ++$urutan]);
                $ada[] = mb_strtolower($bersih);
                $ditambahkan[] = $bersih;
            }

            if ($ditambahkan !== []) {
                $this->audit->Catat('kategori.tambah-template', nilaiBaru: ['Nama' => $ditambahkan]);
            }

            return $ditambahkan;
        });
    }
}
