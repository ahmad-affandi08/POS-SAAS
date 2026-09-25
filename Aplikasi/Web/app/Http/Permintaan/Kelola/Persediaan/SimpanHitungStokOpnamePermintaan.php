<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Persediaan\Data\DataHitungOpname;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi lembar hitung stok opname (F-05b): daftar hasil hitung per baris (`Urutan`) atau baris baru (`UuidProduk`
 * + batch/nomor seri). `JumlahFisik` null = belum dihitung. Satuan dasar, maksimal 4 desimal.
 */
final class SimpanHitungStokOpnamePermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Hitung' => ['required', 'array', 'min:1', 'max:'.(int) config('persediaan.Dokumen.MaksimalBarisOpname', 5000)],
            'Hitung.*.Urutan' => ['nullable', 'integer', 'min:1', 'required_without:Hitung.*.UuidProduk'],
            'Hitung.*.UuidProduk' => ['nullable', 'ulid'],
            'Hitung.*.JumlahFisik' => ['nullable', SimpanStokAwalPermintaan::POLA_JUMLAH],
            'Hitung.*.NomorBatch' => ['nullable', 'string', 'max:60'],
            'Hitung.*.TanggalKedaluwarsa' => ['nullable', 'date_format:Y-m-d'],
            'Hitung.*.NomorSeri' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Hitung.required' => 'Belum ada hasil hitung yang disimpan.',
            'Hitung.*.JumlahFisik.regex' => 'Jumlah fisik berupa angka ≥ 0 tanpa titik ribuan, maksimal 4 angka desimal.',
        ];
    }

    /**
     * @return list<DataHitungOpname>
     */
    public function AmbilHitung(): array
    {
        /** @var list<array<string, mixed>> $hitung */
        $hitung = array_values((array) $this->validated('Hitung'));
        $produk = app(InfoProdukStok::class)->AmbilDariUuid(array_values(array_filter(array_map(fn (array $h): ?string => is_string($h['UuidProduk'] ?? null) ? $h['UuidProduk'] : null, $hitung))));

        return array_map(fn (array $h): DataHitungOpname => new DataHitungOpname(
            urutan: isset($h['Urutan']) && is_numeric($h['Urutan']) ? (int) $h['Urutan'] : null,
            idProduk: is_string($h['UuidProduk'] ?? null) ? (($produk[$h['UuidProduk']] ?? null)->id ?? 0) : null,
            jumlahFisik: is_string($h['JumlahFisik'] ?? null) || is_numeric($h['JumlahFisik'] ?? null) ? Kuantitas::Dari((string) $h['JumlahFisik']) : null,
            nomorBatch: is_string($h['NomorBatch'] ?? null) ? $h['NomorBatch'] : null,
            tanggalKedaluwarsa: is_string($h['TanggalKedaluwarsa'] ?? null) ? (CarbonImmutable::createFromFormat('!Y-m-d', $h['TanggalKedaluwarsa']) ?: null) : null,
            nomorSeri: is_string($h['NomorSeri'] ?? null) ? $h['NomorSeri'] : null,
        ), $hitung);
    }
}
