<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Enum\StatusBahanTerbuang;
use App\Domain\Persediaan\Kueri\MutasiDokumen;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use App\Domain\Persediaan\Layanan\PenyusunJurnalPersediaan;
use App\Domain\Persediaan\Model\BahanTerbuang;
use App\Domain\Persediaan\Model\MutasiStok;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * F-05f: membatalkan catatan bahan terbuang yang salah (alasan 5–255 karakter): bahan kembali masuk pada nilai asal
 * (mutasi pembalik) dan jurnal pembalik di tanggal bisnis hari ini. Idempoten. Audit `bahan-terbuang.batalkan`.
 */
final class BatalkanBahanTerbuang
{
    public function __construct(
        private readonly MutasiDokumen $mutasiDokumen,
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoGudang $infoGudang,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly PenyusunJurnalPersediaan $penyusunJurnal,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(BahanTerbuang $catatan, string $alasan, int $idPengguna): BahanTerbuang
    {
        $alasan = trim($alasan);
        $panjang = mb_strlen($alasan);

        if ($panjang < 5 || $panjang > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan pembatalan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($catatan, $alasan, $idPengguna): BahanTerbuang {
            $catatan = BahanTerbuang::query()->whereKey($catatan->Id)->lockForUpdate()->firstOrFail();

            if ($catatan->Status === StatusBahanTerbuang::Dibatalkan) {
                return $catatan;
            }

            $asal = array_values(array_filter(
                $this->mutasiDokumen->Ambil(JenisReferensiMutasi::BahanTerbuang, $catatan->Id),
                fn (MutasiStok $m): bool => ! str_starts_with($m->KunciBaris, 'X/'),
            ));
            $tanggal = $this->tanggalBisnis->Hitung($catatan->IdOutlet);
            $nomor = 'BT-'.$catatan->Uuid;
            $idJurnal = null;

            if ($asal !== []) {
                $balik = $this->catatMutasi->Jalankan(new DataDokumenMutasi(
                    JenisReferensiMutasi::BahanTerbuang,
                    $catatan->Id,
                    $catatan->Uuid,
                    $nomor,
                    $tanggal,
                    $idPengguna,
                    null,
                    array_map(fn (MutasiStok $m): DataBarisMutasi => new DataBarisMutasi(
                        kunciBaris: 'X/'.$m->KunciBaris,
                        idProduk: $m->IdProduk,
                        idGudang: $m->IdGudang,
                        jenisMutasi: $m->JenisMutasi,
                        jumlah: Kuantitas::Dari($m->Jumlah)->Negasi(),
                        modeNilai: ModeNilaiMutasi::Ditentukan,
                        nilai: AritmetikaHpp::AmbilMutlak(Uang::Dari($m->TotalHpp)->Kurangi(Uang::Dari($m->SelisihHpp))),
                        hppSatuan: BigDecimal::of($m->HppSatuan),
                        idMutasiAsal: $m->Id,
                    ), $asal),
                ));
                $baris = $this->penyusunJurnal->Susun(
                    array_values(array_map(fn ($b): array => [$b, PeranAkun::SusutPersediaan], $balik->baris)),
                    $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map(fn (MutasiStok $m): int => $m->IdProduk, $asal))), denganTerhapus: true),
                    $this->infoGudang->AmbilBanyak([$catatan->IdGudang]),
                    $catatan->IdOutlet,
                );

                if ($baris !== []) {
                    $idJurnal = $this->postingJurnal->Jalankan(new DataJurnal(
                        jenisSumber: JenisSumberJurnal::BahanTerbuang,
                        idSumber: $catatan->Id,
                        uuidSumber: $catatan->Uuid,
                        nomorSumber: $nomor,
                        tanggal: $tanggal,
                        keterangan: mb_substr("Pembatalan bahan terbuang {$catatan->NamaProduk}: {$alasan}", 0, 255),
                        baris: $baris,
                        idPengguna: $idPengguna,
                        kunciSumber: 'Pembatalan',
                        idJurnalDibalik: $catatan->IdJurnal,
                    ))->idJurnal;
                }
            }

            $catatan->UbahStatus(StatusBahanTerbuang::Dibatalkan);
            $catatan->forceFill([
                'IdJurnalPembatalan' => $idJurnal,
                'AlasanBatal' => $alasan,
                'DibatalkanOleh' => $idPengguna,
                'DibatalkanPada' => now(),
            ])->save();
            $this->audit->Catat('bahan-terbuang.batalkan', $catatan, ['Status' => StatusBahanTerbuang::Tercatat->value], [
                'Status' => StatusBahanTerbuang::Dibatalkan->value,
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $catatan;
        });
    }
}
