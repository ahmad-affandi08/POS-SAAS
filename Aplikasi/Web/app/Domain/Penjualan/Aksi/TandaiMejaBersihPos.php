<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Aksi\AturKebersihanMeja;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `Meja.Bersih` (K-12, §9.1): pelayan/kasir menandai meja sudah dibersihkan setelah tagihannya dibayar.
 * Pelaku wajib anggota outlet yang boleh mencatat pesanan. Tanda yang lebih tua dari pembayaran terakhir meja itu,
 * atau meja yang tidak dikenal, tetap `Diterima` tanpa mengubah apa pun supaya outbox perangkat tidak macet.
 */
final class TandaiMejaBersihPos
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly AturKebersihanMeja $kebersihan,
    ) {}

    public function Jalankan(int $idOutlet, string $uuidMeja, string $uuidPengguna, CarbonImmutable $dibersihkanPada): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($dibersihkanPada, 'DibersihkanPada');
        $this->penjaga->CariPelaku($this->konteks->Wajib(), $uuidPengguna, $idOutlet);

        DB::transaction(fn (): bool => $this->kebersihan->TandaiBersih($idOutlet, $uuidMeja, $dibersihkanPada));

        return StatusItemSinkron::Diterima;
    }
}
