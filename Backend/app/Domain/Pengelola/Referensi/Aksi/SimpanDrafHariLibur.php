<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Data\DataHariLibur;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Model\HariLibur;
use Illuminate\Support\Facades\DB;

/**
 * Membuat, mengubah, atau menghapus DRAF hari libur (P-02). Yang sudah diajukan/terbit tidak bisa diubah.
 */
final class SimpanDrafHariLibur
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataHariLibur $data, ?HariLibur $hariLibur = null): HariLibur
    {
        return DB::transaction(function () use ($pelaku, $data, $hariLibur): HariLibur {
            if ($hariLibur !== null) {
                self::PastikanDraf($hariLibur);
            }

            $bentrok = HariLibur::query()
                ->whereDate('Tanggal', $data->tanggal->toDateString())
                ->where('Jenis', $data->jenis->value)
                ->when($hariLibur !== null, fn ($kueri) => $kueri->whereKeyNot($hariLibur?->Id))
                ->exists();

            if ($bentrok) {
                throw new PelanggaranAturanBisnis('TanggalGanda', 'Sudah ada '.$data->jenis->AmbilLabel().' pada tanggal ini.', 'Tanggal');
            }

            $baru = $hariLibur === null;
            $hariLibur ??= new HariLibur(['Status' => StatusDataMaster::Draf, 'IdPenggunaPengelolaPengaju' => $pelaku->Id]);
            $hariLibur->fill([
                'Tanggal' => $data->tanggal->toDateString(),
                'Nama' => $data->nama,
                'Jenis' => $data->jenis,
                'NomorDasarHukum' => $data->nomorDasarHukum,
            ])->save();

            $this->audit->Catat(
                $baru ? 'referensi.hari-libur.buat-draf' : 'referensi.hari-libur.ubah-draf',
                $hariLibur,
                nilaiBaru: ['Tanggal' => $data->tanggal->toDateString(), 'Nama' => $data->nama, 'Jenis' => $data->jenis->value],
                idPelaku: $pelaku->Id,
            );

            return $hariLibur;
        });
    }

    public function Hapus(PenggunaPengelola $pelaku, HariLibur $hariLibur): void
    {
        DB::transaction(function () use ($pelaku, $hariLibur): void {
            self::PastikanDraf($hariLibur);
            $this->audit->Catat(
                'referensi.hari-libur.hapus-draf',
                $hariLibur,
                nilaiLama: ['Tanggal' => $hariLibur->Tanggal->toDateString(), 'Nama' => $hariLibur->Nama],
                idPelaku: $pelaku->Id,
            );
            $hariLibur->delete();
        });
    }

    private static function PastikanDraf(HariLibur $hariLibur): void
    {
        if ($hariLibur->Status !== StatusDataMaster::Draf) {
            throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Hanya hari libur berstatus draf yang bisa diubah atau dihapus.');
        }
    }
}
