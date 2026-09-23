<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Dukungan;

use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Http\Permintaan\Kelola\BuatTiketDukunganPermintaan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Balasan ke tenant atau catatan internal tim pada tiket (P-09).
 */
final class BalasTiketDukunganPengelolaPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Isi' => ['required', 'string', 'min:2', 'max:10000'],
            'CatatanInternal' => ['required', 'boolean'],
            'Status' => ['nullable', 'string', Rule::in([
                StatusTiketDukungan::Ditangani->value,
                StatusTiketDukungan::MenungguPelanggan->value,
                StatusTiketDukungan::Selesai->value,
            ])],
            ...BuatTiketDukunganPermintaan::AturanLampiran(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Lampiran.*' => 'lampiran'];
    }

    public function AmbilIsi(): string
    {
        return str_replace("\r\n", "\n", $this->string('Isi')->trim()->toString());
    }

    public function AmbilStatus(): ?StatusTiketDukungan
    {
        return $this->filled('Status') ? StatusTiketDukungan::from($this->string('Status')->toString()) : null;
    }

    /**
     * @return list<UploadedFile>
     */
    public function AmbilLampiran(): array
    {
        return BuatTiketDukunganPermintaan::AmbilBerkas($this);
    }
}
