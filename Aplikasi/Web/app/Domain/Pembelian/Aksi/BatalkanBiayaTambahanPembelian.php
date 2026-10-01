<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pembelian\Model\BiayaTambahanPembelian;
use App\Domain\Persediaan\Aksi\CatatMutasiStok;
use App\Domain\Persediaan\Data\DataBarisMutasi;
use App\Domain\Persediaan\Data\DataDokumenMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use App\Domain\Persediaan\Kueri\MutasiDokumen;
use App\Domain\Persediaan\Kueri\SaldoStokPasangan;
use App\Domain\Persediaan\Layanan\Hpp\AritmetikaHpp;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan biaya tambahan pembelian (v3.41, CLAUDE.md #8): mutasi penilaian ulang dibalik (masuk dulu, lalu keluar)
 * dan jurnalnya dibalik bertanggal hari bisnis. Hanya selama stok produk yang dinilai ulang **belum bergerak** sejak itu
 * (mutasi terakhir masih milik dokumen ini); bila sudah, koreksi lewat jurnal umum (`StokSudahBergerak`). Alasan
 * 5–255 karakter. Audit `biaya-tambahan.batalkan`.
 */
final class BatalkanBiayaTambahanPembelian
{
    public function __construct(
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly MutasiDokumen $mutasiDokumen,
        private readonly SaldoStokPasangan $saldo,
        private readonly CatatMutasiStok $catatMutasi,
        private readonly BalikkanJurnal $balikkan,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AlasanTidakValid, StokSudahBergerak
     */
    public function Jalankan(BiayaTambahanPembelian $biaya, string $alasan, int $idPengguna): BiayaTambahanPembelian
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan pembatalan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($biaya, $alasan, $idPengguna): BiayaTambahanPembelian {
            $terkunci = BiayaTambahanPembelian::query()->whereKey($biaya->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === StatusDokumenTerposting::Dibatalkan) {
                return $terkunci;
            }

            $mutasi = $this->mutasiDokumen->Ambil(JenisReferensiMutasi::BiayaTambahanPembelian, $terkunci->Id);
            $masuk = array_values(array_filter($mutasi, fn (MutasiStok $m): bool => str_starts_with($m->KunciBaris, 'M/')));
            $keluar = array_values(array_filter($mutasi, fn (MutasiStok $m): bool => str_starts_with($m->KunciBaris, 'K/')));
            $terakhir = $this->saldo->AmbilIdMutasiTerakhir(array_map(fn (MutasiStok $m): array => [$m->IdProduk, $m->IdGudang], $masuk));

            foreach ($masuk as $m) {
                if (($terakhir[SaldoStok::BuatKunciPasangan($m->IdProduk, $m->IdGudang)] ?? null) !== $m->Id) {
                    throw new PelanggaranAturanBisnis('StokSudahBergerak', 'Barangnya sudah terjual atau berpindah sejak biaya ini dicatat, jadi nilai stok tidak bisa dikembalikan. Koreksi selisihnya lewat jurnal umum.');
                }
            }

            $tanggal = $this->tanggalBisnis->Hitung($terkunci->IdOutlet);

            foreach ([$masuk, $keluar] as $kelompok) {
                if ($kelompok !== []) {
                    $this->catatMutasi->Jalankan(new DataDokumenMutasi(
                        JenisReferensiMutasi::BiayaTambahanPembelian,
                        $terkunci->Id,
                        $terkunci->Uuid,
                        $terkunci->Nomor,
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
                        ), $kelompok),
                    ));
                }
            }

            $jurnal = $terkunci->IdJurnal === null ? null : $this->balikkan->Jalankan(
                $terkunci->IdJurnal,
                $tanggal,
                mb_substr("Pembatalan biaya tambahan {$terkunci->Nomor}: {$alasan}", 0, 255),
                JenisSumberJurnal::BiayaTambahanPembelian,
                $terkunci->Id,
                'Pembatalan',
                $idPengguna,
            );

            $terkunci->UbahStatus(StatusDokumenTerposting::Dibatalkan);
            $terkunci->forceFill(['IdJurnalPembatalan' => $jurnal?->idJurnal, 'AlasanBatal' => $alasan, 'DibatalkanOleh' => $idPengguna, 'DibatalkanPada' => now()])->save();
            $this->riwayat->Catat(BiayaTambahanPembelian::JENIS_DOKUMEN, $terkunci->Id, StatusDokumenTerposting::Diposting->value, StatusDokumenTerposting::Dibatalkan->value, $idPengguna, $alasan);
            $this->audit->Catat('biaya-tambahan.batalkan', $terkunci, ['Status' => StatusDokumenTerposting::Diposting->value], [
                'Status' => StatusDokumenTerposting::Dibatalkan->value,
                'Nomor' => $terkunci->Nomor,
                'Alasan' => $alasan,
                'NomorJurnalPembatalan' => $jurnal?->nomor,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
