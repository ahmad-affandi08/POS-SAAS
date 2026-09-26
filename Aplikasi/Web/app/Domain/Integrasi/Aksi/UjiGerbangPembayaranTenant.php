<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Integrasi\HasilUjiLayanan;
use App\Domain\Integrasi\Model\GerbangPembayaranTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Uji koneksi gerbang pembayaran tenant (v2.06) lewat `UjiKoneksi` adaptor, tanpa membuat transaksi. Panggilan
 * jaringan di luar transaksi DB; hasil dibuang bila isian berubah selama pengujian, agar status `Berhasil` selalu milik
 * isian yang benar-benar diuji. Pesan tidak pernah memuat kredensial.
 */
final class UjiGerbangPembayaranTenant
{
    public function __construct(
        private readonly PembuatGerbangPembayaran $pembuat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(): HasilUjiLayanan
    {
        $gerbang = GerbangPembayaranTenant::query()->first()
            ?? throw new PelanggaranAturanBisnis('GerbangBelumDiatur', 'Simpan gerbang pembayaran dulu sebelum mengujinya.');
        $isiDiuji = self::AmbilIsi($gerbang);
        $hasil = $this->Uji($gerbang);

        return DB::transaction(function () use ($gerbang, $isiDiuji, $hasil): HasilUjiLayanan {
            $terkini = GerbangPembayaranTenant::query()->lockForUpdate()->findOrFail($gerbang->Id);

            if (self::AmbilIsi($terkini) !== $isiDiuji) {
                return HasilUjiLayanan::Gagal('Isian gerbang berubah saat diuji. Uji ulang.');
            }

            $terkini->update([
                'StatusUji' => $hasil->berhasil ? StatusUjiGerbang::Berhasil : StatusUjiGerbang::Gagal,
                'PesanUji' => mb_substr($hasil->pesan, 0, 300),
                'DiujiPada' => now(),
            ]);
            $this->audit->Catat('gerbang-pembayaran.uji', $terkini, nilaiBaru: [
                'Penyedia' => $terkini->Penyedia->value,
                'Lingkungan' => $terkini->Lingkungan->value,
                'Berhasil' => $hasil->berhasil,
            ]);

            return $hasil;
        });
    }

    private function Uji(GerbangPembayaranTenant $gerbang): HasilUjiLayanan
    {
        $adaptor = $this->pembuat->BuatDariTenant($gerbang);

        if ($adaptor === null) {
            return HasilUjiLayanan::Gagal('Kredensial tersimpan tidak bisa dibaca. Isi ulang kredensial lalu simpan.');
        }

        try {
            return $adaptor->UjiKoneksi();
        } catch (GalatGerbang $galat) {
            return HasilUjiLayanan::Gagal($galat->getMessage());
        } catch (Throwable $galat) {
            $pesan = $galat->getMessage();

            foreach ($gerbang->Kredensial as $rahasia) {
                $pesan = $rahasia === '' ? $pesan : str_replace($rahasia, '••••', $pesan);
            }

            return HasilUjiLayanan::Gagal('Uji gagal: '.Str::limit($pesan, 250));
        }
    }

    /**
     * @return array<int, mixed>
     */
    private static function AmbilIsi(GerbangPembayaranTenant $gerbang): array
    {
        return [$gerbang->Penyedia, $gerbang->Lingkungan, $gerbang->Pengaturan, $gerbang->Kredensial];
    }
}
