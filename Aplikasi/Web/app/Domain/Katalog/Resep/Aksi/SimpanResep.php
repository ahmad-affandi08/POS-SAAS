<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Resep\Data\DataBahanResep;
use App\Domain\Katalog\Resep\Data\DataResep;
use App\Domain\Katalog\Resep\Layanan\PemeriksaSiklusResep;
use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan resep/BOM produk sebagai versi baru (BR-03.4, F-03 C.4). Versi lama tidak pernah diubah atau dihapus.
 * - Hanya produk jenis Resep/Produksi (`JenisTidakPunyaResep`); 1–100 bahan (`ResepKosong`); `JumlahHasil > 0`
 *   (`JumlahHasilTidakValid`).
 * - Bahan: milik tenant ini, jenis yang boleh jadi bahan, bukan produk itu sendiri, satuannya salah satu satuan
 *   produk bahan, jumlah > 0, `0 ≤ PersenSusut < 100` (`BahanTidakValid`); tanpa resep melingkar (`ResepSiklus`).
 * - `JumlahDasar` = Jumlah × KonversiKeDasar satuan bahan saat ini (HalfUp, 4 desimal), disimpan sebagai snapshot.
 * - Isian yang sama persis dengan versi terbaru mengembalikan versi itu (idempoten, kirim ganda aman).
 */
