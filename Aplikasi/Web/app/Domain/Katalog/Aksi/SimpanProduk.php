<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataProduk;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\SumberPerubahanKatalog;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Layanan\AturanProduk;
use App\Domain\Katalog\Layanan\PemetaGalatUnikKatalog;
use App\Domain\Katalog\Layanan\PenilaiPemakaianProduk;
use App\Domain\Katalog\Layanan\PenyelarasSatuanProduk;
use App\Domain\Katalog\Layanan\PenyusunAnakVarian;
use App\Domain\Katalog\Layanan\RingkasanAuditProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * F-03 form produk (juga dipakai impor untuk membuat produk): buat atau ubah produk lengkap dengan satuan, barcode,
 * dan harga awal satuan baru.
 * - Idempoten saat membuat: Uuid yang sudah ada di tenant ini dikembalikan apa adanya (kirim ganda aman).
 * - BR-P04.3/BR-02.1: `BatasSku` diperiksa saat membuat produk yang dihitung (bukan IndukVarian).
 * - BR-03.1: SKU unik per tenant (kosong = otomatis `PRD-000001`), barcode unik per tenant; pelanggaran indeks unik
 *   dari permintaan bersamaan dipetakan ke `BR-03.1` dan seluruh transaksi dibatalkan.
 * - Aturan jenis (C.1); IndukVarian menyimpan definisi atribut dan meneruskan kategori, merek, pajak, dan tampilan ke
 *   anak-anaknya saat diubah.
 * Urutan kunci: Tenant → Langganan (batas paket) → baris produk → satuan & barcode.
 */
final class SimpanProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianSku $pemakaianSku,
        private readonly PenilaiPemakaianProduk $penilai,
        private readonly AturanProduk $aturan,
        private readonly PenyelarasSatuanProduk $penyelaras,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?Produk $produk, DataProduk $data): Produk
    {
        $idTenant = $this->konteks->Wajib();

        try {
            return DB::transaction(fn (): Produk => $this->Simpan($idTenant, $produk, $data));
        } catch (UniqueConstraintViolationException $galat) {
            throw PemetaGalatUnikKatalog::Petakan($galat);
        }
    }

    private function Simpan(int $idTenant, ?Produk $produk, DataProduk $data): Produk
    {
        $this->penguncian->Kunci($idTenant);

        if ($produk === null) {
            $ada = Produk::query()->withTrashed()->where('Uuid', $data->uuid)->first();

            if ($ada !== null) {
                return $ada;
            }

            if ($data->jenis->CekDihitungBatasSku()) {
                $this->batasPaket->Pastikan($idTenant, 'BatasSku', fn (): int => $this->pemakaianSku->Hitung(), 1);
            }
        } else {
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();
            $this->aturan->PastikanJenisBolehDiubah($produk, $data->jenis);
            $this->PastikanSatuanDasarBolehDiubah($produk, $data->idSatuanDasar);
        }

        if ($produk?->IdInduk !== null) {
            $this->aturan->PastikanJenisAnak($data->jenis);
        }

        $nama = trim($data->nama);

        if ($nama === '' || mb_strlen($nama) > 150) {
            throw new PelanggaranAturanBisnis('NamaTidakValid', 'Nama produk wajib diisi, maksimal 150 karakter.', 'Nama');
        }

        $this->aturan->PastikanKategori($data->idKategori);
        $satuanDasar = $this->aturan->AmbilSatuan($data->idSatuanDasar);
        [$pelacakan, $bolehMinus] = $this->aturan->TentukanPelacakan($data->jenis, $data->pelacakan, $satuanDasar, $data->bolehMinus);
        // F-01 (panduan awal) boleh tanpa kelompok pajak bila template belum diterapkan; form & impor wajib.
        if ($data->sumber !== SumberPerubahanKatalog::PanduanAwal) {
            $this->aturan->PastikanKelompokPajak($data->jenis, $data->idKelompokPajak);
        }
        $atributVarian = $data->jenis === JenisProduk::IndukVarian
            ? $this->aturan->NormalisasiDefinisiVarian($data->atributVarian, $produk)
            : $produk?->AtributVarian;
        $rencanaSatuan = $this->penyelaras->Periksa($produk, $data->idSatuanDasar, $data->satuan, $data->bolehUbahHarga);
        $sku = $this->aturan->TentukanSku($data->sku, $produk?->Id);

        $lama = $produk === null ? null : RingkasanAuditProduk::Ambil($produk);
        $baru = $produk === null;
        $produk ??= new Produk(['Uuid' => $data->uuid, 'Aktif' => true]);
        $produk->fill([
            'Sku' => $sku,
            'Nama' => $nama,
            'NamaStruk' => self::KosongJadiNull($data->namaStruk),
            'Merek' => self::KosongJadiNull($data->merek),
            'Jenis' => $data->jenis,
            'AtributVarian' => $atributVarian,
            'IdKategori' => $data->idKategori,
            'IdSatuanDasar' => $data->idSatuanDasar,
            'Pelacakan' => $pelacakan,
            'IdKelompokPajak' => $data->idKelompokPajak,
            'HargaTermasukPajak' => $data->hargaTermasukPajak,
            'BolehMinus' => $bolehMinus,
            'TampilDiPos' => AturanProduk::TentukanTampilDiPos($data->jenis, $data->tampilDiPos),
            'TampilOnline' => $data->tampilOnline,
        ])->save();

        $this->penyelaras->Terapkan($produk, $rencanaSatuan, $data->sumber->value);

        if (! $baru && $produk->Jenis === JenisProduk::IndukVarian) {
            PenyusunAnakVarian::TeruskanDariInduk($produk);
        }

        if ($data->sumber !== SumberPerubahanKatalog::Impor) {
            $this->audit->Catat($baru ? 'produk.buat' : 'produk.ubah', $produk, $lama, RingkasanAuditProduk::Ambil($produk));
        }

        return $produk;
    }

    /** Satuan dasar hanya bisa diganti selama produk belum dipakai (stok, resep, varian, penjualan). */
    private function PastikanSatuanDasarBolehDiubah(Produk $produk, int $idSatuanDasar): void
    {
        if ($produk->IdSatuanDasar === $idSatuanDasar) {
            return;
        }

        $alasan = $this->penilai->AmbilAlasan($produk);

        if ($alasan !== null) {
            throw new PelanggaranAturanBisnis('SatuanDasarTerkunci', "Satuan dasar tidak bisa diganti karena produk {$alasan}.", 'UuidSatuanDasar');
        }
    }

    private static function KosongJadiNull(?string $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
