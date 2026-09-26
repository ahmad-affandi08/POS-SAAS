<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisAksiPromo;
use App\Domain\Penjualan\Enum\JenisKondisiPromo;
use App\Domain\Penjualan\Enum\JenisUlangTahunPromo;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Promo\Data\DataPromo;
use App\Domain\Promo\Model\Promo;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tambah/ubah promo (F-16c, izin `pelanggan.kelola`). Isian diperiksa sesuai aksi lalu disimpan sebagai `Definisi`
 * kanonik (dibaca `DefinisiPromo::Urai` di server & POS). Kode tidak bisa diubah. Mengubah promo berlaku untuk transaksi
 * berikutnya; transaksi offline yang memakai definisi lama ditandai tinjauan saat sinkron. Audit `promo.tambah|ubah`.
 */
final class SimpanPromo
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(DataPromo $data, ?Promo $promo = null): Promo
    {
        $definisi = self::SusunDefinisi($data);

        if ($promo === null && preg_match('/^[A-Za-z0-9_-]{1,30}$/', $data->kode) !== 1) {
            throw new PelanggaranAturanBisnis('KodeTidakValid', 'Kode promo 1–30 huruf, angka, garis bawah, atau tanda hubung.', 'Kode');
        }

        if ($data->prioritas < 0 || $data->prioritas > 999) {
            throw new PelanggaranAturanBisnis('PrioritasTidakValid', 'Prioritas 0–999.', 'Prioritas');
        }

        if ($data->mulaiPada !== null && $data->selesaiPada !== null && ! $data->selesaiPada->greaterThan($data->mulaiPada)) {
            throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Tanggal selesai harus setelah tanggal mulai.', 'SelesaiPada');
        }

        if ($data->kuota !== null && $data->kuota < 1) {
            throw new PelanggaranAturanBisnis('KuotaTidakValid', 'Kuota minimal 1 atau kosongkan untuk tanpa batas.', 'Kuota');
        }

        try {
            return DB::transaction(function () use ($data, $promo, $definisi): Promo {
                $baru = $promo === null;
                $promo ??= new Promo(['Kode' => strtoupper($data->kode)]);
                $lama = $baru ? [] : self::AmbilNilaiAudit($promo);

                if (! $baru && $data->kuota !== null && $data->kuota < $promo->KuotaTerpakai) {
                    throw new PelanggaranAturanBisnis('KuotaTidakValid', "Kuota tidak boleh kurang dari pemakaian ({$promo->KuotaTerpakai}).", 'Kuota');
                }

                $promo->fill([
                    'Nama' => trim($data->nama),
                    'Definisi' => $definisi,
                    'Prioritas' => $data->prioritas,
                    'Eksklusif' => $data->eksklusif,
                    'MulaiPada' => $data->mulaiPada,
                    'SelesaiPada' => $data->selesaiPada,
                    'Kuota' => $data->kuota,
                ]);
                $promo->save();
                $this->audit->Catat($baru ? 'promo.tambah' : 'promo.ubah', $promo, $lama, self::AmbilNilaiAudit($promo), idPengguna: $data->idPengguna);

                return $promo;
            });
        } catch (QueryException $galat) {
            if (str_contains($galat->getMessage(), 'UniqPromoIdTenantKode')) {
                throw new PelanggaranAturanBisnis('KodeSudahDipakai', 'Kode promo sudah dipakai promo lain.', 'Kode');
            }

            throw $galat;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function SusunDefinisi(DataPromo $data): array
    {
        $nama = trim($data->nama);

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw new PelanggaranAturanBisnis('NamaWajib', 'Nama promo wajib diisi (maks. 100 karakter).', 'Nama');
        }

        foreach ($data->hari as $hari) {
            if ($hari < 1 || $hari > 7) {
                throw new PelanggaranAturanBisnis('HariTidakValid', 'Hari promo tidak valid.', 'Hari');
            }
        }

        foreach (['JamMulai' => $data->jamMulai, 'JamSelesai' => $data->jamSelesai] as $bidang => $jam) {
            if ($jam !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam) !== 1) {
                throw new PelanggaranAturanBisnis('JamTidakValid', 'Jam memakai format JJ:MM (00:00–23:59).', $bidang);
            }
        }

        if ($data->minimalSubtotal->BernilaiNegatif()) {
            throw new PelanggaranAturanBisnis('MinimalSubtotalTidakValid', 'Minimal belanja tidak boleh negatif.', 'MinimalSubtotal');
        }

        if ($data->kondisi !== JenisKondisiPromo::Semua && $data->uuidKondisi === []) {
            throw new PelanggaranAturanBisnis('KondisiWajib', $data->kondisi === JenisKondisiPromo::Produk ? 'Pilih minimal satu produk.' : 'Pilih minimal satu kategori.', 'UuidKondisi');
        }

        if ($data->jumlahMinimal->BernilaiNegatif()) {
            throw new PelanggaranAturanBisnis('JumlahMinimalTidakValid', 'Jumlah minimal tidak boleh negatif.', 'JumlahMinimal');
        }

        $aksi = ['Jenis' => $data->aksi->value];
        $persenValid = fn (?BigDecimal $p): bool => $p !== null && $p->isGreaterThan(0) && $p->isLessThanOrEqualTo(100);
        $jumlahMinimal = $data->jumlahMinimal;

        switch ($data->aksi) {
            case JenisAksiPromo::DiskonPersenItem:
            case JenisAksiPromo::DiskonPersenPesanan:
                if (! $persenValid($data->persen)) {
                    throw new PelanggaranAturanBisnis('PersenTidakValid', 'Persen diskon lebih dari 0 dan paling besar 100.', 'Persen');
                }

                $aksi['Persen'] = (string) $data->persen;

                break;
            case JenisAksiPromo::DiskonTetapItem:
            case JenisAksiPromo::DiskonTetapPesanan:
                if ($data->jumlah === null || $data->jumlah->Bandingkan(Uang::Nol()) <= 0) {
                    throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Nilai potongan harus lebih dari Rp 0.', 'Jumlah');
                }

                $aksi['Jumlah'] = $data->jumlah->KeString();

                break;
            case JenisAksiPromo::HargaSpesial:
                if ($data->harga === null || $data->harga->BernilaiNegatif()) {
                    throw new PelanggaranAturanBisnis('HargaTidakValid', 'Isi harga spesial.', 'Harga');
                }

                $aksi['Harga'] = $data->harga->KeString();

                break;
            case JenisAksiPromo::BeliXGratisY:
                if ($data->beli === null || $data->beli < 1 || $data->beli > 99 || $data->gratis === null || $data->gratis < 1 || $data->gratis > 99) {
                    throw new PelanggaranAturanBisnis('BeliGratisTidakValid', 'Isi jumlah beli dan gratis (1–99).', 'Beli');
                }

                $persenGratis = $data->persenGratis ?? BigDecimal::of(100);

                if (! $persenValid($persenGratis)) {
                    throw new PelanggaranAturanBisnis('PersenTidakValid', 'Potongan barang gratis lebih dari 0 dan paling besar 100%.', 'PersenGratis');
                }

                $aksi += ['Beli' => $data->beli, 'Gratis' => $data->gratis, 'PersenGratis' => (string) $persenGratis];
                $jumlahMinimal = Kuantitas::Dari($data->beli + $data->gratis);

                break;
            case JenisAksiPromo::BundelHargaTetap:
                $isi = $data->jumlahMinimal->KeDesimal();

                if (! $isi->getFractionalPart()->isZero() || $isi->isLessThan(2) || $isi->isGreaterThan(99)) {
                    throw new PelanggaranAturanBisnis('JumlahMinimalTidakValid', 'Isi bundel 2–99 barang.', 'JumlahMinimal');
                }

                if ($data->harga === null || $data->harga->Bandingkan(Uang::Nol()) <= 0) {
                    throw new PelanggaranAturanBisnis('HargaTidakValid', 'Isi harga bundel.', 'Harga');
                }

                $aksi['Harga'] = $data->harga->KeString();

                break;
            case JenisAksiPromo::PoinBerlipat:
                // F-16c bagian 4: pengali poin > 1 sampai 10, paling banyak satu angka desimal (misal 1,5).
                if ($data->pengali === null || ! $data->pengali->isGreaterThan(1) || $data->pengali->isGreaterThan(10) || $data->pengali->strippedOfTrailingZeros()->getScale() > 1) {
                    throw new PelanggaranAturanBisnis('PengaliTidakValid', 'Pengali poin lebih dari 1 sampai 10, misal 2 atau 1,5.', 'Pengali');
                }

                $aksi['Pengali'] = (string) $data->pengali->strippedOfTrailingZeros();

                break;
        }

        $bertingkat = in_array($data->aksi, [JenisAksiPromo::BeliXGratisY, JenisAksiPromo::BundelHargaTetap], true);

        if ($data->batasPerTransaksi !== null && (! $bertingkat || $data->batasPerTransaksi < 1 || $data->batasPerTransaksi > 999)) {
            throw new PelanggaranAturanBisnis('BatasTidakValid', 'Batas per transaksi 1–999, hanya untuk Beli X gratis Y dan bundel.', 'BatasPerTransaksi');
        }

        if ($data->ulangTahun === JenisUlangTahunPromo::Rentang && ($data->hariUlangTahun < 1 || $data->hariUlangTahun > 30)) {
            throw new PelanggaranAturanBisnis('HariUlangTahunTidakValid', 'Isi jarak 1–30 hari dari hari ulang tahun.', 'HariUlangTahun');
        }

        if ($data->batasPerPelanggan !== null && ($data->batasPerPelanggan < 1 || $data->batasPerPelanggan > 999)) {
            throw new PelanggaranAturanBisnis('BatasPelangganTidakValid', 'Batas per pelanggan 1–999 kali.', 'BatasPerPelanggan');
        }

        $hari = array_values(array_unique($data->hari));
        sort($hari);

        return [
            'Hari' => $hari,
            'JamMulai' => $data->jamMulai,
            'JamSelesai' => $data->jamSelesai,
            'Outlet' => array_values(array_unique($data->uuidOutlet)),
            'Kanal' => array_values(array_unique(array_map(fn (KanalPenjualan $k): string => $k->value, $data->kanal))),
            'Tier' => array_values(array_unique($data->tier)),
            'MinimalSubtotal' => $data->minimalSubtotal->KeString(),
            'Kondisi' => [
                'Jenis' => $data->kondisi->value,
                'Uuid' => $data->kondisi === JenisKondisiPromo::Semua ? [] : array_values(array_unique($data->uuidKondisi)),
                'JumlahMinimal' => $jumlahMinimal->KeString(),
            ],
            'Aksi' => $aksi,
            'BatasPerTransaksi' => $data->batasPerTransaksi,
            ...($data->wajibVoucher ? ['WajibVoucher' => true] : []),
            // F-16c bagian 3: kunci hanya ditulis bila dipakai (promo tanpa syarat ini tetap terkirim ke aplikasi lama).
            ...($data->metodeBayar !== [] ? ['MetodeBayar' => array_values(array_unique($data->metodeBayar))] : []),
            ...($data->ulangTahun !== null ? ['UlangTahun' => [
                'Jenis' => $data->ulangTahun->value,
                ...($data->ulangTahun === JenisUlangTahunPromo::Rentang ? ['Hari' => $data->hariUlangTahun] : []),
            ]] : []),
            ...($data->transaksiPertama ? ['TransaksiPertama' => true] : []),
            ...($data->batasPerPelanggan !== null ? ['BatasPerPelanggan' => ['Jumlah' => $data->batasPerPelanggan, 'Periode' => $data->periodeBatasPelanggan->value]] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function AmbilNilaiAudit(Promo $promo): array
    {
        return [
            'Kode' => $promo->Kode,
            'Nama' => $promo->Nama,
            'Definisi' => $promo->Definisi,
            'Prioritas' => $promo->Prioritas,
            'Eksklusif' => $promo->Eksklusif,
            'MulaiPada' => $promo->MulaiPada?->toIso8601ZuluString(),
            'SelesaiPada' => $promo->SelesaiPada?->toIso8601ZuluString(),
            'Kuota' => $promo->Kuota,
        ];
    }
}
