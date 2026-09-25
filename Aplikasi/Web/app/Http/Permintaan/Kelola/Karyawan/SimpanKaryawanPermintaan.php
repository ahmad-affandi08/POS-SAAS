<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Karyawan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Karyawan\Data\DataKaryawan;
use Illuminate\Foundation\Http\FormRequest;

/** Isian karyawan (F-18): nama, jabatan, level staf (komisi bagian 2), gaji pokok, akun tertaut, outlet utama. */
final class SimpanKaryawanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            'Jabatan' => ['nullable', 'string', 'max:80'],
            'LevelStaf' => ['nullable', 'string', 'max:40'],
            'GajiPokok' => ['nullable', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'UuidPengguna' => ['nullable', 'string', 'ulid'],
            'UuidOutlet' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['GajiPokok.regex' => 'Gaji pokok harus angka dengan pemisah desimal titik (maks. 2 desimal).'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Nama' => 'nama', 'Jabatan' => 'jabatan', 'LevelStaf' => 'level staf', 'GajiPokok' => 'gaji pokok', 'UuidPengguna' => 'akun', 'UuidOutlet' => 'outlet'];
    }

    public function AmbilData(int $idPengguna): DataKaryawan
    {
        $teks = fn (string $kunci): ?string => is_string($this->validated($kunci)) && $this->validated($kunci) !== '' ? (string) $this->validated($kunci) : null;
        $gaji = $teks('GajiPokok');

        return new DataKaryawan(
            nama: (string) $this->validated('Nama'),
            jabatan: $teks('Jabatan'),
            levelStaf: $teks('LevelStaf'),
            gajiPokok: $gaji === null ? null : Uang::Dari($gaji),
            uuidPengguna: $teks('UuidPengguna'),
            uuidOutlet: $teks('UuidOutlet'),
            idPengguna: $idPengguna,
        );
    }
}
