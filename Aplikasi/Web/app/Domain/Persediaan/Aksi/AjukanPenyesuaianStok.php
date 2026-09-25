<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Layanan\PemostingPenyesuaianStok;
use App\Domain\Persediaan\Layanan\PenaksirNilaiPenyesuaian;
use App\Domain\Persediaan\Layanan\PengunciSaldoStok;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan draf penyesuaian stok (F-05b, §19.2). Isi diperiksa ulang dan SaldoStok dikunci, lalu nilainya
 * ditaksir (`PenaksirNilaiPenyesuaian`): di atas `BatasPersetujuanPenyesuaian` → MenungguPersetujuan (disetujui orang
 * lain yang ber-izin `persediaan.penyesuaian.setujui`); selain itu langsung diposting (mutasi + jurnal). Idempoten:
 * dokumen yang sudah diajukan/diposting dikembalikan apa adanya. Audit `penyesuaian-stok.ajukan`/`.posting`.
 */
final class AjukanPenyesuaianStok
{
    public function __construct(
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly PengunciSaldoStok $pengunciSaldo,
        private readonly PenaksirNilaiPenyesuaian $penaksir,
        private readonly PemostingPenyesuaianStok $pemosting,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PenyesuaianStok $dokumen, int $idPengguna): PenyesuaianStok
    {
        return DB::transaction(function () use ($dokumen, $idPengguna): PenyesuaianStok {
            $pengaturan = $this->pengaturan->AmbilDenganKunciBaca();
            $dokumen = PenyesuaianStok::query()->whereKey($dokumen->Id)->lockForUpdate()->firstOrFail();

            if ($dokumen->Status === StatusPenyesuaianStok::MenungguPersetujuan || $dokumen->Status === StatusPenyesuaianStok::Diposting) {
                return $dokumen;
            }

            if ($dokumen->Status !== StatusPenyesuaianStok::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Penyesuaian berstatus {$dokumen->Status->AmbilLabel()} tidak bisa diajukan.");
            }

            [$gudang, $produk, $detail, $baris] = $this->pemosting->Siapkan($dokumen, $this->pengunciSaldo);
            $nilai = $this->penaksir->Taksir($baris, $gudang->id);
            $perlu = $nilai->Bandingkan($pengaturan->batasPersetujuanPenyesuaian) > 0;
            $dokumen->fill(['NilaiPerkiraan' => $nilai->KeString(), 'PerluPersetujuan' => $perlu, 'DiajukanOleh' => $idPengguna, 'DiajukanPada' => now()]);

            if ($perlu) {
                $dokumen->UbahStatus(StatusPenyesuaianStok::MenungguPersetujuan);
                $dokumen->DiubahOleh = $idPengguna;
                $dokumen->save();
                $this->riwayat->Catat(PenyesuaianStok::JENIS_DOKUMEN, $dokumen->Id, StatusPenyesuaianStok::Draf->value, StatusPenyesuaianStok::MenungguPersetujuan->value, $idPengguna);
                $this->audit->Catat('penyesuaian-stok.ajukan', $dokumen, ['Status' => StatusPenyesuaianStok::Draf->value], [
                    'Status' => StatusPenyesuaianStok::MenungguPersetujuan->value,
                    'NilaiPerkiraan' => $dokumen->NilaiPerkiraan,
                    'BatasPersetujuan' => $pengaturan->batasPersetujuanPenyesuaian->KeString(),
                ], idPengguna: $idPengguna);

                return $dokumen;
            }

            $jurnal = $this->pemosting->Posting($dokumen, $gudang, $produk, $detail, $idPengguna);
            $this->riwayat->Catat(PenyesuaianStok::JENIS_DOKUMEN, $dokumen->Id, StatusPenyesuaianStok::Draf->value, StatusPenyesuaianStok::Diposting->value, $idPengguna);
            $this->audit->Catat('penyesuaian-stok.posting', $dokumen, ['Status' => StatusPenyesuaianStok::Draf->value], [
                'Status' => StatusPenyesuaianStok::Diposting->value,
                'Nomor' => $dokumen->Nomor,
                'TotalNilaiMasuk' => $dokumen->TotalNilaiMasuk,
                'TotalNilaiKeluar' => $dokumen->TotalNilaiKeluar,
                'NomorJurnal' => $jurnal?->nomor,
            ], idPengguna: $idPengguna);

            return $dokumen;
        }, max(1, (int) config('persediaan.PercobaanTransaksi', 3)));
    }
}
