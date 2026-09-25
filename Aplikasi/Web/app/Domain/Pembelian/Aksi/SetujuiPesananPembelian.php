<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\PesananPembelian;
use Illuminate\Support\Facades\DB;

/**
 * Menyetujui atau menolak PO yang menunggu persetujuan (F-04 fase 1, §19.2, izin `pembelian.po.setujui` dijaga rute).
 * Penyetuju tidak boleh pembuat PO (`PenyetujuPembuat`). Ditolak = kembali ke Draf dengan alasan wajib (5–255
 * karakter). Audit `pesanan-pembelian.setujui`/`pesanan-pembelian.tolak`.
 */
final class SetujuiPesananPembelian
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis StatusTidakSesuai, PenyetujuPembuat, AlasanTidakValid
     */
    public function Jalankan(PesananPembelian $po, int $idPengguna, bool $setujui = true, ?string $alasan = null): PesananPembelian
    {
        $alasan = trim((string) $alasan);

        if (! $setujui && (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255)) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan penolakan wajib diisi, 5 sampai 255 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($po, $idPengguna, $setujui, $alasan): PesananPembelian {
            $terkunci = PesananPembelian::query()->whereKey($po->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status !== StatusPesananPembelian::MenungguPersetujuan) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Pesanan pembelian berstatus {$terkunci->Status->AmbilLabel()} tidak sedang menunggu persetujuan.");
            }

            if ($terkunci->DibuatOleh === $idPengguna) {
                throw new PelanggaranAturanBisnis('PenyetujuPembuat', 'Pesanan pembelian tidak bisa disetujui atau ditolak oleh pembuatnya. Minta pemegang izin persetujuan lain.');
            }

            $tujuan = $setujui ? StatusPesananPembelian::Disetujui : StatusPesananPembelian::Draf;
            $terkunci->UbahStatus($tujuan);
            $terkunci->fill($setujui
                ? ['DisetujuiOleh' => $idPengguna, 'DisetujuiPada' => now(), 'DiubahOleh' => $idPengguna]
                : ['AlasanDitolak' => $alasan, 'DiajukanOleh' => null, 'DiajukanPada' => null, 'DiubahOleh' => $idPengguna])->save();

            $this->riwayat->Catat(PesananPembelian::JENIS_DOKUMEN, $terkunci->Id, StatusPesananPembelian::MenungguPersetujuan->value, $tujuan->value, $idPengguna, $setujui ? null : $alasan);
            $this->audit->Catat($setujui ? 'pesanan-pembelian.setujui' : 'pesanan-pembelian.tolak', $terkunci, ['Status' => StatusPesananPembelian::MenungguPersetujuan->value], [
                'Status' => $tujuan->value,
                'Total' => $terkunci->Total,
                'Alasan' => $setujui ? null : $alasan,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
