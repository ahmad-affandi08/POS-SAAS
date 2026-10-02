<?php

declare(strict_types=1);

namespace App\Http\Permintaan\ApiPublik;

use App\Domain\Persediaan\Enum\AlasanPenyesuaian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Badan `POST /api/v1/stok/penyesuaian` (X7 bagian 4, cakupan `stok:tulis`). `Uuid` dibuat pemanggil (ULID) dan
 * menjadi kunci idempotensi: kirim ulang dengan Uuid yang sama mengembalikan dokumen yang sama, tidak membuat dokumen
 * kedua. `Jumlah` bertanda dalam satuan dasar (+ masuk, − keluar); stok masuk wajib `HppSatuan`.
 */
final class BuatPenyesuaianStokApiPermintaan extends FormRequest
{
    public const MAKS_BARIS = 200;

    private const POLA_JUMLAH = '/^-?\d{1,14}(\.\d{1,4})?$/';

    private const POLA_HPP = '/^\d{1,14}(\.\d{1,6})?$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Uuid' => ['required', 'string', 'ulid'],
            'UuidGudang' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Alasan' => ['required', Rule::enum(AlasanPenyesuaian::class)],
            'Keterangan' => ['nullable', 'string', 'max:255'],
            'Baris' => ['required', 'array', 'min:1', 'max:'.self::MAKS_BARIS],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:'.self::POLA_JUMLAH],
            'Baris.*.HppSatuan' => ['nullable', 'string', 'regex:'.self::POLA_HPP],
            'Baris.*.NomorBatch' => ['nullable', 'string', 'max:50'],
            'Baris.*.TanggalKedaluwarsa' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
