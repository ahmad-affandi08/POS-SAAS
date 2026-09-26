<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Penjualan\Enum\StatusPesananSendiri;
use App\Domain\Penjualan\Layanan\PenandaKedaluwarsaPesananSendiri;
use App\Domain\Penjualan\Layanan\PenjagaPesananSendiri;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use App\Domain\Penjualan\Model\PesananSendiri;
use Illuminate\Support\Facades\DB;

/**
 * F-17: staf menolak pesanan QR di POS dengan alasan yang tampil ke tamu (`POST /api/pos/v1/pesan-sendiri/{uuid}/tolak`).
 * Idempoten: sudah ditolak → hasil sama; diterima → 409 `SudahDiproses`; lewat 30 menit → 409 `Kedaluwarsa`.
 * Audit `pesan-sendiri.tolak`.
 */
final class TolakPesananSendiri
{
    public function __construct(
        private readonly PenjagaPesananSendiri $penjaga,
        private readonly PenjagaPesananTerbuka $penjagaPesanan,
        private readonly PenandaKedaluwarsaPesananSendiri $kedaluwarsa,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idTenant, int $idOutlet, int $idPerangkat, string $uuid, string $uuidPengguna, string $alasan): PesananSendiri
    {
        $pelaku = $this->penjagaPesanan->CariPelaku($idTenant, $uuidPengguna, $idOutlet);
        $this->kedaluwarsa->TandaiOutlet($idOutlet);

        return DB::transaction(function () use ($idOutlet, $idPerangkat, $uuid, $alasan, $pelaku): PesananSendiri {
            $pesanan = $this->penjaga->CariUntukDiproses($uuid, $idOutlet);

            if ($pesanan->Status === StatusPesananSendiri::Ditolak) {
                return $pesanan;
            }

            $this->penjaga->PastikanMenunggu($pesanan);
            $pesanan->update([
                'Status' => StatusPesananSendiri::Ditolak,
                'AlasanTolak' => $alasan,
                'IdPemroses' => $pelaku->id,
                'IdPerangkat' => $idPerangkat,
                'DiprosesPada' => now(),
            ]);
            $this->audit->Catat('pesan-sendiri.tolak', $pesanan, nilaiLama: ['Status' => StatusPesananSendiri::MenungguKonfirmasi->value], nilaiBaru: [
                'Status' => StatusPesananSendiri::Ditolak->value,
                'Nomor' => $pesanan->Nomor,
                'AlasanTolak' => $alasan,
            ], idPengguna: $pelaku->id);

            return $pesanan;
        });
    }
}
