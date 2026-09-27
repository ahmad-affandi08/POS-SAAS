<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Publik;

use App\Domain\Situs\Enum\JenisProspek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Situs pemasaran bagian B: isian formulir kontak/minta demo. `Situs` adalah perangkap bot (disembunyikan dari
 * pengunjung); `Setuju` wajib dicentang (UU PDP: persetujuan eksplisit pemakaian data untuk dihubungi).
 */
final class KirimProspekSitusPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Jenis' => ['required', Rule::enum(JenisProspek::class)],
            'Nama' => ['required', 'string', 'max:100'],
            'NamaUsaha' => ['nullable', 'string', 'max:150'],
            'NoHp' => ['required', 'string', 'max:25', 'regex:/^\+?[0-9\- ]{8,25}$/'],
            'Email' => ['nullable', 'email', 'max:150'],
            'JenisUsaha' => ['nullable', 'string', 'max:60'],
            'Kota' => ['nullable', 'string', 'max:100'],
            'Pesan' => ['nullable', 'string', 'max:1000'],
            'HalamanAsal' => ['nullable', 'string', 'max:200'],
            'Setuju' => ['accepted'],
            'Situs' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** Isian terisi pada bidang perangkap: dikirim bot, diterima diam-diam tanpa disimpan. */
    public function CekBot(): bool
    {
        return trim((string) $this->input('Situs', '')) !== '';
    }

    /**
     * @return array{Jenis: JenisProspek, Nama: string, NamaUsaha: string|null, NoHp: string, Email: string|null, JenisUsaha: string|null, Kota: string|null, Pesan: string|null, HalamanAsal: string|null}
     */
    public function AmbilIsian(): array
    {
        $t = fn (string $kunci): ?string => ($nilai = trim((string) $this->input($kunci, ''))) === '' ? null : $nilai;
        $halaman = $t('HalamanAsal');

        return [
            'Jenis' => JenisProspek::from((string) $this->input('Jenis')),
            'Nama' => (string) $t('Nama'),
            'NamaUsaha' => $t('NamaUsaha'),
            'NoHp' => (string) $t('NoHp'),
            'Email' => $t('Email'),
            'JenisUsaha' => $t('JenisUsaha'),
            'Kota' => $t('Kota'),
            'Pesan' => $t('Pesan'),
            // Hanya jalur relatif situs (tanpa domain/query) agar tidak menyimpan data pelacakan.
            'HalamanAsal' => $halaman !== null && preg_match('#^/[a-z0-9\-/]*$#', $halaman) === 1 ? $halaman : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nama' => 'nama',
            'NamaUsaha' => 'nama usaha',
            'NoHp' => 'nomor WhatsApp',
            'Email' => 'email',
            'Pesan' => 'pesan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Setuju.accepted' => 'Centang persetujuan agar tim kami boleh menghubungi Anda.',
            'NoHp.regex' => 'Tulis nomor WhatsApp yang aktif, misalnya 0812 3456 7890.',
        ];
    }
}
