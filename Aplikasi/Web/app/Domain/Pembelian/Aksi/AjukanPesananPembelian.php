<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Tenant\Kueri\PengaturanPembelianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan draf PO (F-04 fase 1, §19.2): Total di atas `BatasPersetujuanPo` → MenungguPersetujuan (disetujui pemegang
 * `pembelian.po.setujui` yang bukan pembuatnya); sampai batas → langsung Disetujui. Idempoten untuk PO yang sudah
 * diajukan. Audit `pesanan-pembelian.ajukan`.
 */
final class AjukanPesananPembelian
{
    public function __construct(
        private readonly PengaturanPembelianTenant $pengaturan,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
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
            $butuhPersetujuan = Uang::Dari($terkunci->Total)->Bandingkan($batas) > 0;
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
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
