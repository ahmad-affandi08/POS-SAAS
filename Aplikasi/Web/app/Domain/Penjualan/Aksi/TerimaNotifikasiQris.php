<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\GerbangPembayaran\HasilWebhook;
use App\Domain\Penjualan\Layanan\NomorPesananQris;
use App\Domain\Penjualan\Layanan\PenerapStatusTagihanQris;
use App\Domain\Penjualan\Model\TagihanQris;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Illuminate\Support\Facades\Log;

/**
 * F-08 BR-08.5: notifikasi gerbang (tanda tangan sudah diverifikasi adaptor) diterapkan ke tagihan QRIS. Tenant
 * diambil dari `NomorPesanan` (`PY{tenant basis-36}-{Uuid}`) lalu dipasang sebagai konteks, sehingga pencarian tetap
 * lewat scope `MilikTenant` (tanpa query lintas tenant). Tagihan tidak dikenal / penyedia berbeda = false (dijawab
 * 200 agar gerbang berhenti mengulang) dan dicatat di log. Idempoten: tagihan `Lunas` tidak berubah lagi.
 */
final class TerimaNotifikasiQris
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly PenerapStatusTagihanQris $penerap,
    ) {}

    public function Jalankan(HasilWebhook $notifikasi, string $kodePenyedia): bool
    {
        $nomor = NomorPesananQris::Urai($notifikasi->nomorPesanan);

        if ($nomor === null || ! $this->profil->CekAda($nomor['IdTenant'])) {
            return $this->Abaikan($notifikasi, $kodePenyedia, 'nomor pesanan tidak dikenal');
        }

        $konteksLama = $this->konteks->Ambil();
        $this->konteks->Atur($nomor['IdTenant']);

        try {
            $tagihan = TagihanQris::query()->where('NomorPesanan', $notifikasi->nomorPesanan)->first();

            if ($tagihan === null || $tagihan->Penyedia !== $kodePenyedia) {
                return $this->Abaikan($notifikasi, $kodePenyedia, $tagihan === null ? 'tagihan tidak ditemukan' : 'penyedia berbeda');
            }

            $this->penerap->Terapkan($tagihan->Id, $notifikasi->status, $notifikasi->jumlah, "Webhook {$kodePenyedia}");

            return true;
        } finally {
            $konteksLama === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($konteksLama);
        }
    }

    private function Abaikan(HasilWebhook $notifikasi, string $kodePenyedia, string $alasan): bool
    {
        Log::warning('Notifikasi QRIS dinamis diabaikan: '.$alasan.'.', [
            'Penyedia' => $kodePenyedia,
            'NomorPesanan' => mb_substr($notifikasi->nomorPesanan, 0, 60),
            'Status' => $notifikasi->status->value,
        ]);

        return false;
    }
}
