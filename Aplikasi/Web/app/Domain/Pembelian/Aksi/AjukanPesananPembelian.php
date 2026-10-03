<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Peristiwa\PeristiwaIntegrasi;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\PenyetujuLain;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Layanan\PenyusunDataIntegrasiPembelian;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Tenant\Kueri\PengaturanPembelianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan draf PO (F-04 fase 1, §19.2): Total di atas `BatasPersetujuanPo` → MenungguPersetujuan (disetujui pemegang
 * `pembelian.po.setujui` yang bukan pembuatnya); sampai batas → langsung Disetujui. Idempoten untuk PO yang sudah
 * diajukan. Audit `pesanan-pembelian.ajukan`.
 *
 * D-38 four-eyes adaptif: di atas batas tetapi pelaku Pemilik, atau tidak ada pemegang `pembelian.po.setujui` lain
 * yang menjangkau outlet lokasi PO (`PenyetujuLain`) → langsung Disetujui dengan alasan di audit (`DisetujuiLangsung`).
 */
final class AjukanPesananPembelian
{
    public function __construct(
        private readonly PengaturanPembelianTenant $pengaturan,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
        private readonly PenyusunDataIntegrasiPembelian $dataIntegrasi,
        private readonly AksesPengguna $akses,
        private readonly PenyetujuLain $penyetujuLain,
        private readonly InfoGudang $infoGudang,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis StatusTidakSesuai
     */
    public function Jalankan(PesananPembelian $po, int $idPengguna): PesananPembelian
    {
        return DB::transaction(function () use ($po, $idPengguna): PesananPembelian {
            $terkunci = PesananPembelian::query()->whereKey($po->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status === StatusPesananPembelian::MenungguPersetujuan || $terkunci->Status === StatusPesananPembelian::Disetujui) {
                return $terkunci;
            }

            if ($terkunci->Status !== StatusPesananPembelian::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Pesanan pembelian berstatus {$terkunci->Status->AmbilLabel()} tidak bisa diajukan.");
            }

            $batas = $this->pengaturan->Ambil()->batasPersetujuanPo;
            $diAtasBatas = Uang::Dari($terkunci->Total)->Bandingkan($batas) > 0;
            $langsung = $diAtasBatas ? $this->AmbilAlasanLangsung($terkunci, $idPengguna) : null;
            $butuhPersetujuan = $diAtasBatas && $langsung === null;
            $tujuan = $butuhPersetujuan ? StatusPesananPembelian::MenungguPersetujuan : StatusPesananPembelian::Disetujui;

            $terkunci->UbahStatus($tujuan);
            $terkunci->fill([
                'AlasanDitolak' => null,
                'DiajukanOleh' => $idPengguna,
                'DiajukanPada' => now(),
                'DisetujuiOleh' => $butuhPersetujuan ? null : $idPengguna,
                'DisetujuiPada' => $butuhPersetujuan ? null : now(),
                'DiubahOleh' => $idPengguna,
            ])->save();

            $this->riwayat->Catat(PesananPembelian::JENIS_DOKUMEN, $terkunci->Id, StatusPesananPembelian::Draf->value, $tujuan->value, $idPengguna);
            $this->audit->Catat('pesanan-pembelian.ajukan', $terkunci, ['Status' => StatusPesananPembelian::Draf->value], [
                'Status' => $tujuan->value,
                'Total' => $terkunci->Total,
                'BatasPersetujuanPo' => $batas->KeString(),
                ...($langsung === null ? [] : ['DisetujuiLangsung' => $langsung]),
            ], idPengguna: $idPengguna);

            if (! $butuhPersetujuan) {
                // X7 §16.4: PO di bawah batas persetujuan langsung Disetujui → webhook `pesanan-pembelian.disetujui`.
                PeristiwaIntegrasi::dispatch($terkunci->IdTenant, 'pesanan-pembelian.disetujui', $terkunci->Id, $this->dataIntegrasi->Pesanan($terkunci));
            }

            return $terkunci;
        }, 3);
    }

    /** D-38: alasan PO di atas batas disetujui tanpa penyetuju kedua, atau null bila four-eyes berlaku. */
    private function AmbilAlasanLangsung(PesananPembelian $po, int $idPengguna): ?string
    {
        if (($this->akses->Ambil($po->IdTenant, $idPengguna)['Pemilik'] ?? false) === true) {
            return 'Pemilik';
        }

        $idOutlet = ($this->infoGudang->AmbilBanyak([$po->IdGudang])[$po->IdGudang] ?? null)?->idOutlet;

        return $this->penyetujuLain->CekAda($po->IdTenant, $idPengguna, IzinTenant::PembelianPoSetujui, $idOutlet) ? null : 'PenyetujuTunggal';
    }
}
