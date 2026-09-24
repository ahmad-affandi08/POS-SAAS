<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Persediaan\Model\MutasiStok;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kartu stok satu (produk, lokasi stok) (BR-05.1, DesainF05a C.8, H-5; tipe FE `PropsKartuStok` bagian `SaldoAwal`,
 * `SaldoAkhir`, `Mutasi`).
 *
 * Baris urut `Id` (urutan pencatatan), disaring `TanggalBisnis` dalam [dari, sampai]. Saldo awal = SaldoSetelah/
 * NilaiSetelah baris terakhir sebelum baris pertama dalam rentang; tanpa baris dalam rentang = baris terakhir yang
 * bertanggal sebelum `dari`. Saldo akhir = baris terakhir dalam rentang (atau sama dengan saldo awal). Tanpa riwayat
 * = nol.
 */
final class KartuStok
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * `Mutasi` berkontrak `TabelData` (D-16) `{Data, Meta}`, urut kronologis (saldo berjalan tidak bisa diurut ulang).
     *
     * @return array{SaldoAwal: array{Jumlah: string, Nilai: string}, SaldoAkhir: array{Jumlah: string, Nilai: string}, Mutasi: array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}}
     */
    public function Ambil(int $idProduk, int $idGudang, CarbonImmutable $dari, CarbonImmutable $sampai, int $halaman, int $perHalaman = 100): array
    {
        $pasangan = fn (): Builder => MutasiStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang);
        $rentang = fn (): Builder => $pasangan()->whereBetween('TanggalBisnis', [$dari->toDateString(), $sampai->toDateString()]);

        $idPertama = $rentang()->min('Id');
        $idTerakhir = $rentang()->max('Id');

        $sebelum = is_numeric($idPertama)
            ? $pasangan()->where('Id', '<', (int) $idPertama)->orderByDesc('Id')->first()
            : $pasangan()->where('TanggalBisnis', '<', $dari->toDateString())->orderByDesc('Id')->first();
        $saldoAwal = self::PetakanSaldo($sebelum);
        $saldoAkhir = is_numeric($idTerakhir) ? self::PetakanSaldo($pasangan()->whereKey((int) $idTerakhir)->first()) : $saldoAwal;

        $halamanMutasi = $rentang()
            ->with(['BatchStok:Id,NomorBatch', 'NomorSeri:Id,Nomor'])
            ->orderBy('Id')
            ->paginate(max(1, $perHalaman), ['*'], 'halaman', max(1, $halaman));

        /** @var list<MutasiStok> $baris */
        $baris = array_values($halamanMutasi->items());
        $idPengguna = array_values(array_unique(array_map('intval', array_filter(array_map(fn (MutasiStok $m): mixed => $m->DibuatOleh, $baris), 'is_numeric'))));
        $nama = $idPengguna === [] ? [] : $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $idPengguna);

        return [
            'SaldoAwal' => $saldoAwal,
            'SaldoAkhir' => $saldoAkhir,
            'Mutasi' => PenerapKueriTabel::DariPaginator($halamanMutasi, fn (array $isi): array => array_map(fn (MutasiStok $m): array => self::PetakanBaris($m, $nama), $isi)),
        ];
    }

    /**
     * @return array{Jumlah: string, Nilai: string}
     */
    private static function PetakanSaldo(?MutasiStok $mutasi): array
    {
        return $mutasi === null
            ? ['Jumlah' => '0.0000', 'Nilai' => '0.00']
            : ['Jumlah' => $mutasi->SaldoSetelah, 'Nilai' => $mutasi->NilaiSetelah];
    }

    /**
     * Baris tipe FE `BarisKartuStok`: jumlah positif di `Masuk`, negatif (tanpa tanda) di `Keluar`.
     *
     * @param  array<int, string>  $nama
     * @return array<string, mixed>
     */
    private static function PetakanBaris(MutasiStok $m, array $nama): array
    {
        $jumlah = BigDecimal::of($m->Jumlah);
        $oleh = $m->DibuatOleh;

        return [
            'TanggalBisnis' => $m->TanggalBisnis->toDateString(),
            'DicatatPada' => $m->DibuatPada?->toIso8601String(),
            'JenisMutasi' => $m->JenisMutasi->value,
            'LabelJenisMutasi' => $m->JenisMutasi->AmbilLabel(),
            'NomorReferensi' => $m->NomorReferensi,
            'TautanReferensi' => $m->JenisReferensi->BuatTautan($m->UuidReferensi),
            'Masuk' => $jumlah->isNegative() ? null : $m->Jumlah,
            'Keluar' => $jumlah->isNegative() ? (string) $jumlah->abs() : null,
            'HppSatuan' => $m->HppSatuan,
            'TotalHpp' => $m->TotalHpp,
            'SaldoSetelah' => $m->SaldoSetelah,
            'NilaiSetelah' => $m->NilaiSetelah,
            'NomorBatch' => $m->BatchStok?->NomorBatch,
            'NomorSeri' => $m->NomorSeri?->Nomor,
            'DicatatOleh' => is_numeric($oleh) ? ($nama[(int) $oleh] ?? null) : null,
        ];
    }
}
