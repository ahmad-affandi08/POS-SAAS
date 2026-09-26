<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Kontrak;

use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;

/**
 * Penyedia butir Kotak Tindakan (D-23 C), satu per domain, didaftarkan dengan tag [TAG] di provider domainnya.
 * `Kumpulkan` hanya mengembalikan butir yang `jumlah` > 0 dan sudah memeriksa izin pengguna. Penyedia yang punya
 * dokumen "perlu ditinjau" juga menjawab `SaringDokumen`: Uuid mana yang benar dokumen jenis itu di tenant aktif (untuk
 * menandai "sudah dicek").
 */
interface PenyediaTindakan
{
    public const TAG = 'tindakan.penyedia';

    /**
     * @return list<DataButirTindakan>
     */
    public function Kumpulkan(DataKonteksTindakan $konteks): array;

    /**
     * Jenis dokumen yang bisa ditandai "sudah dicek" oleh penyedia ini.
     *
     * @return list<string>
     */
    public function AmbilJenisDokumen(): array;

    /**
     * @param  list<string>  $uuid
     * @return list<string> Uuid yang ada di tenant aktif dan memang perlu ditinjau
     */
    public function SaringDokumen(string $jenisDokumen, array $uuid): array;
}
