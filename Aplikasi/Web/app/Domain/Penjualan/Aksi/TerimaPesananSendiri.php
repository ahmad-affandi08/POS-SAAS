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
 * F-17: staf menerima pesanan QR di POS (`POST /api/pos/v1/pesan-sendiri/{uuid}/terima`). Perangkat itu sendiri yang
 * lalu membuat pesanan terbuka (`UuidPesananTerbuka`) lewat outbox-nya (`PesananTerbuka.Buka/Tambah`), sehingga server
 * hanya mencatat tautannya. Idempoten: sudah diterima dengan `UuidPesananTerbuka` sama → hasil sama; diproses dengan
 * hasil lain → 409 `SudahDiproses`; lewat 30 menit → 409 `Kedaluwarsa`. Audit `pesan-sendiri.terima`.
 */
final class TerimaPesananSendiri
{
    public function __construct(
        private readonly PenjagaPesananSendiri $penjaga,
        private readonly PenjagaPesananTerbuka $penjagaPesanan,
        private readonly PenandaKedaluwarsaPesananSendiri $kedaluwarsa,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idTenant, int $idOutlet, int $idPerangkat, string $uuid, string $uuidPengguna, string $uuidPesananTerbuka): PesananSendiri
    {
        $pelaku = $this->penjagaPesanan->CariPelaku($idTenant, $uuidPengguna, $idOutlet);
        $this->kedaluwarsa->TandaiOutlet($idOutlet);

        return DB::transaction(function () use ($idOutlet, $idPerangkat, $uuid, $uuidPesananTerbuka, $pelaku): PesananSendiri {
            $pesanan = $this->penjaga->CariUntukDiproses($uuid, $idOutlet);

            if ($pesanan->Status === StatusPesananSendiri::Diterima && $pesanan->UuidPesananTerbuka === $uuidPesananTerbuka) {
                return $pesanan;
            }

            $this->penjaga->PastikanMenunggu($pesanan);
            $pesanan->update([
                'Status' => StatusPesananSendiri::Diterima,
                'UuidPesananTerbuka' => $uuidPesananTerbuka,
                'IdPemroses' => $pelaku->id,
                'IdPerangkat' => $idPerangkat,
                'DiprosesPada' => now(),
            ]);
            $this->audit->Catat('pesan-sendiri.terima', $pesanan, nilaiLama: ['Status' => StatusPesananSendiri::MenungguKonfirmasi->value], nilaiBaru: [
                'Status' => StatusPesananSendiri::Diterima->value,
                'Nomor' => $pesanan->Nomor,
                'UuidPesananTerbuka' => $uuidPesananTerbuka,
            ], idPengguna: $pelaku->id);

            return $pesanan;
        });
    }
}
