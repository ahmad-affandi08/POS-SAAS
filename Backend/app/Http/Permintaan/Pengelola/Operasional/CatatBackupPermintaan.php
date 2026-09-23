<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Operasional;

use App\Domain\Pengelola\Operasional\Data\DataCatatanBackup;
use App\Domain\Pengelola\Operasional\Enum\HasilBackup;
use App\Domain\Pengelola\Operasional\Enum\JenisCatatanBackup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Isian manual hasil backup / uji restore oleh Teknis (P-11). Waktu diisi dalam WIB dari formulir.
 */
final class CatatBackupPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Jenis' => ['required', 'string', Rule::enum(JenisCatatanBackup::class)],
            'Hasil' => ['required', 'string', Rule::enum(HasilBackup::class)],
            'SelesaiPada' => ['required', 'date_format:Y-m-d\TH:i'],
            'UkuranMb' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'Lokasi' => ['nullable', 'string', 'max:500'],
            'Keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function AmbilData(): DataCatatanBackup
    {
        $selesaiPada = $this->date('SelesaiPada', 'Y-m-d\\TH:i', 'Asia/Jakarta')
            ?? throw ValidationException::withMessages(['SelesaiPada' => 'Waktu selesai wajib diisi.']);

        return new DataCatatanBackup(
            jenis: JenisCatatanBackup::from($this->string('Jenis')->toString()),
            hasil: HasilBackup::from($this->string('Hasil')->toString()),
            selesaiPada: $selesaiPada->utc(),
            ukuranByte: $this->filled('UkuranMb') ? $this->integer('UkuranMb') * 1024 * 1024 : null,
            lokasi: $this->filled('Lokasi') ? $this->string('Lokasi')->trim()->toString() : null,
            keterangan: $this->filled('Keterangan') ? $this->string('Keterangan')->trim()->toString() : null,
        );
    }
}
