<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pos\V1;

use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Penjualan\Data\DataTagihanQrisPos;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-08 `POST /api/pos/v1/qris`: `Uuid` (ULID perangkat, idempoten), `UuidMetode` (metode QRIS dinamis aktif),
 * `Jumlah` (string desimal; rupiah penuh diperiksa Aksi), `Keterangan` opsional.
 */
final class BuatTagihanQrisPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Uuid' => ['required', 'string', 'ulid'],
            'UuidMetode' => ['required', 'string', 'ulid'],
            'Jumlah' => ['required', 'string', 'max:30'],
            'Keterangan' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function AmbilData(Perangkat $perangkat): DataTagihanQrisPos
    {
        return new DataTagihanQrisPos(
            uuid: strtoupper($this->string('Uuid')->toString()),
            uuidMetode: strtoupper($this->string('UuidMetode')->toString()),
            jumlah: $this->string('Jumlah')->toString(),
            keterangan: $this->filled('Keterangan') ? $this->string('Keterangan')->toString() : null,
            idPerangkat: $perangkat->Id,
            idOutlet: $perangkat->IdOutlet,
        );
    }
}
