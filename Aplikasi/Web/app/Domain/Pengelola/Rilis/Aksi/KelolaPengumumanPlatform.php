<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\Pengelola\Rilis\Data\DataPengumumanPlatform;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisPengumuman;
use App\Domain\Tenant\Enum\PlatformPengumuman;
use App\Domain\Tenant\Enum\StatusPengumuman;
use App\Domain\Tenant\Kueri\PengumumanBerlaku;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\PengumumanPlatform;
use Illuminate\Support\Facades\DB;

/**
 * P-10 PGL-19: simpan draf, terbitkan, atau cabut pengumuman platform. Draf bebas diubah; yang terbit hanya bisa
 * dicabut (beralasan) supaya isi yang pernah dilihat tenant tetap tercatat. Masa tampil ≤ `MAKS_HARI_TAMPIL` hari.
 * Pemeliharaan wajib berjadwal dan masa tampilnya harus mencakup awal sampai akhir pemeliharaan. Sasaran divalidasi
 * terhadap katalog paket, template sektor, platform, dan format versi. Audit `pengumuman.simpan|terbit|cabut`.
 */
final class KelolaPengumumanPlatform
{
    public const MAKS_HARI_TAMPIL = 90;

    public const POLA_VERSI = '/^\d+\.\d+\.\d+$/';

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Simpan(PenggunaPengelola $pelaku, DataPengumumanPlatform $data, ?PengumumanPlatform $pengumuman = null): PengumumanPlatform
    {
        $this->Periksa($data);

        return DB::transaction(function () use ($pelaku, $data, $pengumuman): PengumumanPlatform {
            if ($pengumuman !== null) {
                $pengumuman = PengumumanPlatform::query()->whereKey($pengumuman->Id)->lockForUpdate()->firstOrFail();

                if ($pengumuman->Status !== StatusPengumuman::Draf) {
                    throw new PelanggaranAturanBisnis('BukanDraf', 'Pengumuman yang sudah terbit tidak bisa diubah. Cabut lalu buat yang baru.', 'Umum', 409);
                }
            }

            $pengumuman ??= new PengumumanPlatform(['DibuatOleh' => $pelaku->Id]);
            $pengumuman->fill([
                'Judul' => trim($data->judul),
                'Isi' => trim($data->isi),
                'Jenis' => $data->jenis,
                'Sasaran' => $data->sasaran,
                'Tautan' => $data->tautan,
                'TampilMulai' => $data->tampilMulai,
                'TampilSampai' => $data->tampilSampai,
                'PemeliharaanMulai' => $data->jenis === JenisPengumuman::Pemeliharaan ? $data->pemeliharaanMulai : null,
                'PemeliharaanSelesai' => $data->jenis === JenisPengumuman::Pemeliharaan ? $data->pemeliharaanSelesai : null,
            ])->save();

            $this->audit->Catat('pengumuman.simpan', $pengumuman, nilaiBaru: [
                'Judul' => $pengumuman->Judul,
                'Jenis' => $pengumuman->Jenis->value,
                'Sasaran' => $pengumuman->Sasaran,
            ], idPelaku: $pelaku->Id);

            return $pengumuman;
        });
    }

    public function Terbitkan(PenggunaPengelola $pelaku, PengumumanPlatform $pengumuman): PengumumanPlatform
    {
        return DB::transaction(function () use ($pelaku, $pengumuman): PengumumanPlatform {
            $terkunci = PengumumanPlatform::query()->whereKey($pengumuman->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status !== StatusPengumuman::Draf) {
                throw new PelanggaranAturanBisnis('BukanDraf', 'Pengumuman ini sudah terbit atau dicabut.', 'Umum', 409);
            }

            if ($terkunci->TampilSampai->lessThanOrEqualTo(now())) {
                throw new PelanggaranAturanBisnis('MasaTampilLewat', 'Masa tampil sudah lewat. Ubah tanggalnya dulu.', 'TampilSampai');
            }

            $terkunci->UbahStatus(StatusPengumuman::Terbit);
            $terkunci->fill(['DiterbitkanPada' => now(), 'DiterbitkanOleh' => $pelaku->Id])->save();
            $this->audit->Catat('pengumuman.terbit', $terkunci, ['Status' => StatusPengumuman::Draf->value], ['Status' => $terkunci->Status->value], idPelaku: $pelaku->Id);
            DB::afterCommit(fn () => PengumumanBerlaku::LupakanTembolok());

            return $terkunci;
        });
    }

