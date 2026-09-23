<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\PersetujuanDokumenLegal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Persetujuan ulang dokumen legal materiil (BR-P06.5).
 *
 * Owner wajib menyetujui versi yang berlaku sebuah jenis bila ada versi **materiil** jenis itu yang (a) lebih baru dari
 * versi terakhir yang ia setujui untuk tenant tersebut dan (b) mulai berlaku setelah persetujuan pertamanya di tenant
 * itu (saat registrasi). Versi materiil yang sudah berlaku sebelum Owner bergabung tidak memicu permintaan, karena
 * registrasi hanya meminta persetujuan S&K dan Kebijakan Privasi versi yang berlaku saat itu (F-00 langkah 1).
 */
final class PersetujuanLegalTertunda
{
    public function __construct(private readonly DokumenLegalBerlaku $berlaku) {}

    /**
     * @return list<DokumenLegal> versi berlaku yang harus disetujui, urut sesuai jenis
     */
    public function Ambil(int $idTenant, int $idPengguna, CarbonInterface $sekarang): array
    {
        $hariIni = $sekarang->copy()->setTimezone('Asia/Jakarta')->toDateString();
        $persetujuan = PersetujuanDokumenLegal::query()
            ->where('IdTenant', $idTenant)
            ->where('IdPengguna', $idPengguna);
        $pertama = (clone $persetujuan)->min('DisetujuiPada');
        $tanggalBergabung = is_string($pertama) ? Carbon::parse($pertama)->setTimezone('Asia/Jakarta')->toDateString() : null;
        $versiDisetujui = DokumenLegal::query()
            ->whereIn('Id', (clone $persetujuan)->select('IdDokumenLegal'))
            ->selectRaw('Jenis, MAX(Versi) AS VersiTertinggi')
            ->groupBy('Jenis')
            ->pluck('VersiTertinggi', 'Jenis')
            ->all();

        $tertunda = [];

        foreach (JenisDokumenLegal::AmbilWajibPersetujuanUlang() as $jenis) {
            $dokumen = $this->berlaku->Cari($jenis, $sekarang);
            $versiTerakhir = (int) ($versiDisetujui[$jenis->value] ?? 0);

            if ($dokumen === null || $dokumen->Versi <= $versiTerakhir) {
                continue;
            }

            $adaMateriil = DokumenLegal::query()
                ->where('Jenis', $jenis->value)
                ->where('Status', StatusDokumenLegal::Terbit->value)
                ->where('Materiil', true)
                ->where('Versi', '>', $versiTerakhir)
                ->whereDate('BerlakuMulai', '<=', $hariIni)
                ->when($tanggalBergabung !== null, fn ($kueri) => $kueri->whereDate('BerlakuMulai', '>', $tanggalBergabung))
                ->exists();

            if ($adaMateriil) {
                $tertunda[] = $dokumen;
            }
        }

        return $tertunda;
    }

    /**
     * Versi materiil yang sudah terbit tetapi belum berlaku (masa pengumuman ≥ 30 hari, BR-P06.3/BR-P06.5).
     *
     * @return list<DokumenLegal>
     */
    public function AmbilPengumuman(CarbonInterface $sekarang): array
    {
        return array_values(DokumenLegal::query()
            ->whereIn('Jenis', array_map(fn (JenisDokumenLegal $jenis) => $jenis->value, JenisDokumenLegal::AmbilWajibPersetujuanUlang()))
            ->where('Status', StatusDokumenLegal::Terbit->value)
            ->where('Materiil', true)
            ->whereDate('BerlakuMulai', '>', $sekarang->copy()->setTimezone('Asia/Jakarta')->toDateString())
            ->orderBy('BerlakuMulai')
            ->orderBy('Id')
            ->get()
            ->all());
    }
}
