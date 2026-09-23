<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Katalog\Resep\Data\DataBahanResep;
use App\Domain\Katalog\Resep\Data\DataResep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Isian versi resep baru: `{ JumlahHasil; Catatan; Bahan: { UuidProdukBahan; Jumlah; UuidSatuan; PersenSusut }[] }`
 * (F-03 E.9). Jumlah & persen berupa string desimal. Bahan dan satuan dicari lewat Uuid di tenant aktif.
 */
final class SimpanResepPermintaan extends FormRequest
{
    /** @var array<string, int> */
    private array $idProduk = [];

    /** @var array<string, int> */
    private array $idSatuan = [];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'JumlahHasil' => ['required', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
            'Catatan' => ['nullable', 'string', 'max:255'],
            'Bahan' => ['present', 'array'],
            'Bahan.*.UuidProdukBahan' => ['required', 'ulid'],
            'Bahan.*.Jumlah' => ['required', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
            'Bahan.*.UuidSatuan' => ['required', 'ulid'],
            'Bahan.*.PersenSusut' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'JumlahHasil.regex' => 'Jumlah hasil berupa angka, maksimal 4 desimal.',
            'Bahan.*.Jumlah.regex' => 'Jumlah berupa angka, maksimal 4 desimal.',
            'Bahan.*.PersenSusut.regex' => 'Susut berupa angka persen, maksimal 6 desimal.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $baris = $this->AmbilBaris();
            /** @var array<string, int> $produk */
            $produk = Produk::query()->whereIn('Uuid', array_column($baris, 'UuidProdukBahan'))->pluck('Id', 'Uuid')->all();
            /** @var array<string, int> $satuan */
            $satuan = Satuan::query()->whereIn('Uuid', array_column($baris, 'UuidSatuan'))->pluck('Id', 'Uuid')->all();
            $this->idProduk = $produk;
            $this->idSatuan = $satuan;

            foreach ($baris as $i => $isi) {
                if (! isset($produk[(string) $isi['UuidProdukBahan']])) {
                    $validator->errors()->add("Bahan.{$i}.UuidProdukBahan", 'Bahan tidak ditemukan.');
                }

                if (! isset($satuan[(string) $isi['UuidSatuan']])) {
                    $validator->errors()->add("Bahan.{$i}.UuidSatuan", 'Satuan tidak ditemukan.');
                }
            }
        });
    }

    public function AmbilData(): DataResep
    {
        $bahan = [];

        foreach ($this->AmbilBaris() as $isi) {
            $bahan[] = new DataBahanResep(
                idProdukBahan: $this->idProduk[(string) $isi['UuidProdukBahan']] ?? 0,
                jumlah: Kuantitas::Dari((string) $isi['Jumlah']),
                idSatuan: $this->idSatuan[(string) $isi['UuidSatuan']] ?? 0,
                persenSusut: is_scalar($isi['PersenSusut'] ?? null) ? (string) $isi['PersenSusut'] : '0',
            );
        }

        $catatan = $this->input('Catatan');

        return new DataResep(
            jumlahHasil: Kuantitas::Dari($this->string('JumlahHasil')->toString()),
            catatan: is_string($catatan) ? $catatan : null,
            bahan: $bahan,
        );
    }

    /**
     * @return list<array{UuidProdukBahan: string, Jumlah: string, UuidSatuan: string, PersenSusut: mixed}>
     */
    private function AmbilBaris(): array
    {
        $baris = $this->input('Bahan', []);
        $hasil = [];

        foreach (is_array($baris) ? $baris : [] as $isi) {
            if (is_array($isi)) {
                $hasil[] = [
                    'UuidProdukBahan' => is_scalar($isi['UuidProdukBahan'] ?? null) ? (string) $isi['UuidProdukBahan'] : '',
                    'Jumlah' => is_scalar($isi['Jumlah'] ?? null) ? (string) $isi['Jumlah'] : '0',
                    'UuidSatuan' => is_scalar($isi['UuidSatuan'] ?? null) ? (string) $isi['UuidSatuan'] : '',
                    'PersenSusut' => $isi['PersenSusut'] ?? null,
                ];
            }
        }

        return $hasil;
    }
}
