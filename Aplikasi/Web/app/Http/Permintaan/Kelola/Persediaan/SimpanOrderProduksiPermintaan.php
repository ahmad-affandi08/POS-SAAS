<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Persediaan\Data\DataOrderProduksi;
use App\Domain\Persediaan\Layanan\PenyusunBahanProduksi;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi formulir order produksi POST/PUT (F-05e). `Bahan` kosong/tidak dikirim = diisi dari resep; jumlah dalam
 * satuan dasar. Aturan bisnis (jenis produk, resep, pelacakan) diperiksa Aksi.
 */
final class SimpanOrderProduksiPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...($this->isMethod('post') ? ['Uuid' => ['nullable', 'ulid']] : ['VersiDiubahPada' => ['required', 'string', 'max:40']]),
            'UuidGudang' => ['required', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'UuidProduk' => ['required', 'ulid'],
            'JumlahHasil' => ['required', SimpanStokAwalPermintaan::POLA_JUMLAH],
            'BiayaOverhead' => ['nullable', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'NomorBatch' => ['nullable', 'string', 'max:60'],
            'TanggalKedaluwarsa' => ['nullable', 'date_format:Y-m-d'],
            'Keterangan' => ['nullable', 'string', 'max:500'],
            'Bahan' => ['nullable', 'array', 'max:'.PenyusunBahanProduksi::MAKS_BAHAN],
            'Bahan.*.UuidProduk' => ['required', 'ulid'],
            'Bahan.*.Jumlah' => ['required', SimpanStokAwalPermintaan::POLA_JUMLAH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'UuidProduk.required' => 'Pilih produk yang diproduksi.',
            'JumlahHasil.required' => 'Isi jumlah hasil produksi.',
            'JumlahHasil.regex' => 'Jumlah hasil maksimal 4 angka desimal.',
            'BiayaOverhead.regex' => 'Biaya overhead berupa angka rupiah tanpa minus.',
            'Bahan.max' => 'Paling banyak :max baris bahan.',
            'Bahan.*.Jumlah.required' => 'Isi jumlah bahan.',
            'Bahan.*.Jumlah.regex' => 'Jumlah bahan maksimal 4 angka desimal.',
        ];
    }

    public function AmbilData(int $idGudang): DataOrderProduksi
    {
        $uuidProduk = (string) $this->validated('UuidProduk');
        /** @var list<array{UuidProduk: string, Jumlah: string}> $bahan */
        $bahan = array_values((array) ($this->validated('Bahan') ?? []));
        $info = app(InfoProdukStok::class)->AmbilDariUuid([$uuidProduk, ...array_map(fn (array $b): string => (string) $b['UuidProduk'], $bahan)]);
        $hasil = $info[$uuidProduk] ?? throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk hasil tidak ditemukan.', 'UuidProduk');
        $kedaluwarsa = $this->validated('TanggalKedaluwarsa');

        return new DataOrderProduksi(
            uuid: $this->isMethod('post') ? ($this->validated('Uuid') === null ? null : strtoupper((string) $this->validated('Uuid'))) : null,
            idGudang: $idGudang,
            tanggal: CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal')) ?: CarbonImmutable::today(),
            idProduk: $hasil->id,
            jumlahHasil: Kuantitas::Dari((string) $this->validated('JumlahHasil')),
            biayaOverhead: Uang::Dari((string) ($this->validated('BiayaOverhead') ?? '0')),
            nomorBatch: $this->validated('NomorBatch') === null ? null : (string) $this->validated('NomorBatch'),
            tanggalKedaluwarsa: is_string($kedaluwarsa) ? (CarbonImmutable::createFromFormat('!Y-m-d', $kedaluwarsa) ?: null) : null,
            keterangan: $this->validated('Keterangan') === null ? null : (string) $this->validated('Keterangan'),
            bahan: $bahan === [] ? null : array_map(function (array $b, int $i) use ($info): array {
                $p = $info[(string) $b['UuidProduk']] ?? throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk bahan tidak ditemukan.', "Bahan.{$i}.UuidProduk");

                return ['idProduk' => $p->id, 'jumlah' => Kuantitas::Dari((string) $b['Jumlah'])];
            }, $bahan, array_keys($bahan)),
            versiDiubahPada: $this->isMethod('post') ? null : (string) $this->validated('VersiDiubahPada'),
        );
    }
}