final class SimpanResep
{
    public const MAKSIMAL_BAHAN = 100;

    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PenguncianTenant $penguncian,
        private readonly PemeriksaSiklusResep $siklus,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @param  int|null  $idPembuat  pengguna pembuat versi (`Resep.DibuatOleh`)
     */
    public function Jalankan(Produk $produk, DataResep $data, ?int $idPembuat = null): Resep
    {
        try {
            return DB::transaction(function () use ($produk, $data, $idPembuat): Resep {
                $this->penguncian->Kunci($this->konteks->Wajib());
                $produk = Produk::query()->lockForUpdate()->findOrFail($produk->Id);

                if (! $produk->Jenis->CekBolehResep()) {
                    throw new PelanggaranAturanBisnis('JenisTidakPunyaResep', "Produk jenis {$produk->Jenis->AmbilLabel()} tidak memakai resep. Ubah jenisnya ke Resep atau Produksi.", 'Umum');
                }

                $baris = $this->SusunBaris($produk, $data);

                if ($this->siklus->CekSiklus($produk->Id, array_map(fn (array $bahan): int => $bahan['IdProdukBahan'], $baris))) {
                    throw new PelanggaranAturanBisnis('ResepSiklus', "Resep melingkar: salah satu bahan (atau bahan dari bahannya) memakai {$produk->Nama}.", 'Bahan');
                }

                $catatan = $data->catatan === null || trim($data->catatan) === '' ? null : trim($data->catatan);
                $terbaru = Resep::query()->where('IdProduk', $produk->Id)->orderByDesc('Versi')->first();

                if ($terbaru !== null && $this->CekSamaDengan($terbaru, $data->jumlahHasil, $catatan, $baris)) {
                    return $terbaru;
                }

                $resep = Resep::query()->create([
                    'IdProduk' => $produk->Id,
                    'Versi' => ($terbaru === null ? 0 : $terbaru->Versi) + 1,
                    'JumlahHasil' => $data->jumlahHasil->KeString(),
                    'Catatan' => $catatan,
                    'DibuatOleh' => $idPembuat,
                ]);

                foreach ($baris as $urutan => $bahan) {
                    ResepDetail::query()->create(['IdResep' => $resep->Id, ...$bahan, 'Urutan' => $urutan]);
                }

                $this->audit->Catat('produk.resep.buat-versi', $resep, nilaiLama: $terbaru === null ? null : ['Versi' => $terbaru->Versi], nilaiBaru: [
                    'IdProduk' => $produk->Id,
                    'Versi' => $resep->Versi,
                    'JumlahHasil' => $resep->JumlahHasil,
                    'Catatan' => $catatan,
                    'Bahan' => $baris,
                ]);

                return $resep;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('ResepGanda', 'Resep sedang disimpan dari tempat lain. Muat ulang halaman lalu coba lagi.', 'Umum');
        }
    }

    /**
     * @return list<array{IdProdukBahan: int, Jumlah: string, IdSatuan: int, JumlahDasar: string, PersenSusut: string}>
     */
    private function SusunBaris(Produk $produk, DataResep $data): array
    {
        if ($data->jumlahHasil->Bandingkan(Kuantitas::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('JumlahHasilTidakValid', 'Jumlah hasil resep harus lebih dari 0.', 'JumlahHasil');
        }

        if ($data->bahan === [] || count($data->bahan) > self::MAKSIMAL_BAHAN) {
            throw new PelanggaranAturanBisnis('ResepKosong', 'Isi 1 sampai '.self::MAKSIMAL_BAHAN.' bahan resep.', 'Bahan');
        }

        $idBahan = array_values(array_unique(array_map(fn (DataBahanResep $bahan): int => $bahan->idProdukBahan, $data->bahan)));
        $daftarProduk = Produk::query()->whereKey($idBahan)->get(['Id', 'Nama', 'Jenis'])->keyBy('Id');
        $daftarSatuan = ProdukSatuan::query()->whereIn('IdProduk', $idBahan)->get(['IdProduk', 'IdSatuan', 'KonversiKeDasar'])
            ->keyBy(fn (ProdukSatuan $satuan): string => $satuan->IdProduk.'-'.$satuan->IdSatuan);
        $baris = [];

        foreach ($data->bahan as $i => $bahan) {
            $produkBahan = $daftarProduk->get($bahan->idProdukBahan);

            if (! $produkBahan instanceof Produk || ! $produkBahan->Jenis->CekBolehBahan()) {
                throw new PelanggaranAturanBisnis('BahanTidakValid', 'Bahan harus berupa bahan baku, barang stok, atau produk produksi.', "Bahan.{$i}.UuidProdukBahan");
            }

            if ($produkBahan->Id === $produk->Id) {
                throw new PelanggaranAturanBisnis('BahanTidakValid', 'Produk tidak bisa menjadi bahan resepnya sendiri.', "Bahan.{$i}.UuidProdukBahan");
            }

            $satuan = $daftarSatuan->get($bahan->idProdukBahan.'-'.$bahan->idSatuan);

            if (! $satuan instanceof ProdukSatuan) {
                throw new PelanggaranAturanBisnis('BahanTidakValid', "Pilih satuan yang terdaftar di produk {$produkBahan->Nama}.", "Bahan.{$i}.UuidSatuan");
            }

            if ($bahan->jumlah->Bandingkan(Kuantitas::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('BahanTidakValid', "Jumlah {$produkBahan->Nama} harus lebih dari 0.", "Bahan.{$i}.Jumlah");
            }

            $baris[] = [
                'IdProdukBahan' => $produkBahan->Id,
                'Jumlah' => $bahan->jumlah->KeString(),
                'IdSatuan' => $bahan->idSatuan,
                'JumlahDasar' => $bahan->jumlah->Kali($satuan->KonversiKeDasar)->KeString(),
                'PersenSusut' => $this->UraiPersenSusut($bahan->persenSusut, "Bahan.{$i}.PersenSusut"),
            ];
        }

        return $baris;
    }

    /** Persen susut 0 ≤ x < 100 (H7 disetujui: jumlah kotor = JumlahDasar ÷ (1 − x/100)), maksimal 6 desimal. */
    private function UraiPersenSusut(string $nilai, string $bidang): string
    {
        try {
            $persen = BigDecimal::of(trim($nilai) === '' ? '0' : trim($nilai));
        } catch (MathException) {
            throw new PelanggaranAturanBisnis('BahanTidakValid', 'Susut harus berupa angka persen.', $bidang);
        }

        if ($persen->getScale() > 6 || $persen->isNegative() || $persen->isGreaterThanOrEqualTo(100)) {
            throw new PelanggaranAturanBisnis('BahanTidakValid', 'Susut harus 0 sampai kurang dari 100 persen, maksimal 6 desimal.', $bidang);
        }

        return (string) $persen->toScale(6);
    }

    /**
     * @param  list<array{IdProdukBahan: int, Jumlah: string, IdSatuan: int, JumlahDasar: string, PersenSusut: string}>  $baris
     */
    private function CekSamaDengan(Resep $terbaru, Kuantitas $jumlahHasil, ?string $catatan, array $baris): bool
    {
        if ($terbaru->JumlahHasil !== $jumlahHasil->KeString() || $terbaru->Catatan !== $catatan) {
            return false;
        }

        $lama = ResepDetail::query()->where('IdResep', $terbaru->Id)->orderBy('Urutan')->get()
            ->map(fn (ResepDetail $detail): array => [$detail->IdProdukBahan, $detail->Jumlah, $detail->IdSatuan, $detail->PersenSusut])
            ->all();
        $baru = array_map(fn (array $bahan): array => [$bahan['IdProdukBahan'], $bahan['Jumlah'], $bahan['IdSatuan'], $bahan['PersenSusut']], $baris);

        return $lama === $baru;
    }
}
