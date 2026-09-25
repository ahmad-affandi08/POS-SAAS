<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PembayaranHutang;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PesananPembelian;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus pemasok yang belum pernah dipakai dokumen apa pun (soft delete, F-04 fase 1). Pemasok yang sudah dipakai
 * ditolak `PemasokSudahDipakai`: nonaktifkan saja. Audit `pemasok.hapus`.
 */
final class HapusPemasok
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @throws PelanggaranAturanBisnis PemasokSudahDipakai
     */
    public function Jalankan(Pemasok $pemasok, int $idPengguna): void
    {
        DB::transaction(function () use ($pemasok, $idPengguna): void {
            $terkunci = Pemasok::query()->whereKey($pemasok->Id)->lockForUpdate()->firstOrFail();
            $dipakai = PesananPembelian::query()->where('IdPemasok', $terkunci->Id)->exists()
                || PenerimaanBarang::query()->where('IdPemasok', $terkunci->Id)->exists()
                || FakturPembelian::query()->where('IdPemasok', $terkunci->Id)->exists()
                || PembayaranHutang::query()->where('IdPemasok', $terkunci->Id)->exists();

            if ($dipakai) {
                throw new PelanggaranAturanBisnis('PemasokSudahDipakai', "Pemasok {$terkunci->Nama} sudah dipakai di dokumen pembelian. Nonaktifkan saja agar tidak bisa dipilih lagi.");
            }

            $terkunci->delete();
            $this->audit->Catat('pemasok.hapus', $terkunci, ['Kode' => $terkunci->Kode, 'Nama' => $terkunci->Nama], idPengguna: $idPengguna);
        });
    }
}
