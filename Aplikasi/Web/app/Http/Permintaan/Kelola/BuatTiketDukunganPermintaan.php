<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Dukungan\Data\DataTiketBaru;
use App\Domain\Dukungan\Enum\KategoriTiketDukungan;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tiket dukungan baru dari back-office tenant (P-09).
 */
final class BuatTiketDukunganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kategori' => ['required', 'string', Rule::enum(KategoriTiketDukungan::class)],
            'Prioritas' => ['required', 'string', Rule::enum(PrioritasTiketDukungan::class)],
            'Judul' => ['required', 'string', 'min:5', 'max:150'],
            'Isi' => ['required', 'string', 'min:10', 'max:10000'],
            'HalamanAsal' => ['nullable', 'string', 'max:300'],
            ...self::AturanLampiran(),
        ];
    }

    /**
     * Aturan lampiran bersama (tenant & tim internal): jenis & ukuran dari `config('dukungan')`.
     *
     * @return array<string, list<mixed>>
     */
    public static function AturanLampiran(): array
    {
        return [
            'Lampiran' => ['nullable', 'array', 'max:'.(int) config('dukungan.MaksimalLampiranPerPesan')],
            'Lampiran.*' => [
                'file',
                'mimes:'.implode(',', (array) config('dukungan.EkstensiLampiran')),
                'max:'.(int) config('dukungan.UkuranMaksimalLampiranKb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Lampiran.*' => 'lampiran'];
    }

    public function AmbilData(): DataTiketBaru
    {
        $halamanAsal = $this->string('HalamanAsal')->trim()->toString();

        return new DataTiketBaru(
            kategori: KategoriTiketDukungan::from($this->string('Kategori')->toString()),
            prioritas: PrioritasTiketDukungan::from($this->string('Prioritas')->toString()),
            judul: $this->string('Judul')->trim()->toString(),
            isi: str_replace("\r\n", "\n", $this->string('Isi')->trim()->toString()),
            lampiran: self::AmbilBerkas($this),
            konteks: array_filter([
                'HalamanAsal' => $halamanAsal === '' ? null : $halamanAsal,
                'Peramban' => Str::limit((string) $this->userAgent(), 300, ''),
            ], fn ($nilai) => $nilai !== null && $nilai !== ''),
        );
    }

    /**
     * @return list<UploadedFile>
     */
    public static function AmbilBerkas(FormRequest $permintaan): array
    {
        $berkas = $permintaan->file('Lampiran', []);

        return array_values(array_filter(is_array($berkas) ? $berkas : [$berkas], fn ($file) => $file instanceof UploadedFile));
    }
}
