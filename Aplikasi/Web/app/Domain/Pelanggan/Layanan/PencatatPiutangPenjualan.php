<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\Piutang;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Pelanggan untuk domain Penjualan (F-12): piutang penjualan tempo dicatat, dibatalkan (void),
 * dan dikurangi (retur) di transaksi DB yang sama dengan dokumen penjualannya. Jatuh tempo = tanggal bisnis + termin
 * pelanggan (bawaan 30 hari). Idempoten per penjualan.
 */
final class PencatatPiutangPenjualan
{
    public const TERMIN_BAWAAN = 30;

    public function __construct(private readonly PencatatRiwayatStatus $riwayat) {}

    public function Catat(?int $idPelanggan, int $idPenjualan, int $idOutlet, string $nomor, CarbonImmutable $tanggalBisnis, Uang $jumlah): Piutang
    {
        $ada = Piutang::query()->where('IdPenjualan', $idPenjualan)->first();

        if ($ada !== null) {
            return $ada;
        }

        $termin = $idPelanggan === null ? self::TERMIN_BAWAAN : (int) (Pelanggan::query()->whereKey($idPelanggan)->value('TerminHari') ?? self::TERMIN_BAWAAN);
        $piutang = Piutang::query()->create([
            'IdPelanggan' => $idPelanggan,
            'IdPenjualan' => $idPenjualan,
            'IdOutlet' => $idOutlet,
            'Nomor' => $nomor,
            'TanggalBisnis' => $tanggalBisnis->toDateString(),
            'JatuhTempo' => $tanggalBisnis->addDays($termin)->toDateString(),
            'Jumlah' => $jumlah->KeString(),
            'Status' => StatusPiutang::BelumLunas,
        ]);
        $this->riwayat->Catat(Piutang::JENIS_DOKUMEN, $piutang->Id, null, StatusPiutang::BelumLunas->value, null);

        return $piutang;
    }

    /** Sisa piutang penjualan (null = bukan penjualan tempo). */
    public function AmbilSisa(int $idPenjualan): ?Uang
    {
        return Piutang::query()->where('IdPenjualan', $idPenjualan)->first()?->AmbilSisa();
    }

    /** Void hanya boleh bila piutangnya belum dibayar (null = boleh). */
    public function PeriksaBisaBatal(int $idPenjualan): ?string
    {
        $piutang = Piutang::query()->where('IdPenjualan', $idPenjualan)->first();

        return $piutang !== null && ! Uang::Dari($piutang->JumlahDibayar)->BernilaiNol()
            ? "Piutang {$piutang->Nomor} sudah dibayar sebagian. Gunakan retur."
            : null;
    }

    /** Void: sisa piutang dikurangi habis dan status Dibatalkan. */
    public function Batalkan(int $idPenjualan, int $idPengguna): void
    {
        $piutang = Piutang::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->first();

        if ($piutang === null || $piutang->Status === StatusPiutang::Dibatalkan) {
            return;
        }

        $asal = $piutang->Status;
        $piutang->JumlahDikurangi = Uang::Dari($piutang->JumlahDikurangi)->Tambah($piutang->AmbilSisa())->KeString();
        $piutang->Status = StatusPiutang::Dibatalkan;
        $piutang->save();
        $this->riwayat->Catat(Piutang::JENIS_DOKUMEN, $piutang->Id, $asal->value, StatusPiutang::Dibatalkan->value, $idPengguna, 'Penjualan di-void');
    }

    /** Retur: sisa piutang dikurangi [jumlah] (≤ sisa, diperiksa pemanggil). */
    public function Kurangi(int $idPenjualan, Uang $jumlah, int $idPengguna): void
    {
        $piutang = Piutang::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->first();

        if ($piutang === null || $jumlah->BernilaiNol()) {
            return;
        }

        $asal = $piutang->Status;
        $piutang->JumlahDikurangi = Uang::Dari($piutang->JumlahDikurangi)->Tambah($jumlah)->KeString();
        $piutang->SelaraskanStatus();
        $piutang->save();

        if ($asal !== $piutang->Status) {
            $this->riwayat->Catat(Piutang::JENIS_DOKUMEN, $piutang->Id, $asal->value, $piutang->Status->value, $idPengguna, 'Retur penjualan');
        }
    }
}
