<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\PaketProduk\Data\DataKomponenPaket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Komponen paket: `{ Komponen: { UuidProdukKomponen; Jumlah; AlokasiHarga }[] }` (F-03 E.9). `AlokasiHarga` kosong =
 * otomatis. Komponen dicari lewat Uuid di tenant aktif; produk tenant lain = tidak ditemukan.
 */
final class SimpanKomponenPaketPermintaan extends FormRequest
{
    /** @var array<string, int> */
    private array $idProduk = [];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Komponen' => ['present', 'array'],
            'Komponen.*.UuidProdukKomponen' => ['required', 'ulid'],
            'Komponen.*.Jumlah' => ['required', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
            'Komponen.*.AlokasiHarga' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Komponen.*.Jumlah.regex' => 'Jumlah berupa angka, maksimal 4 desimal.',
            'Komponen.*.AlokasiHarga.regex' => 'Alokasi harga berupa angka persen, maksimal 6 desimal.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $baris = $this->AmbilBaris();
            /** @var array<string, int> $peta */
            $peta = Produk::query()->whereIn('Uuid', array_column($baris, 'UuidProdukKomponen'))->pluck('Id', 'Uuid')->all();
            $this->idProduk = $peta;

            foreach ($baris as $i => $isi) {
                if (! isset($peta[$isi['UuidProdukKomponen']])) {
                    $validator->errors()->add("Komponen.{$i}.UuidProdukKomponen", 'Produk komponen tidak ditemukan.');
                }
            }
        });
    }

    /**
     * @return list<DataKomponenPaket>
     */
    public function AmbilData(): array
    {
        return array_map(fn (array $isi): DataKomponenPaket => new DataKomponenPaket(
            idProdukKomponen: $this->idProduk[$isi['UuidProdukKomponen']] ?? 0,
            jumlah: Kuantitas::Dari($isi['Jumlah']),
            alokasiHarga: $isi['AlokasiHarga'],
        ), $this->AmbilBaris());
    }

    /**
     * @return list<array{UuidProdukKomponen: string, Jumlah: string, AlokasiHarga: string|null}>
     */
    private function AmbilBaris(): array
    {
        $baris = $this->input('Komponen', []);
        $hasil = [];

        foreach (is_array($baris) ? $baris : [] as $isi) {
            if (is_array($isi)) {
                $alokasi = $isi['AlokasiHarga'] ?? null;
                $hasil[] = [
                    'UuidProdukKomponen' => is_scalar($isi['UuidProdukKomponen'] ?? null) ? (string) $isi['UuidProdukKomponen'] : '',
                    'Jumlah' => is_scalar($isi['Jumlah'] ?? null) ? (string) $isi['Jumlah'] : '0',
                    'AlokasiHarga' => is_scalar($alokasi) && (string) $alokasi !== '' ? (string) $alokasi : null,
                ];
            }
        }

        return $hasil;
    }
}
