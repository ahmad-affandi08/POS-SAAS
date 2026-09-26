<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Model\TinjauanDokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pembantu penyedia Kotak Tindakan (D-23 C) untuk dokumen `PerluTinjauan`: kecualikan yang sudah ditandai dicek,
 * hitung, ambil maksimal `DataButirTindakan::BATAS_RINCIAN` terbaru sebagai rincian, dan saring Uuid saat menandai.
 */
final class PembuatButirTinjauan
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $kueri  dokumen tenant aktif yang `PerluTinjauan` (sudah disaring outlet)
     * @param  callable(TModel): DataRincianTindakan  $petakan
     */
    public static function Buat(
        Builder $kueri,
        string $jenisDokumen,
        string $tabel,
        string $kunci,
        string $modul,
        string $judul,
        string $keterangan,
        string $tautan,
        callable $petakan,
        string $kolomUrut = 'Id',
        TingkatTindakan $tingkat = TingkatTindakan::Penting,
    ): DataButirTindakan {
        TinjauanDokumen::KecualikanDitinjau($kueri, $jenisDokumen, $tabel);
        $jumlah = (clone $kueri)->count();
        $rincian = $jumlah === 0 ? [] : array_values($kueri->orderByDesc("{$tabel}.{$kolomUrut}")->limit(DataButirTindakan::BATAS_RINCIAN)->get()->map($petakan)->all());

        return new DataButirTindakan($kunci, $modul, $tingkat, $judul, $keterangan, $jumlah, $tautan, 'Buka daftar', $rincian, $jenisDokumen);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $kueri  dokumen tenant aktif yang `PerluTinjauan`
     * @param  list<string>  $uuid
     * @return list<string>
     */
    public static function Saring(Builder $kueri, string $tabel, array $uuid): array
    {
        return array_values(array_map('strval', $kueri->whereIn("{$tabel}.Uuid", $uuid)->pluck("{$tabel}.Uuid")->all()));
    }
}
