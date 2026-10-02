<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Katalog\Data\DataProdukPenjualan;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Penjualan\Data\DataPenjualanPos;
use App\Domain\Penjualan\Model\ResepPenjualan;
use Carbon\CarbonImmutable;

/**
 * Sektor Apotek bagian 1 (PRD §9.5, §19 "Apoteker"): pemeriksaan resep & apoteker pada penjualan POS dan pencatatan
 * `ResepPenjualan`.
 *
 * Aturan (dasar PMK 73/2016 pelayanan resep; UU 35/2009 & PMK 3/2015 psikotropika/narkotika):
 * - baris obat keras (termasuk Obat Wajib Apotek), psikotropika, dan narkotika hanya boleh diserahkan apoteker: kasir
 *   (`X-Id-Kasir`/`UuidPengguna`) atau `UuidApoteker` yang memegang izin `apotek.obat-keras.jual`;
 * - baris wajib resep (keras bukan OWA, psikotropika, narkotika) wajib ditutup blok `Resep`; bila perangkat menandai
 *   baris (`Baris[].DenganResep`), baris wajib resep yang tidak ditandai dianggap tanpa resep;
 * - psikotropika & narkotika juga butuh alamat pasien (catatan penyerahan untuk pelaporan);
 * - tanggal resep tidak boleh setelah tanggal penjualan (kalender lokal outlet saat transaksi).
 *
 * Penjualan bisa terjadi offline, jadi pelanggaran **tidak menolak** penjualan (§18.3): penjualan diterima dan ditandai
 * tinjauan `ResepTidakLengkap` / `ApotekerTidakBerwenang` (aplikasi kasir memblokirnya lebih dulu). Teks tinjauan hanya
 * memuat nama produk, tidak pernah data pasien.
 */
final class PencatatResepPenjualan
{
    public function __construct(private readonly AnggotaOutlet $anggota) {}

    /**
     * @param  array<string, DataProdukPenjualan>  $produk  kunci = Uuid produk
     * @return array{0: DataAnggotaOutlet|null, 1: array<int, bool>, 2: array<string, string>} [apoteker, DenganResep per indeks baris, tinjauan]
     */
    public function Periksa(DataPenjualanPos $data, array $produk, DataAnggotaOutlet $kasir, int $idTenant, int $idOutlet, CarbonImmutable $waktuLokal): array
    {
        $resep = $data->resep;
        $uuidApoteker = $resep !== null && $resep->uuidApoteker !== null ? $resep->uuidApoteker : $data->uuidApoteker;
        $calon = $uuidApoteker === null ? null : $this->anggota->CariDiTenant($idTenant, $uuidApoteker, $idOutlet);
        $apoteker = match (true) {
            $calon !== null && $calon[0]->CekIzin(IzinTenant::ApotekObatKerasJual->value) => $calon[0],
            $kasir->CekIzin(IzinTenant::ApotekObatKerasJual->value) => $kasir,
            default => null,
        };
        $adaTanda = array_filter($data->baris, fn ($b): bool => $b->denganResep !== null) !== [];
        $denganResep = [];
        $butuhApoteker = [];
        $tanpaResep = [];
        $tanpaAlamat = [];

        foreach ($data->baris as $indeks => $baris) {
            $p = $produk[$baris->uuidProduk];
            $golongan = $p->golonganObat;
            $denganResep[$indeks] = $resep !== null && $golongan !== null && ($adaTanda ? $baris->denganResep === true : $golongan->CekWajibResep($p->obatWajibApotek));

            if ($golongan === null) {
                continue;
            }

            if ($golongan->CekWajibApoteker()) {
                $butuhApoteker[$p->nama] = true;
            }

            if ($golongan->CekWajibResep($p->obatWajibApotek) && ! $denganResep[$indeks]) {
                $tanpaResep[$p->nama] = true;
            }

            if ($golongan->CekDilaporkanSipnap() && $resep !== null && $resep->alamatPasien === null) {
                $tanpaAlamat[$p->nama] = true;
            }
        }

        $masalahResep = [];

        if ($tanpaResep !== []) {
            $masalahResep[] = 'tanpa resep untuk '.implode(', ', array_keys($tanpaResep));
        }

        if ($tanpaAlamat !== []) {
            $masalahResep[] = 'alamat pasien wajib untuk '.implode(', ', array_keys($tanpaAlamat));
        }

        if ($resep !== null && $resep->tanggalResep->toDateString() > $waktuLokal->toDateString()) {
            $masalahResep[] = 'tanggal resep setelah tanggal penjualan';
        }

        $tinjauan = [];

        if ($masalahResep !== []) {
            $tinjauan['ResepTidakLengkap'] = 'ResepTidakLengkap: '.implode('; ', $masalahResep);
        }

        if ($butuhApoteker !== [] && $apoteker === null) {
            $tinjauan['ApotekerTidakBerwenang'] = 'ApotekerTidakBerwenang: '.implode(', ', array_keys($butuhApoteker)).' diserahkan tanpa apoteker berizin';
        }

        return [$butuhApoteker === [] ? null : $apoteker, $denganResep, $tinjauan];
    }

    /** Resep dicatat bila perangkat mengirimnya (juga untuk penjualan yang ditinjau). Idempoten per penjualan. */
    public function Catat(DataPenjualanPos $data, int $idPenjualan, int $idOutlet, ?DataAnggotaOutlet $apoteker): void
    {
        $resep = $data->resep;

        if ($resep === null || ResepPenjualan::query()->where('IdPenjualan', $idPenjualan)->exists()) {
            return;
        }

        ResepPenjualan::query()->create([
            'IdPenjualan' => $idPenjualan,
            'IdOutlet' => $idOutlet,
            'NomorResep' => mb_substr($resep->nomorResep, 0, 50),
            'TanggalResep' => $resep->tanggalResep->toDateString(),
            'NamaDokter' => mb_substr($resep->namaDokter, 0, 100),
            'NoSipDokter' => $resep->noSipDokter === null ? null : mb_substr($resep->noSipDokter, 0, 50),
            'NamaPasien' => $resep->namaPasien,
            'UmurPasien' => $resep->umurPasien === null ? null : mb_substr($resep->umurPasien, 0, 20),
            'AlamatPasien' => $resep->alamatPasien,
            'IdApoteker' => $apoteker?->id,
        ]);
    }
}
