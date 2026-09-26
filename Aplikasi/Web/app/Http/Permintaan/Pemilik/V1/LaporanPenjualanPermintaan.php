<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pemilik\V1;

use App\Domain\Laporan\Kueri\LaporanRingkasPemilik;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `GET /api/pemilik/v1/laporan/penjualan?dari=&sampai=&kelompok=&outlet=` (OWN-05). */
final class LaporanPenjualanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'kelompok' => ['required', 'string', Rule::in(LaporanRingkasPemilik::KELOMPOK)],
            'outlet' => ['nullable', 'string', 'max:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'kelompok.in' => 'Kelompok laporan harus Produk, Kategori, Kasir, Jam, atau Kanal.',
        ];
    }
}
