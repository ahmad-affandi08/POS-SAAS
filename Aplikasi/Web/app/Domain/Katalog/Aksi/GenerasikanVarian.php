<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataAtributVarian;
use App\Domain\Katalog\Data\DataGenerasiVarian;
use App\Domain\Katalog\Data\HasilGenerasiVarian;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Layanan\AturanProduk;
use App\Domain\Katalog\Layanan\PemetaGalatUnikKatalog;
use App\Domain\Katalog\Layanan\PenyusunAnakVarian;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * F-03 varian: gabungkan atribut ke definisi induk lalu buat semua kombinasi yang belum ada (hasil kali Kartesius,
 * maks. 3 atribut × 20 nilai, maks. 100 kombinasi baru per panggilan). Idempoten: kombinasi yang ada dilewati.
 * `BatasSku` diperiksa untuk semua anak baru sekaligus (semua atau tidak sama sekali). Harga dasar anak lewat Tim 2
 * (sumber `Varian`, butuh izin ubah harga).
 */
final class GenerasikanVarian
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianSku $pemakaianSku,
        private readonly AturanProduk $aturan,
        private readonly PenyusunAnakVarian $penyusun,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $induk, DataGenerasiVarian $data): HasilGenerasiVarian
    {
        $idTenant = $this->konteks->Wajib();

        try {
            return DB::transaction(fn (): HasilGenerasiVarian => $this->Generasikan($idTenant, $induk, $data));
        } catch (UniqueConstraintViolationException $galat) {
            throw PemetaGalatUnikKatalog::Petakan($galat);
        }
    }

    private function Generasikan(int $idTenant, Produk $induk, DataGenerasiVarian $data): HasilGenerasiVarian
    {
        $this->penguncian->Kunci($idTenant);
        $induk = Produk::query()->whereKey($induk->Id)->lockForUpdate()->firstOrFail();

        if ($induk->Jenis !== JenisProduk::IndukVarian) {
            throw new PelanggaranAturanBisnis('JenisTidakMendukung', 'Varian hanya bisa dibuat dari produk berjenis induk varian.', 'AtributVarian');
        }

        $this->aturan->PastikanJenisAnak($data->jenisAnak, 'JenisAnak');

        if ($data->hargaDasar !== null && ! $data->bolehUbahHarga) {
            throw new PelanggaranAturanBisnis('IzinHargaDiperlukan', 'Anda tidak punya izin mengubah harga jual. Kosongkan harga, atau minta pemilik mengisinya.', 'HargaDasar');
        }

        $definisi = $this->aturan->NormalisasiDefinisiVarian($this->Gabungkan($induk, $data->atribut), $induk);
        $kunciAda = array_flip(array_map('strval', Produk::query()->where('IdInduk', $induk->Id)->whereNotNull('KunciVarian')->pluck('KunciVarian')->all()));
        $baru = [];
        $dilewati = [];

        foreach (self::HitungKombinasi($definisi) as $atribut) {
            if (isset($kunciAda[PenyusunAnakVarian::BuatKunci($atribut)])) {
                $dilewati[] = PenyusunAnakVarian::BuatNama($induk, $atribut);
            } else {
                $baru[] = $atribut;
            }
        }

        $maksimal = (int) config('katalog.Varian.MaksimalKombinasi', 100);

        if (count($baru) > $maksimal) {
            throw new PelanggaranAturanBisnis('VarianTerlaluBanyak', 'Kombinasi baru '.count($baru)." melebihi batas {$maksimal} per sekali buat. Kurangi nilai atribut.", 'AtributVarian');
        }

        if ($baru !== [] && $data->jenisAnak->CekDihitungBatasSku()) {
            $this->batasPaket->Pastikan($idTenant, 'BatasSku', fn (): int => $this->pemakaianSku->Hitung(), count($baru));
        }

        $induk->fill(['AtributVarian' => $definisi])->save();
        $dibuat = [];

        foreach ($baru as $atribut) {
            $dibuat[] = $this->penyusun->Buat($induk, $atribut, null, $data->jenisAnak, $data->hargaDasar, 'Varian')->Uuid;
        }

        $this->audit->Catat('produk.varian.generasi', $induk, null, [
            'AtributVarian' => $definisi,
            'JenisAnak' => $data->jenisAnak->value,
            'HargaDasar' => $data->hargaDasar?->KeString(),
            'JumlahDibuat' => count($dibuat),
            'JumlahDilewati' => count($dilewati),
        ]);

        return new HasilGenerasiVarian($dibuat, $dilewati);
    }

    /**
     * Definisi induk + atribut masukan (nama sama tanpa beda huruf besar/kecil = nilai digabung).
     *
     * @param  list<DataAtributVarian>  $atribut
     * @return list<DataAtributVarian>
     */
    private function Gabungkan(Produk $induk, array $atribut): array
    {
        $hasil = [];

        foreach ($induk->AtributVarian ?? [] as $lama) {
            $hasil[mb_strtolower((string) $lama['Nama'])] = new DataAtributVarian((string) $lama['Nama'], array_values(array_map('strval', (array) $lama['Nilai'])));
        }

        foreach ($atribut as $satu) {
            $kunci = mb_strtolower(trim($satu->nama));
            $hasil[$kunci] = isset($hasil[$kunci])
                ? new DataAtributVarian($hasil[$kunci]->nama, [...$hasil[$kunci]->nilai, ...$satu->nilai])
                : $satu;
        }

        return array_values($hasil);
    }

    /**
     * @param  list<array{Nama: string, Nilai: list<string>}>  $definisi
     * @return list<list<array{Nama: string, Nilai: string}>>
     */
    private static function HitungKombinasi(array $definisi): array
    {
        if ($definisi === []) {
            return [];
        }

        $hasil = [[]];

        foreach ($definisi as $atribut) {
            $berikut = [];

            foreach ($hasil as $awal) {
                foreach ($atribut['Nilai'] as $nilai) {
                    $berikut[] = [...$awal, ['Nama' => $atribut['Nama'], 'Nilai' => $nilai]];
                }
            }

            $hasil = $berikut;
        }

        return $hasil;
    }
}
