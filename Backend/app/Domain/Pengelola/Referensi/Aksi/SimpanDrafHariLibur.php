<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Data\DataHariLibur;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Model\HariLibur;
use Illuminate\Support\Facades\DB;

/**
 * Membuat, mengubah, atau menghapus DRAF hari libur (P-02). Yang sudah diajukan/terbit tidak bisa diubah.
 * Baris diambil ulang dengan kunci di dalam transaksi, sehingga ubah/hapus tidak bisa menyalip penerbitan.
 */
final class SimpanDrafHariLibur
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataHariLibur $data, ?HariLibur $hariLibur = null): HariLibur
    {
        return DB::transaction(function () use ($pelaku, $data, $hariLibur): HariLibur {
            $nilaiLama = null;

            if ($hariLibur !== null) {
                $hariLibur = self::AmbilDrafTerkunci($hariLibur);
                $nilaiLama = self::AmbilNilai($hariLibur);
            }

            $bentrok = HariLibur::query()
                ->whereDate('Tanggal', $data->tanggal->toDateString())
                ->where('Jenis', $data->jenis->value)
                ->where('Status', '!=', StatusDataMaster::Dibatalkan->value)
                ->when($hariLibur !== null, fn ($kueri) => $kueri->whereKeyNot($hariLibur?->Id))
                ->exists();

            if ($bentrok) {
                throw new PelanggaranAturanBisnis('TanggalGanda', 'Sudah ada '.$data->jenis->AmbilLabel().' pada tanggal ini.', 'Tanggal');
            }

            $hariLibur ??= new HariLibur(['Status' => StatusDataMaster::Draf, 'IdPenggunaPengelolaPengaju' => $pelaku->Id]);
            $hariLibur->fill([
                'DaftarIdPenyusun' => TinjauanDataMaster::TambahPenyusun($hariLibur->DaftarIdPenyusun, $pelaku->Id),
                'Tanggal' => $data->tanggal->toDateString(),
                'Nama' => $data->nama,
                'Jenis' => $data->jenis,
                'NomorDasarHukum' => $data->nomorDasarHukum,
            ])->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'referensi.hari-libur.buat-draf' : 'referensi.hari-libur.ubah-draf',
                $hariLibur,
                nilaiLama: $nilaiLama,
                nilaiBaru: self::AmbilNilai($hariLibur),
                idPelaku: $pelaku->Id,
            );

            return $hariLibur;
        });
    }

    public function Hapus(PenggunaPengelola $pelaku, HariLibur $hariLibur): void
    {
        DB::transaction(function () use ($pelaku, $hariLibur): void {
            $hariLibur = self::AmbilDrafTerkunci($hariLibur);
            $this->audit->Catat('referensi.hari-libur.hapus-draf', $hariLibur, nilaiLama: self::AmbilNilai($hariLibur), idPelaku: $pelaku->Id);
            $hariLibur->delete();
        });
    }

    private static function AmbilDrafTerkunci(HariLibur $hariLibur): HariLibur
    {
        $terkunci = HariLibur::query()->lockForUpdate()->findOrFail($hariLibur->Id);

        if ($terkunci->Status !== StatusDataMaster::Draf) {
            throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Hanya hari libur berstatus draf yang bisa diubah atau dihapus.');
        }

        return $terkunci;
    }

    /**
     * @return array{Tanggal: string, Nama: string, Jenis: string, NomorDasarHukum: string|null}
     */
    private static function AmbilNilai(HariLibur $hariLibur): array
    {
        return [
            'Tanggal' => $hariLibur->Tanggal->toDateString(),
            'Nama' => $hariLibur->Nama,
            'Jenis' => $hariLibur->Jenis->value,
            'NomorDasarHukum' => $hariLibur->NomorDasarHukum,
        ];
    }
}
