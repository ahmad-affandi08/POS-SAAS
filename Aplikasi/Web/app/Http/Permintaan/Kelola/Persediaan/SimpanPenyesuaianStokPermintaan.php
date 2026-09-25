<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Persediaan\Data\DataBarisDokumenStok;
use App\Domain\Persediaan\Data\DataPenyesuaianStok;
use App\Domain\Persediaan\Enum\AlasanPenyesuaian;
use App\Domain\Persediaan\Kueri\PelacakanTersedia;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form penyesuaian stok POST/PUT (F-05b). Per baris `Arah` Masuk/Keluar + jumlah positif (disimpan bertanda),
 * harga modal per satuan untuk masuk, batch/nomor seri keluar lewat Uuid, batch/nomor seri masuk diketik.
 */
final class SimpanPenyesuaianStokPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maksimal = (int) config('persediaan.Dokumen.MaksimalBaris', 500);

        return [
            ...($this->isMethod('post') ? ['Uuid' => ['nullable', 'ulid']] : ['VersiDiubahPada' => ['required', 'string', 'max:40']]),
            'UuidGudang' => ['required', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'KodeAlasan' => ['required', 'string', Rule::enum(AlasanPenyesuaian::class)],
            'Keterangan' => ['nullable', 'string', 'max:500'],
            'Baris' => ['required', 'array', 'min:1', "max:{$maksimal}"],
            'Baris.*.UuidProduk' => ['required', 'ulid'],
            'Baris.*.Arah' => ['required', Rule::in(['Masuk', 'Keluar'])],
            'Baris.*.Jumlah' => ['required', SimpanStokAwalPermintaan::POLA_JUMLAH],
            'Baris.*.HppSatuan' => ['nullable', SimpanStokAwalPermintaan::POLA_HPP],
            'Baris.*.UuidBatchStok' => ['nullable', 'ulid'],
            'Baris.*.NomorBatch' => ['nullable', 'string', 'max:60'],
            'Baris.*.TanggalKedaluwarsa' => ['nullable', 'date_format:Y-m-d'],
            'Baris.*.UuidNomorSeri' => ['nullable', 'ulid'],
            'Baris.*.NomorSeri' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'KodeAlasan.required' => 'Pilih alasan penyesuaian.',
            'KodeAlasan.*' => 'Alasan penyesuaian tidak dikenal.',
            'Baris.required' => 'Tambahkan minimal satu produk.',
            'Baris.min' => 'Tambahkan minimal satu produk.',
            'Baris.max' => 'Satu penyesuaian maksimal :max baris.',
            'Baris.*.Jumlah.regex' => 'Jumlah berupa angka tanpa titik ribuan, maksimal 4 angka desimal.',
            'Baris.*.HppSatuan.regex' => 'Harga modal berupa angka tanpa titik ribuan, maksimal 6 angka desimal.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['UuidGudang' => 'lokasi stok', 'Tanggal' => 'tanggal', 'Keterangan' => 'keterangan', 'Baris.*.Jumlah' => 'jumlah', 'Baris.*.HppSatuan' => 'harga modal'];
    }

    public function AmbilData(int $idGudang): DataPenyesuaianStok
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));
        $produk = app(InfoProdukStok::class)->AmbilDariUuid(array_values(array_map(fn (array $b): string => (string) $b['UuidProduk'], $baris)));
        $pelacakan = app(PelacakanTersedia::class);
        $batch = $pelacakan->AmbilIdBatch(array_values(array_filter(array_map(fn (array $b): ?string => is_string($b['UuidBatchStok'] ?? null) ? $b['UuidBatchStok'] : null, $baris))));
        $seri = $pelacakan->AmbilIdSeri(array_values(array_filter(array_map(fn (array $b): ?string => is_string($b['UuidNomorSeri'] ?? null) ? $b['UuidNomorSeri'] : null, $baris))));
        $teks = fn (mixed $nilai): ?string => is_string($nilai) && trim($nilai) !== '' ? $nilai : null;

        return new DataPenyesuaianStok(
            uuid: $this->isMethod('post') && is_string($this->validated('Uuid')) ? (string) $this->validated('Uuid') : null,
            idGudang: $idGudang,
            tanggal: CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal')) ?: CarbonImmutable::today(),
            alasan: AlasanPenyesuaian::from((string) $this->validated('KodeAlasan')),
            keterangan: is_string($this->validated('Keterangan')) ? (string) $this->validated('Keterangan') : null,
            baris: array_map(function (array $b) use ($produk, $batch, $seri, $teks): DataBarisDokumenStok {
                $jumlah = Kuantitas::Dari((string) $b['Jumlah']);
                $keluar = $b['Arah'] === 'Keluar';

                return new DataBarisDokumenStok(
                    idProduk: ($produk[(string) $b['UuidProduk']] ?? null)->id ?? 0,
                    jumlah: $keluar ? $jumlah->Negasi() : $jumlah,
                    hppSatuan: $teks($b['HppSatuan'] ?? null) === null ? null : BigDecimal::of((string) $b['HppSatuan']),
                    idBatchStok: is_string($b['UuidBatchStok'] ?? null) ? ($batch[$b['UuidBatchStok']] ?? 0) : null,
                    nomorBatch: $teks($b['NomorBatch'] ?? null),
                    tanggalKedaluwarsa: is_string($b['TanggalKedaluwarsa'] ?? null) ? (CarbonImmutable::createFromFormat('!Y-m-d', $b['TanggalKedaluwarsa']) ?: null) : null,
                    idNomorSeri: is_string($b['UuidNomorSeri'] ?? null) ? ($seri[$b['UuidNomorSeri']] ?? 0) : null,
                    nomorSeri: $teks($b['NomorSeri'] ?? null),
                );
            }, $baris),
            versiDiubahPada: $this->isMethod('put') && is_string($this->validated('VersiDiubahPada')) ? (string) $this->validated('VersiDiubahPada') : null,
        );
    }
}
