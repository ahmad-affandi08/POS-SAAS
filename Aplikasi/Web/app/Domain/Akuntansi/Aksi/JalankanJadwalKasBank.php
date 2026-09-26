<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataTransaksiKasBank;
use App\Domain\Akuntansi\Model\JadwalKasBank;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * D-23 D bagian 2: mencatat setiap transaksi kas & bank berulang yang jatuh tempo s.d. `hariIni` (tanggal transaksi =
 * tanggal jatuh tempo; tertinggal beberapa kali = dicatat semuanya, maks. [MAKS_SUSULAN] per jadwal per putaran).
 * Tiap kejadian satu transaksi DB (transaksi + jurnal + majukan jadwal). Transaksi untuk (jadwal, tanggal) yang sudah
 * ada tidak dibuat lagi (idempoten). Bila ditolak aturan bisnis (misal periode terkunci, akun nonaktif), jadwal tidak
 * dimajukan dan alasannya disimpan di `GalatTerakhir` untuk Kotak Tindakan.
 */
final class JalankanJadwalKasBank
{
    public const MAKS_SUSULAN = 12;

    public function __construct(private readonly SimpanTransaksiKasBank $simpan) {}

    /**
     * @return array{Dicatat: int, Gagal: int}
     */
    public function Jalankan(CarbonImmutable $hariIni): array
    {
        $dicatat = 0;
        $gagal = 0;
        $daftar = JadwalKasBank::query()
            ->where('Aktif', true)
            ->where('TanggalBerikutnya', '<=', $hariIni->toDateString())
            ->with(['AkunSumber:Id,Uuid', 'AkunTujuan:Id,Uuid'])
            ->orderBy('Id')
            ->get();

        foreach ($daftar as $jadwal) {
            for ($i = 0; $i < self::MAKS_SUSULAN; $i++) {
                $hasil = $this->CatatSatu($jadwal->Id, $hariIni);

                if ($hasil === 'Gagal') {
                    $gagal++;

                    break;
                }

                if ($hasil === 'Selesai') {
                    break;
                }

                $dicatat++;
            }
        }

        return ['Dicatat' => $dicatat, 'Gagal' => $gagal];
    }

    /** @return 'Dicatat'|'Selesai'|'Gagal' */
    private function CatatSatu(int $idJadwal, CarbonImmutable $hariIni): string
    {
        try {
            return DB::transaction(function () use ($idJadwal, $hariIni): string {
                $jadwal = JadwalKasBank::query()->whereKey($idJadwal)->with(['AkunSumber:Id,Uuid', 'AkunTujuan:Id,Uuid'])->lockForUpdate()->firstOrFail();
                $tanggal = CarbonImmutable::parse($jadwal->TanggalBerikutnya->toDateString());

                // Bandingkan tanggal kalender (hariIni bisa berzona waktu outlet).
                if (! $jadwal->Aktif || $tanggal->toDateString() > $hariIni->toDateString()) {
                    return 'Selesai';
                }

                $sudahAda = TransaksiKasBank::query()->where('IdJadwalKasBank', $jadwal->Id)->where('Tanggal', $tanggal->toDateString())->exists();

                if (! $sudahAda) {
                    $this->simpan->Jalankan(new DataTransaksiKasBank(
                        $jadwal->Jenis,
                        $tanggal,
                        $jadwal->IdOutlet,
                        $jadwal->AkunSumber->Uuid,
                        $jadwal->AkunTujuan->Uuid,
                        Uang::Dari($jadwal->Jumlah),
                        $jadwal->Keterangan,
                        null,
                        $jadwal->DibuatOleh,
                        $jadwal->Id,
                    ));
                    $jadwal->JumlahDicatat++;
                }

                $jadwal->setAttribute('TanggalBerikutnya', $jadwal->Frekuensi->HitungBerikutnya(CarbonImmutable::parse($jadwal->TanggalAcuan->toDateString()), $tanggal)->toDateString());
                $jadwal->GalatTerakhir = null;
                $jadwal->save();

                return 'Dicatat';
            });
        } catch (PelanggaranAturanBisnis $galat) {
            JadwalKasBank::query()->whereKey($idJadwal)->update(['GalatTerakhir' => mb_substr($galat->getMessage(), 0, 255)]);

            return 'Gagal';
        }
    }
}
