<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Mitra\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusKomisiMitra;
use App\Domain\Tenant\Model\KomisiMitra;
use App\Domain\Tenant\Model\Mitra;
use App\Domain\Tenant\Model\PencairanKomisi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P-12 langkah 5: Keuangan mencatat pencairan komisi bulanan satu mitra. Yang dicairkan = semua komisi **Tertunda**
 * yang tercatat sampai akhir `Periode` (TTTT-BB, WIB) — komisi bulan lalu yang tertinggal ikut, komisi bulan
 * berikutnya tidak. Potongan pajak diisi sesuai bukti potong (K24: perlakuan PPh 21/23 menunggu keputusan),
 * tidak boleh melebihi total. Satu pencairan per mitra per periode; komisinya menjadi Dibayar dan tertaut.
 */
final class CatatPencairanKomisi
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, Mitra $mitra, string $periode, string $potonganPajak, string $dibayarPada, ?string $catatan): PencairanKomisi
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode berformat TTTT-BB.', 'Periode');
        }

        $akhir = CarbonImmutable::parse("{$periode}-01", 'Asia/Jakarta')->endOfMonth();

        if ($akhir->startOfMonth()->isFuture()) {
            throw new PelanggaranAturanBisnis('PeriodeBelumMulai', 'Periode pencairan belum dimulai.', 'Periode');
        }

        return DB::transaction(function () use ($pelaku, $mitra, $periode, $potonganPajak, $dibayarPada, $catatan, $akhir): PencairanKomisi {
            if (PencairanKomisi::query()->where('IdMitra', $mitra->Id)->where('Periode', $periode)->exists()) {
                throw new PelanggaranAturanBisnis('PencairanSudahAda', "Pencairan {$periode} untuk mitra ini sudah dicatat.", 'Periode');
            }

            $komisi = KomisiMitra::query()
                ->where('IdMitra', $mitra->Id)
                ->where('Status', StatusKomisiMitra::Tertunda->value)
                ->where('DibuatPada', '<=', $akhir->utc())
                ->lockForUpdate()
                ->get();
            $total = $komisi->reduce(fn (Uang $jumlah, KomisiMitra $k): Uang => $jumlah->Tambah(Uang::Dari($k->Jumlah)), Uang::Nol());

            if ($total->BernilaiNol()) {
                throw new PelanggaranAturanBisnis('TidakAdaKomisi', "Tidak ada komisi tertunda sampai akhir {$periode}.", 'Periode');
            }

            $potongan = Uang::Dari($potonganPajak);

            if ($potongan->BernilaiNegatif() || $potongan->Bandingkan($total) > 0) {
                throw new PelanggaranAturanBisnis('PotonganTidakValid', 'Potongan pajak antara 0 dan total komisi.', 'PotonganPajak');
            }

            $pencairan = PencairanKomisi::query()->create([
                'IdMitra' => $mitra->Id,
                'Periode' => $periode,
                'Total' => $total->KeString(),
                'PotonganPajak' => $potongan->KeString(),
                'JumlahBersih' => $total->Kurangi($potongan)->KeString(),
                'DibayarPada' => $dibayarPada,
                'Catatan' => $catatan === null || trim($catatan) === '' ? null : trim($catatan),
                'DibuatOleh' => $pelaku->Id,
            ]);

            foreach ($komisi as $k) {
                $k->UbahStatus(StatusKomisiMitra::Dibayar);
                $k->IdPencairanKomisi = $pencairan->Id;
                $k->save();
            }

            $this->audit->Catat('mitra.pencairan', $pencairan, nilaiBaru: [
                'Mitra' => $mitra->Kode, 'Periode' => $periode, 'Total' => $pencairan->Total,
                'PotonganPajak' => $pencairan->PotonganPajak, 'JumlahKomisi' => $komisi->count(),
            ], idPelaku: $pelaku->Id);

            return $pencairan;
        });
    }
}
