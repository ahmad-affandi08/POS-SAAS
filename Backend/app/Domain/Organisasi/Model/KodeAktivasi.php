<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Kode aktivasi perangkat (PRD §15.3, F-02 langkah 5): 8 karakter tanpa karakter ambigu, berlaku 15 menit
 * (konfigurasi), sekali pakai. Kode asli hanya tampil sekali di back-office; yang disimpan HMAC-SHA256 dengan kunci
 * aplikasi sehingga kebocoran tabel tidak cukup untuk menebak kode 40-bit secara offline.
 *
 * Sengaja tanpa `MilikTenant` (seperti `UndanganPengguna`): saat aplikasi menukar kode, tenant belum diketahui.
 * Baris hanya dicari lewat `HashKode`, atau disaring `IdTenant` tenant aktif secara eksplisit.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property string $HashKode
 * @property Carbon $KedaluwarsaPada
 * @property Carbon|null $DipakaiPada
 * @property Carbon|null $DibatalkanPada
 * @property int|null $IdPenggunaPembuat
 */
final class KodeAktivasi extends ModelDasar
{
    /** Tanpa 0/O dan 1/I agar mudah diketik dari layar (32 karakter = 5 bit per karakter). */
    public const ABJAD = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const PANJANG = 8;

    protected $table = 'KodeAktivasi';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $hidden = ['HashKode'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'DipakaiPada' => null,
        'DibatalkanPada' => null,
        'IdPenggunaPembuat' => null,
    ];

    /** Kode dinormalisasi dulu: huruf besar, spasi & tanda hubung diabaikan ("abcd-efgh" = "ABCDEFGH"). */
    public static function BuatHashKode(string $kode): string
    {
        return hash_hmac('sha256', self::Normalkan($kode), (string) config('app.key'));
    }

    public static function Normalkan(string $kode): string
    {
        return (string) preg_replace('/[\s-]+/', '', mb_strtoupper(trim($kode)));
    }

    public function CekBerlaku(): bool
    {
        return $this->DipakaiPada === null && $this->DibatalkanPada === null && $this->KedaluwarsaPada->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'KedaluwarsaPada' => 'datetime',
            'DipakaiPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
