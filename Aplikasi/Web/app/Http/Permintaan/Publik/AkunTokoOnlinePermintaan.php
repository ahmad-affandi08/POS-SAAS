<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Publik;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** F-17 bagian 3: masukan akun pembeli toko online (minta kode, masuk, daftar, ubah profil). */
final class AkunTokoOnlinePermintaan extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $profil = ['Nama' => ['required', 'string', 'min:2', 'max:100'], 'Email' => ['nullable', 'email', 'max:150'],
            'TanggalLahir' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'], 'SetujuPemasaran' => ['sometimes', 'boolean']];

        return match (true) {
            $this->routeIs('publik.toko-online.akun.kode') => ['NoHp' => ['required', 'string', 'max:30']],
            $this->routeIs('publik.toko-online.akun.masuk') => ['NoHp' => ['required', 'string', 'max:30'], 'Kode' => ['required', 'string', 'max:10']],
            $this->routeIs('publik.toko-online.akun.daftar') => [
                'TokenDaftar' => ['required', 'string', 'size:48'], 'NoHp' => ['required', 'string', 'max:30'], ...$profil,
                'SetujuDataPribadi' => ['required', 'accepted'],
            ],
            default => $profil,
        };
    }

    public function AmbilTanggalLahir(): ?CarbonImmutable
    {
        $nilai = $this->validated('TanggalLahir');

        return is_string($nilai) ? CarbonImmutable::createFromFormat('Y-m-d', $nilai)?->startOfDay() : null;
    }

    public function AmbilEmail(): ?string
    {
        $nilai = $this->validated('Email');

        return is_string($nilai) ? $nilai : null;
    }
}
