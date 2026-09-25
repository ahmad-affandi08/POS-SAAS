<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\BukaKunciPeriode;
use Illuminate\Foundation\Http\FormRequest;

/** Alasan membuka kunci periode (F-15 *reopen*), dicatat di log audit. */
final class BukaKunciPeriodePermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['Alasan' => ['required', 'string', 'min:'.BukaKunciPeriode::PANJANG_ALASAN_MINIMAL, 'max:'.BukaKunciPeriode::PANJANG_ALASAN_MAKSIMAL]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Alasan.*' => 'Tulis alasan membuka kunci periode, '.BukaKunciPeriode::PANJANG_ALASAN_MINIMAL.' sampai '.BukaKunciPeriode::PANJANG_ALASAN_MAKSIMAL.' karakter, misal "Faktur pemasok September baru diterima".'];
    }
}
