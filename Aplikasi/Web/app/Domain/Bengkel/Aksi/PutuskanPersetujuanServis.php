<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Aksi;

use App\Domain\Bengkel\Enum\LewatPersetujuan;
use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Layanan\PenyusunBarisPerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerjaDetail;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan pelanggan atas estimasi servis (§9.10): **setuju sebagian atau semua baris**, atau tolak semuanya.
 *
 * - Lewat tautan (`LewatPersetujuan::Tautan`): hanya saat Menunggu persetujuan dan tautannya masih berlaku; satu tautan
 *   satu keputusan (keputusan kedua ditolak, halaman tetap menampilkan hasilnya). IP disimpan sebagai hash.
 * - Dicatat staf (`LewatPersetujuan::Staf`): pelanggan memutuskan langsung di bengkel atau lewat telepon; boleh dari
 *   Diterima/Diagnosis/Menunggu/Ditolak.
 *
 * Baris yang tidak disetujui tetap tersimpan (riwayat estimasi) tetapi tidak diberikan ke kasir. `TotalDisetujui`
 * dihitung ulang dengan mesin kalkulasi kasir atas baris yang disetujui saja.
 */
final class PutuskanPersetujuanServis
{
    public function __construct(
        private readonly PenyusunBarisPerintahKerja $penyusun,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $uuidBarisDisetujui  diabaikan bila `$setuju` = false
     */
    public function Jalankan(
        string $uuidPerintahKerja,
        bool $setuju,
        array $uuidBarisDisetujui,
        LewatPersetujuan $lewat,
        ?int $idPengguna,
        ?string $hashIp = null,
        ?string $catatan = null,
    ): PerintahKerja {
        return DB::transaction(function () use ($uuidPerintahKerja, $setuju, $uuidBarisDisetujui, $lewat, $idPengguna, $hashIp, $catatan): PerintahKerja {
            $pk = PerintahKerja::query()->where('Uuid', $uuidPerintahKerja)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('PerintahKerjaTidakDikenal', 'Perintah kerja tidak ditemukan.', 'Umum', 404);

            if ($lewat === LewatPersetujuan::Tautan && ($pk->Status !== StatusPerintahKerja::MenungguPersetujuan || ! $pk->CekTautanBerlaku())) {
                throw new PelanggaranAturanBisnis('SudahDiputuskan', 'Estimasi ini sudah diputuskan atau tautannya sudah tidak berlaku. Hubungi bengkel bila ada perubahan.', 'Umum', 409);
            }

            if ($lewat === LewatPersetujuan::Staf && ! $pk->Status->CekBolehDiputuskan()) {
                throw new PelanggaranAturanBisnis('PersetujuanTidakBisaDicatat', "Perintah kerja {$pk->Nomor} berstatus {$pk->Status->AmbilLabel()}.", 'Status');
            }

            $detail = PerintahKerjaDetail::query()->where('IdPerintahKerja', $pk->Id)->orderBy('Urutan')->lockForUpdate()->get();

            if ($detail->isEmpty()) {
                throw new PelanggaranAturanBisnis('EstimasiKosong', 'Estimasi belum berisi jasa atau sparepart.', 'Baris');
            }

            $dipilih = array_values(array_unique(array_map('strtoupper', $uuidBarisDisetujui)));

            if ($setuju) {
                $dikenal = $detail->pluck('Uuid')->all();

                if ($dipilih === []) {
                    throw new PelanggaranAturanBisnis('BarisWajibDipilih', 'Pilih minimal satu pekerjaan atau sparepart yang disetujui.', 'Baris');
                }

                if (array_diff($dipilih, $dikenal) !== []) {
                    throw new PelanggaranAturanBisnis('BarisTidakDikenal', 'Ada pilihan yang bukan bagian estimasi ini. Muat ulang halaman.', 'Baris');
                }
            }

            $disetujui = [];

            foreach ($detail as $d) {
                $d->Disetujui = $setuju && in_array($d->Uuid, $dipilih, true);
                $d->save();

                if ($d->Disetujui) {
                    $disetujui[] = $d;
                }
            }

            $outlet = Outlet::query()->whereKey($pk->IdOutlet)->first(['Id', 'KodeKota']);
            $total = $disetujui === [] ? null : $this->penyusun->Hitung($pk->IdOutlet, $outlet?->KodeKota, PenyusunBarisPerintahKerja::DariDetail($disetujui));
            $dari = $pk->Status;
            $tujuan = $setuju ? StatusPerintahKerja::Disetujui : StatusPerintahKerja::Ditolak;
            $pk->UbahStatus($tujuan);
            $catatan = $catatan === null ? null : trim($catatan);
            $pk->forceFill([
                'TotalDisetujui' => $total?->total->KeString() ?? '0.00',
                'DiputuskanPada' => now(),
                'DiputuskanLewat' => $lewat,
                'DiputuskanOleh' => $idPengguna,
                'HashIpPersetujuan' => $hashIp,
                'CatatanPelanggan' => $catatan === null || $catatan === '' ? null : mb_substr($catatan, 0, 255),
            ])->save();

            $keterangan = ($lewat === LewatPersetujuan::Tautan ? 'Lewat tautan pelanggan' : 'Dicatat staf')
                .($setuju ? ': '.count($disetujui).' dari '.$detail->count().' baris disetujui' : '');
            $this->riwayat->Catat(PerintahKerja::JENIS_DOKUMEN, $pk->Id, $dari->value, $tujuan->value, $idPengguna, $keterangan);
            $this->audit->Catat($setuju ? 'bengkel.persetujuan-setuju' : 'bengkel.persetujuan-tolak', $pk, ['Status' => $dari->value], [
                'Status' => $tujuan->value,
                'Lewat' => $lewat->value,
                'BarisDisetujui' => array_map(fn (PerintahKerjaDetail $d): string => $d->Uuid, $disetujui),
                'TotalDisetujui' => $pk->TotalDisetujui,
            ], idPengguna: $idPengguna);

            return $pk;
        });
    }
}
