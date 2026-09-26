<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataTransaksiKasBank;
use App\Domain\Akuntansi\Enum\FrekuensiJadwalKasBank;
use App\Domain\Akuntansi\Model\JadwalKasBank;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * D-23 D bagian 2: catat transaksi kas & bank sekarang dan jadikan pola berulang ("Ulangi otomatis"). Transaksi
 * pertama tercatat seperti biasa; jadwal menyimpan tanggal acuan & jatuh tempo berikutnya. Satu transaksi DB.
 * Audit `kas-bank.jadwal-buat`.
 */
final class CatatTransaksiKasBankBerulang
{
    public function __construct(
        private readonly SimpanTransaksiKasBank $simpan,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return array{Transaksi: TransaksiKasBank, Jadwal: JadwalKasBank}
     */
    public function Jalankan(DataTransaksiKasBank $data, FrekuensiJadwalKasBank $frekuensi): array
    {
        return DB::transaction(function () use ($data, $frekuensi): array {
            $transaksi = $this->simpan->Jalankan($data);
            $acuan = CarbonImmutable::parse($transaksi->Tanggal->toDateString());
            $jadwal = JadwalKasBank::query()->create([
                'Jenis' => $transaksi->Jenis,
                'IdOutlet' => $transaksi->IdOutlet,
                'IdAkunSumber' => $transaksi->IdAkunSumber,
                'IdAkunTujuan' => $transaksi->IdAkunTujuan,
                'Jumlah' => $transaksi->Jumlah,
                'Keterangan' => $transaksi->Keterangan,
                'Frekuensi' => $frekuensi,
                'TanggalAcuan' => $acuan->toDateString(),
                'TanggalBerikutnya' => $frekuensi->HitungBerikutnya($acuan, $acuan)->toDateString(),
                'JumlahDicatat' => 1,
                'DibuatOleh' => $data->idPengguna,
            ]);
            $this->audit->Catat('kas-bank.jadwal-buat', $jadwal, nilaiBaru: [
                'Nomor' => $transaksi->Nomor,
                'Frekuensi' => $frekuensi->value,
                'Jumlah' => $transaksi->Jumlah,
                'TanggalBerikutnya' => $jadwal->TanggalBerikutnya->toDateString(),
            ], idPengguna: $data->idPengguna);

            return ['Transaksi' => $transaksi, 'Jadwal' => $jadwal];
        });
    }
}
