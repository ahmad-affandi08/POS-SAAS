<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Pilihan\Data\DataKelompokPilihan;
use App\Domain\Katalog\Pilihan\Data\DataPilihan;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tambah atau ubah kelompok pilihan (modifier) beserta seluruh pilihannya (F-03 C.4).
 * - Nama kelompok unik per tenant (`KelompokPilihanGanda`); 1–50 pilihan dengan nama unik di kelompok (`PilihanGanda`).
 * - `0 ≤ MinimalPilih ≤ MaksimalPilih`, `1 ≤ MaksimalPilih ≤ 20`, `MinimalPilih ≤ jumlah pilihan aktif`
 *   (`BatasPilihanTidakValid`).
 * - Bahan pilihan harus jenis yang boleh jadi bahan (`CekBolehBahan`) milik tenant ini, dengan `Jumlah > 0`
 *   (`BahanTidakValid`).
 * - Harga pilihan baru selain 0, atau harga pilihan lama yang berubah, memerlukan izin `produk.harga.ubah`
 *   (`IzinHargaDiperlukan`).
 * - Pilihan diganti per Uuid: diperbarui, ditambah, atau dihapus (jejak `Pilihan` untuk sinkron POS).
 * Kirim ganda aman: tenant dikunci, sehingga kiriman kedua melihat nama yang sudah ada.
 */
final class SimpanKelompokPilihan
{
    public const MAKSIMAL_PILIH = 20;

