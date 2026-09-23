<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-03 BR-03.1 (data saja): produk lama tanpa SKU (produk awal F-01) diberi SKU `PRD-` + nomor 6 digit per tenant,
 * urut `Produk.Id`, lalu `NomorUrutKatalog(Jenis='Sku')` diisi nomor terakhir. SKU yang sudah terpakai dilewati.
 * Hanya query builder (bukan model) agar tidak bergantung pada kode domain yang bisa berubah.
 */
return new class extends Migration
{
    private const AWALAN = 'PRD-';

    public function up(): void
    {
        $sekarang = now();
        $daftarTenant = DB::table('Produk')->whereNull('Sku')->distinct()->orderBy('IdTenant')->pluck('IdTenant');

        foreach ($daftarTenant as $idTenant) {
            $nomor = (int) DB::table('NomorUrutKatalog')->where('IdTenant', $idTenant)->where('Jenis', 'Sku')->value('NomorTerakhir');
            $skuAda = [];

            foreach (DB::table('Produk')->where('IdTenant', $idTenant)->whereNotNull('Sku')->pluck('Sku') as $sku) {
                $skuAda[mb_strtolower((string) $sku)] = true;
            }

            foreach (DB::table('Produk')->where('IdTenant', $idTenant)->whereNull('Sku')->orderBy('Id')->pluck('Id') as $idProduk) {
                do {
                    $nomor++;
                    $sku = self::AWALAN.str_pad((string) $nomor, 6, '0', STR_PAD_LEFT);
                } while (isset($skuAda[mb_strtolower($sku)]));

                $skuAda[mb_strtolower($sku)] = true;
                DB::table('Produk')->where('Id', $idProduk)->update(['Sku' => $sku, 'DiubahPada' => $sekarang]);
            }

            DB::table('NomorUrutKatalog')->upsert(
                [['IdTenant' => $idTenant, 'Jenis' => 'Sku', 'NomorTerakhir' => $nomor, 'DibuatPada' => $sekarang, 'DiubahPada' => $sekarang]],
                ['IdTenant', 'Jenis'],
                ['NomorTerakhir', 'DiubahPada'],
            );
        }
    }

    public function down(): void
    {
        // Data saja: SKU yang sudah diberikan tetap dipakai (tidak bisa dibedakan dari SKU buatan pengguna).
    }
};
