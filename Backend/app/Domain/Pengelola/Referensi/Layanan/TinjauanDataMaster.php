<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Aturan four-eyes bersama untuk data master bertanggal (BR-P02.2):
 * - penyusun (siapa pun yang pernah membuat, mengubah, atau mengajukan) tidak meninjau datanya sendiri;
 * - satu orang satu keputusan per putaran (juga dijaga indeks unik di database);
 * - hanya keputusan pada putaran yang sedang berjalan yang dihitung. Putaran naik setiap kali diajukan,
 *   sehingga persetujuan dari putaran yang ditolak tidak pernah terbawa.
 */
final class TinjauanDataMaster
{
    /**
     * Menambahkan pelaku ke daftar penyusun data (tanpa duplikat).
     *
     * @param  list<int>|null  $daftar
     * @return list<int>
     */
    public static function TambahPenyusun(?array $daftar, int $idPelaku): array
    {
        $daftar ??= [];

        return in_array($idPelaku, $daftar, true) ? $daftar : [...$daftar, $idPelaku];
    }

    /**
     * Menggabungkan penyusun dan pengaju beberapa baris data menjadi daftar yang tidak boleh meninjau.
     *
     * @param  iterable<array{0: list<int>|null, 1: int|null}>  $daftar  pasangan [DaftarIdPenyusun, IdPengaju]
     * @return list<int>
     */
    public static function GabungTerlarang(iterable $daftar): array
    {
        $hasil = [];

        foreach ($daftar as [$penyusun, $pengaju]) {
            foreach ([...($penyusun ?? []), ...($pengaju === null ? [] : [$pengaju])] as $id) {
                $hasil[$id] = $id;
            }
        }

        return array_values($hasil);
    }

    /**
     * @param  list<int>  $idPenyusun  semua penyusun & pengaju data yang ditinjau
     * @return int jumlah persetujuan pada putaran ini
     */
    public function CatatKeputusan(
        string $jenisData,
        int $idData,
        int $putaran,
        array $idPenyusun,
        PenggunaPengelola $peninjau,
        KeputusanTinjauan $keputusan,
        ?string $catatan,
    ): int {
        if (in_array($peninjau->Id, $idPenyusun, true)) {
            throw new PelanggaranAturanBisnis(
                'BR-P02.2',
                'Anda ikut menyusun atau mengajukan data ini, jadi tidak boleh meninjaunya. Minta anggota lain meninjau.',
            );
        }

        if ($keputusan === KeputusanTinjauan::Tolak && ($catatan === null || trim($catatan) === '')) {
            throw new PelanggaranAturanBisnis('CatatanWajib', 'Tulis alasan penolakan agar pengaju bisa memperbaiki.', 'Catatan');
        }

        $pesanSudah = 'Anda sudah memberi keputusan untuk pengajuan ini. Persetujuan berikutnya harus dari anggota lain.';
        $kueriPutaran = fn () => PersetujuanDataMaster::query()
            ->where('JenisData', $jenisData)
            ->where('IdData', $idData)
            ->where('Putaran', $putaran);

        if ($kueriPutaran()->where('IdPenggunaPengelola', $peninjau->Id)->exists()) {
            throw new PelanggaranAturanBisnis('BR-P02.2', $pesanSudah);
        }

        try {
            PersetujuanDataMaster::query()->create([
                'JenisData' => $jenisData,
                'IdData' => $idData,
                'Putaran' => $putaran,
                'IdPenggunaPengelola' => $peninjau->Id,
                'Keputusan' => $keputusan,
                'Catatan' => $catatan,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('BR-P02.2', $pesanSudah);
        }

        return $kueriPutaran()->where('Keputusan', KeputusanTinjauan::Setuju->value)->count();
    }
}
