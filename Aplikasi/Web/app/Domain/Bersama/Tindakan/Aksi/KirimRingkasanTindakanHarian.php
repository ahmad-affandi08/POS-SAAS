<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Aksi;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kueri\KotakTindakan;
use App\Domain\Bersama\Tindakan\Model\LanggananRingkasanTindakan;
use App\Domain\Bersama\Tindakan\Surel\RingkasanTindakanHarian;
use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Organisasi\Kueri\KontakAnggotaTenant;
use App\Domain\Organisasi\Kueri\KonteksTindakanPengguna;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * D-23 D bagian 4: kirim ringkasan pagi Kotak Tindakan ke anggota tenant aktif yang berlangganan (Owner bawaan
 * berlangganan, anggota lain memilih sendiri). Isi = butir Penting & Perhatian menurut izin & outlet akses penerima
 * (hutang/piutang jatuh tempo, shift lupa ditutup, dokumen perlu dicek, dst.). Tidak ada butir = tidak dikirim.
 * Paling banyak sekali per tanggal bisnis per penerima (`TerakhirDikirim`). Dipanggil dalam konteks tenant.
 */
final class KirimRingkasanTindakanHarian
{
    public function __construct(
        private readonly KontakAnggotaTenant $kontak,
        private readonly KonteksTindakanPengguna $konteks,
        private readonly KotakTindakan $kotak,
        private readonly ProfilTenant $profil,
    ) {}

    /** @return int jumlah email terkirim */
    public function Jalankan(int $idTenant, CarbonImmutable $hariIni): int
    {
        $langganan = LanggananRingkasanTindakan::query()->get()->keyBy('IdPengguna');
        $namaUsaha = $this->profil->Ambil($idTenant)['Nama'];
        $tanggal = $hariIni->toDateString();
        $terkirim = 0;

        foreach ($this->kontak->Ambil($idTenant) as $anggota) {
            $baris = $langganan->get($anggota['Id']);

            if (! ($baris->Aktif ?? $anggota['Pemilik']) || $baris?->TerakhirDikirim?->toDateString() === $tanggal) {
                continue;
            }

            $butir = array_values(array_filter(
                $this->kotak->Ambil($this->konteks->Buat($idTenant, $anggota['Id'], $hariIni)),
                fn (DataButirTindakan $b): bool => $b->tingkat !== TingkatTindakan::Info,
            ));

            if ($butir === []) {
                continue;
            }

            try {
                Mail::to($anggota['Email'])->send(new RingkasanTindakanHarian(
                    $anggota['Nama'],
                    $namaUsaha,
                    $hariIni->translatedFormat('j F Y'),
                    array_map(fn (DataButirTindakan $b): array => [
                        'Tingkat' => $b->tingkat->value,
                        'Judul' => $b->judul,
                        'Jumlah' => $b->jumlah,
                        'Keterangan' => $b->keterangan,
                        'Tautan' => AlamatDomain::BuatUrlAbsolutTenant($b->tautan),
                    ], $butir),
                    AlamatDomain::BuatUrlAbsolutTenant('/kelola/tindakan'),
                ));
            } catch (Throwable $galat) {
                // Transport email gagal: dicoba lagi pada putaran berikutnya (belum ditandai terkirim).
                report($galat);

                continue;
            }

            $baris ??= new LanggananRingkasanTindakan(['IdPengguna' => $anggota['Id'], 'Aktif' => true]);
            $baris->setAttribute('TerakhirDikirim', $tanggal);
            $baris->save();
            $terkirim++;
        }

        return $terkirim;
    }
}