    public function Cabut(PenggunaPengelola $pelaku, PengumumanPlatform $pengumuman, string $alasan): PengumumanPlatform
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
            throw new PelanggaranAturanBisnis('AlasanTidakValid', 'Alasan mencabut 5 sampai 255 huruf.', 'Alasan');
        }

        return DB::transaction(function () use ($pelaku, $pengumuman, $alasan): PengumumanPlatform {
            $terkunci = PengumumanPlatform::query()->whereKey($pengumuman->Id)->lockForUpdate()->firstOrFail();
            $asal = $terkunci->Status;

            if (! $asal->BisaBerubahKe(StatusPengumuman::Dicabut)) {
                throw new PelanggaranAturanBisnis('SudahDicabut', 'Pengumuman ini sudah dicabut.', 'Umum', 409);
            }

            $terkunci->UbahStatus(StatusPengumuman::Dicabut);
            $terkunci->fill(['DicabutPada' => now(), 'AlasanCabut' => $alasan])->save();
            $this->audit->Catat('pengumuman.cabut', $terkunci, ['Status' => $asal->value], ['Status' => $terkunci->Status->value], alasan: $alasan, idPelaku: $pelaku->Id);
            DB::afterCommit(fn () => PengumumanBerlaku::LupakanTembolok());

            return $terkunci;
        });
    }

    private function Periksa(DataPengumumanPlatform $data): void
    {
        $judul = trim($data->judul);
        $isi = trim($data->isi);

        if (mb_strlen($judul) < 5 || mb_strlen($judul) > 120) {
            throw new PelanggaranAturanBisnis('JudulTidakValid', 'Judul 5 sampai 120 huruf.', 'Judul');
        }

        if (mb_strlen($isi) < 10 || mb_strlen($isi) > 1000) {
            throw new PelanggaranAturanBisnis('IsiTidakValid', 'Isi 10 sampai 1.000 huruf.', 'Isi');
        }

        if ($data->tautan !== null && ! str_starts_with($data->tautan, 'https://')) {
            throw new PelanggaranAturanBisnis('TautanTidakValid', 'Tautan harus diawali https://.', 'Tautan');
        }

        if ($data->tampilSampai->lessThanOrEqualTo($data->tampilMulai)) {
            throw new PelanggaranAturanBisnis('MasaTampilTidakValid', 'Akhir tampil harus setelah awal tampil.', 'TampilSampai');
        }

        if ($data->tampilMulai->diffInDays($data->tampilSampai) > self::MAKS_HARI_TAMPIL) {
            throw new PelanggaranAturanBisnis('MasaTampilTerlaluPanjang', 'Masa tampil paling lama '.self::MAKS_HARI_TAMPIL.' hari.', 'TampilSampai');
        }

        if ($data->jenis === JenisPengumuman::Pemeliharaan) {
            if ($data->pemeliharaanMulai === null || $data->pemeliharaanSelesai === null || $data->pemeliharaanSelesai->lessThanOrEqualTo($data->pemeliharaanMulai)) {
                throw new PelanggaranAturanBisnis('JadwalPemeliharaanWajib', 'Isi jadwal pemeliharaan: selesai harus setelah mulai.', 'PemeliharaanMulai');
            }

            if ($data->tampilMulai->greaterThan($data->pemeliharaanMulai) || $data->tampilSampai->lessThan($data->pemeliharaanSelesai)) {
                throw new PelanggaranAturanBisnis('MasaTampilPemeliharaan', 'Masa tampil harus dimulai sebelum pemeliharaan dan berakhir setelah pemeliharaan selesai.', 'TampilMulai');
            }
        }

        $s = $data->sasaran;
        $paketTidakDikenal = array_diff($s['KodePaket'], Paket::query()->whereIn('Kode', $s['KodePaket'])->pluck('Kode')->all());
        $sektorTidakDikenal = array_diff($s['Sektor'], TemplateSektor::query()->whereIn('Kode', $s['Sektor'])->pluck('Kode')->all());
        $platformTidakDikenal = array_filter($s['Platform'], fn (string $p): bool => PlatformPengumuman::tryFrom($p) === null);

        if ($paketTidakDikenal !== [] || $sektorTidakDikenal !== [] || $platformTidakDikenal !== []) {
            throw new PelanggaranAturanBisnis('SasaranTidakDikenal', 'Ada paket, sektor, atau platform sasaran yang tidak dikenal.', 'Sasaran');
        }

        foreach (['VersiMinimal', 'VersiMaksimal'] as $kunci) {
            if ($s[$kunci] !== null && preg_match(self::POLA_VERSI, $s[$kunci]) !== 1) {
                throw new PelanggaranAturanBisnis('VersiTidakValid', 'Versi berformat MAJOR.MINOR.PATCH, misal 1.4.0.', "Sasaran.{$kunci}");
            }
        }

        if ($s['VersiMinimal'] !== null && $s['VersiMaksimal'] !== null && version_compare($s['VersiMinimal'], $s['VersiMaksimal']) > 0) {
            throw new PelanggaranAturanBisnis('VersiTidakValid', 'Versi minimal tidak boleh di atas versi maksimal.', 'Sasaran.VersiMaksimal');
        }
    }
}
