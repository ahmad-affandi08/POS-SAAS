<?php

declare(strict_types=1);

namespace App\Domain\Situs\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Situs\Enum\JenisProspek;
use App\Domain\Situs\Enum\StatusProspek;
use App\Domain\Situs\Kueri\PengaturanSitusBerlaku;
use App\Domain\Situs\Model\ProspekSitus;
use App\Domain\Situs\Surel\ProspekSitusBaru;
use Illuminate\Support\Facades\Mail;

/**
 * Situs pemasaran bagian B (§13.9): menerima isian formulir kontak/minta demo dari pengunjung (sudah divalidasi &
 * disetujui pemakaian datanya). Nomor dinormalisasi `62…`; paling banyak 3 prospek per nomor per 24 jam (selebihnya
 * ditolak ramah). Tim dikabari lewat email (antrean) ke `Prospek.EmailNotifikasi` atau email kontak situs.
 *
 * @phpstan-type Isian array{Jenis: JenisProspek, Nama: string, NamaUsaha: string|null, NoHp: string, Email: string|null, JenisUsaha: string|null, Kota: string|null, Pesan: string|null, HalamanAsal: string|null}
 */
final class TerimaProspekSitus
{
    public const BATAS_PER_NOMOR = 3;

    public function __construct(private readonly PengaturanSitusBerlaku $pengaturan) {}

    /**
     * @param  Isian  $isian
     */
    public function Jalankan(array $isian, string $ip): ProspekSitus
    {
        $noHp = NomorHp::Normalisasi($isian['NoHp']);

        if ($noHp === null) {
            throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Tulis nomor WhatsApp yang aktif, misalnya 0812 3456 7890.', 'NoHp');
        }

        $sidikNoHp = ProspekSitus::BuatSidik($noHp);

        if (ProspekSitus::query()->where('SidikNoHp', $sidikNoHp)->where('DibuatPada', '>=', now()->subDay())->count() >= self::BATAS_PER_NOMOR) {
            throw new PelanggaranAturanBisnis('SudahDikirim', 'Pesan dari nomor ini sudah kami terima. Tim kami akan segera menghubungi Anda.', 'NoHp', 429);
        }

        $p = new ProspekSitus;
        $p->Jenis = $isian['Jenis'];
        $p->Nama = $isian['Nama'];
        $p->NamaUsaha = $isian['NamaUsaha'];
        $p->NoHp = $noHp;
        $p->SidikNoHp = $sidikNoHp;
        $p->Email = $isian['Email'] === null ? null : mb_strtolower($isian['Email']);
        $p->JenisUsaha = $isian['JenisUsaha'];
        $p->Kota = $isian['Kota'];
        $p->Pesan = $isian['Pesan'];
        $p->HalamanAsal = $isian['HalamanAsal'];
        $p->Status = StatusProspek::Baru;
        $p->PersetujuanPada = now();
        $p->SidikIp = ProspekSitus::BuatSidik($ip);
        $p->save();

        $pengaturan = $this->pengaturan->Ambil();
        $tujuan = $pengaturan['Prospek']['EmailNotifikasi'] ?? $pengaturan['Kontak']['Email'] ?? null;

        if (is_string($tujuan) && $tujuan !== '') {
            Mail::to($tujuan)->queue(new ProspekSitusBaru(
                $p->Jenis->AmbilLabel(),
                $p->Nama,
                $p->NamaUsaha,
                $p->JenisUsaha,
                $p->Kota,
                AlamatDomain::BuatUrl((string) config('pengelola.Domain'), '/situs/prospek'),
            ));
        }

        return $p;
    }
}
