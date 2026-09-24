<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Katalog\Enum\JenisNomorUrutKatalog;
use App\Domain\Katalog\Model\NomorUrutKatalog;

/**
 * Penghitung `NomorUrutKatalog` per tenant aktif (BR-03.1): baris dikunci `FOR UPDATE` lalu dinaikkan di transaksi
 * pemanggil, sehingga dua penyimpanan bersamaan tidak mendapat nomor yang sama. Baris dibuat saat pertama dipakai
 * (pemanggil sudah memegang kunci Tenant, dan indeks unik `UniqNomorUrutKatalogIdTenantJenis` menjadi pengaman).
 */
final class PenghitungNomorUrutKatalog
{
    public function AmbilBerikutnya(JenisNomorUrutKatalog $jenis): int
    {
        $baris = NomorUrutKatalog::query()->where('Jenis', $jenis->value)->lockForUpdate()->first()
            ?? NomorUrutKatalog::query()->create(['Jenis' => $jenis, 'NomorTerakhir' => 0]);

        $baris->NomorTerakhir++;
        $baris->save();

        return $baris->NomorTerakhir;
    }
}
