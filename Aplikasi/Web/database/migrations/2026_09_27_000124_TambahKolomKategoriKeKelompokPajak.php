<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-03 §12.2: kategori pajak produk di `KelompokPajak` (enum `KategoriPajakProduk`). Nullable (expand);
 * `SimpanKelompokPajak` mewajibkannya. Kelompok yang sudah ada diisi dari detailnya: ada `Ppn` → KenaPpn; selain itu
 * ada `PbjtMakananMinuman` → KenaPbjt; tanpa detail → NonPajak; sisanya → Lainnya. Tidak ada angka tarif disalin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('KelompokPajak', function (Blueprint $tabel): void {
            $tabel->string('Kategori', 20)->nullable()->after('Nama');
        });

        $this->IsiKategori();
    }

    public function down(): void
    {
        Schema::table('KelompokPajak', function (Blueprint $tabel): void {
            $tabel->dropColumn('Kategori');
        });
    }

    /** Mengisi `Kategori` yang masih kosong (lintas tenant, hanya query builder). Aman dijalankan ulang. */
    public function IsiKategori(): void
    {
        $punyaJenis = fn (string $kode): Closure => function (Builder $kueri) use ($kode): void {
            $kueri->selectRaw('1')
                ->from('KelompokPajakDetail')
                ->join('JenisPajak', 'JenisPajak.Id', '=', 'KelompokPajakDetail.IdJenisPajak')
                ->whereColumn('KelompokPajakDetail.IdKelompokPajak', 'KelompokPajak.Id')
                ->where('JenisPajak.Kode', $kode);
        };
        $punyaDetail = function (Builder $kueri): void {
            $kueri->selectRaw('1')->from('KelompokPajakDetail')->whereColumn('KelompokPajakDetail.IdKelompokPajak', 'KelompokPajak.Id');
        };

        DB::table('KelompokPajak')->whereNull('Kategori')->whereExists($punyaJenis('Ppn'))->update(['Kategori' => 'KenaPpn']);
        DB::table('KelompokPajak')->whereNull('Kategori')->whereExists($punyaJenis('PbjtMakananMinuman'))->update(['Kategori' => 'KenaPbjt']);
        DB::table('KelompokPajak')->whereNull('Kategori')->whereNotExists($punyaDetail)->update(['Kategori' => 'NonPajak']);
        DB::table('KelompokPajak')->whereNull('Kategori')->update(['Kategori' => 'Lainnya']);
    }
};