    public const MAKSIMAL_JUMLAH_PILIHAN = 50;

    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatPenghapusanKatalog $penghapusan,
        private readonly KonteksTenant $konteks,
    ) {}

    public function Jalankan(?KelompokPilihan $kelompok, DataKelompokPilihan $data): KelompokPilihan
    {
        try {
            return DB::transaction(function () use ($kelompok, $data): KelompokPilihan {
                $this->penguncian->Kunci($this->konteks->Wajib());
                $kelompok = $kelompok === null ? null : KelompokPilihan::query()->lockForUpdate()->findOrFail($kelompok->Id);
                $nama = trim($data->nama);
                $lama = KelompokPilihan::query()->where('Nama', $nama)->when($kelompok !== null, fn ($kueri) => $kueri->whereKeyNot($kelompok?->Id))->exists();

                if ($lama) {
                    throw new PelanggaranAturanBisnis('KelompokPilihanGanda', "Kelompok pilihan {$nama} sudah ada.", 'Nama');
                }

                $this->PeriksaBatas($data);
                $pilihanLama = $kelompok === null
                    ? collect()
                    : Pilihan::query()->where('IdKelompokPilihan', $kelompok->Id)->orderBy('Urutan')->orderBy('Id')->lockForUpdate()->get()->keyBy('Uuid');
                $this->PeriksaPilihan($data, $pilihanLama->all());
                $sebelum = $kelompok === null ? null : $this->AmbilRingkasan($kelompok, array_values($pilihanLama->all()));

                if ($kelompok === null) {
                    $kelompok = KelompokPilihan::query()->create([
                        'Nama' => $nama,
                        'MinimalPilih' => $data->minimalPilih,
                        'MaksimalPilih' => $data->maksimalPilih,
                        'Urutan' => $data->urutan,
                    ]);
                } else {
                    $kelompok->fill(['Nama' => $nama, 'MinimalPilih' => $data->minimalPilih, 'MaksimalPilih' => $data->maksimalPilih, 'Urutan' => $data->urutan])->save();
                }

                $this->GantiPilihan($kelompok, $data->pilihan, $pilihanLama->all());
                $pilihanBaru = Pilihan::query()->where('IdKelompokPilihan', $kelompok->Id)->orderBy('Urutan')->orderBy('Id')->get();
                $sesudah = $this->AmbilRingkasan($kelompok, array_values($pilihanBaru->all()));

                if ($sebelum === null) {
                    $this->audit->Catat('kelompok-pilihan.buat', $kelompok, nilaiBaru: $sesudah);
                } elseif ($sebelum !== $sesudah) {
                    $this->audit->Catat('kelompok-pilihan.ubah', $kelompok, nilaiLama: $sebelum, nilaiBaru: $sesudah);
                }

                return $kelompok;
            });
        } catch (UniqueConstraintViolationException $galat) {
            if (str_contains($galat->getMessage(), 'UniqKelompokPilihanIdTenantNama')) {
                throw new PelanggaranAturanBisnis('KelompokPilihanGanda', 'Nama kelompok pilihan sudah dipakai.', 'Nama');
            }

            throw new PelanggaranAturanBisnis('PilihanGanda', 'Nama pilihan dalam satu kelompok harus berbeda.', 'Pilihan');
        }
    }

    private function PeriksaBatas(DataKelompokPilihan $data): void
    {
        $jumlahPilihan = count($data->pilihan);

        if ($jumlahPilihan < 1 || $jumlahPilihan > self::MAKSIMAL_JUMLAH_PILIHAN) {
            throw new PelanggaranAturanBisnis('BatasPilihanTidakValid', 'Isi 1 sampai '.self::MAKSIMAL_JUMLAH_PILIHAN.' pilihan.', 'Pilihan');
        }

        if ($data->maksimalPilih < 1 || $data->maksimalPilih > self::MAKSIMAL_PILIH) {
            throw new PelanggaranAturanBisnis('BatasPilihanTidakValid', 'Maksimal pilih harus 1 sampai '.self::MAKSIMAL_PILIH.'.', 'MaksimalPilih');
        }

        if ($data->minimalPilih < 0 || $data->minimalPilih > $data->maksimalPilih) {
            throw new PelanggaranAturanBisnis('BatasPilihanTidakValid', 'Minimal pilih tidak boleh lebih dari maksimal pilih.', 'MinimalPilih');
        }

        $aktif = count(array_filter($data->pilihan, fn (DataPilihan $pilihan): bool => $pilihan->aktif));

        if ($data->minimalPilih > $aktif) {
            throw new PelanggaranAturanBisnis('BatasPilihanTidakValid', "Minimal pilih {$data->minimalPilih}, tetapi pilihan aktif hanya {$aktif}.", 'MinimalPilih');
        }
    }

    /**
     * @param  array<string, Pilihan>  $pilihanLama  kunci = Uuid
     */
    private function PeriksaPilihan(DataKelompokPilihan $data, array $pilihanLama): void
    {
        $namaTerpakai = [];
        $idBahan = array_values(array_unique(array_filter(array_map(fn (DataPilihan $pilihan): ?int => $pilihan->idProdukBahan, $data->pilihan), fn (?int $id): bool => $id !== null)));
        $bahan = Produk::query()->whereKey($idBahan)->get(['Id', 'Nama', 'Jenis'])->keyBy('Id');

        foreach ($data->pilihan as $i => $pilihan) {
            $nama = trim($pilihan->nama);
            $kunci = mb_strtolower($nama);

            if ($nama === '') {
                throw new PelanggaranAturanBisnis('BatasPilihanTidakValid', 'Nama pilihan wajib diisi.', "Pilihan.{$i}.Nama");
            }

            if (isset($namaTerpakai[$kunci])) {
                throw new PelanggaranAturanBisnis('PilihanGanda', "Pilihan {$nama} tercantum lebih dari sekali.", "Pilihan.{$i}.Nama");
            }

            $namaTerpakai[$kunci] = true;

            if ($pilihan->harga->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga pilihan tidak boleh negatif.', "Pilihan.{$i}.Harga");
            }

            $lama = $pilihan->uuid === null ? null : ($pilihanLama[$pilihan->uuid] ?? null);
            $hargaBerubah = $lama === null ? ! $pilihan->harga->BernilaiNol() : $lama->Harga !== $pilihan->harga->KeString();

            if ($hargaBerubah && ! $data->bolehUbahHarga) {
                throw new PelanggaranAturanBisnis('IzinHargaDiperlukan', 'Anda tidak punya izin mengubah harga. Minta pemilik atau admin mengubah harga pilihan.', "Pilihan.{$i}.Harga");
            }

            if ($pilihan->idProdukBahan === null) {
                continue;
            }

            $produkBahan = $bahan->get($pilihan->idProdukBahan);

            if (! $produkBahan instanceof Produk || ! $produkBahan->Jenis->CekBolehBahan()) {
                throw new PelanggaranAturanBisnis('BahanTidakValid', 'Bahan harus berupa bahan baku, barang stok, atau produk produksi.', "Pilihan.{$i}.UuidProdukBahan");
            }

            if ($pilihan->jumlah === null || $pilihan->jumlah->Bandingkan(Kuantitas::Nol()) <= 0) {
                throw new PelanggaranAturanBisnis('BahanTidakValid', "Isi jumlah {$produkBahan->Nama} yang dipakai, lebih dari 0.", "Pilihan.{$i}.Jumlah");
            }
        }
    }

    /**
     * @param  list<DataPilihan>  $daftar
     * @param  array<string, Pilihan>  $pilihanLama  kunci = Uuid
     */
    private function GantiPilihan(KelompokPilihan $kelompok, array $daftar, array $pilihanLama): void
    {
        $dipertahankan = [];

        foreach ($daftar as $pilihan) {
            if ($pilihan->uuid !== null && isset($pilihanLama[$pilihan->uuid])) {
                $dipertahankan[$pilihan->uuid] = true;
            }
        }

        foreach ($pilihanLama as $uuid => $lama) {
            if (! isset($dipertahankan[$uuid])) {
                $this->penghapusan->Catat(EntitasKatalog::Pilihan, $lama->Uuid);
                $lama->delete();
            }
        }

        // Nama sementara dulu agar tukar nama antarpilihan tidak bentrok dengan indeks unik.
        foreach ($daftar as $pilihan) {
            $lama = $pilihan->uuid === null ? null : ($pilihanLama[$pilihan->uuid] ?? null);

            if ($lama !== null && $lama->Nama !== trim($pilihan->nama)) {
                $lama->forceFill(['Nama' => '#'.$lama->Uuid])->save();
            }
        }

        foreach ($daftar as $urutan => $pilihan) {
            $isian = [
                'Nama' => trim($pilihan->nama),
                'Harga' => $pilihan->harga->KeString(),
                'Aktif' => $pilihan->aktif,
                'IdProduk' => $pilihan->idProdukBahan,
                'Jumlah' => $pilihan->idProdukBahan === null ? null : $pilihan->jumlah?->KeString(),
                'Urutan' => $urutan,
            ];
            $lama = $pilihan->uuid === null ? null : ($pilihanLama[$pilihan->uuid] ?? null);

            if ($lama !== null) {
                $lama->fill($isian)->save();

                continue;
            }

            Pilihan::query()->create(['IdKelompokPilihan' => $kelompok->Id, ...$isian]);
        }
    }

    /**
     * @param  list<Pilihan>  $pilihan
     * @return array<string, mixed>
     */
    private function AmbilRingkasan(KelompokPilihan $kelompok, array $pilihan): array
    {
        return [
            'Nama' => $kelompok->Nama,
            'MinimalPilih' => $kelompok->MinimalPilih,
            'MaksimalPilih' => $kelompok->MaksimalPilih,
            'Urutan' => $kelompok->Urutan,
            'Pilihan' => array_map(fn (Pilihan $baris): array => [
                'Uuid' => $baris->Uuid,
                'Nama' => $baris->Nama,
                'Harga' => $baris->Harga,
                'Aktif' => $baris->Aktif,
                'IdProduk' => $baris->IdProduk,
                'Jumlah' => $baris->Jumlah,
            ], $pilihan),
        ];
    }
}
