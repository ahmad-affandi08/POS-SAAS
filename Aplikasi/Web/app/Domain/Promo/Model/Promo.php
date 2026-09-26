<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Promo\Enum\StatusPromo;
use Illuminate\Support\Carbon;

/**
 * Promo (F-16c, CRM-05). `Definisi` = syarat & aksi (lihat `App\Domain\Penjualan\Kalkulasi\DefinisiPromo::Urai`);
 * rentang `[MulaiPada, SelesaiPada)` UTC; `Kuota` null = tanpa batas. Bagian 4b (v1.91): `IdPemasok` +
 * `PersenDanaPemasok` = bagian potongan yang ditanggung pemasok (diklaim lewat `KlaimPromoPemasok`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Kode
 * @property string $Nama
 * @property array<string, mixed> $Definisi
 * @property int $Prioritas
 * @property bool $Eksklusif
 * @property Carbon|null $MulaiPada
 * @property Carbon|null $SelesaiPada
 * @property int|null $Kuota
 * @property int $KuotaTerpakai
 * @property StatusPromo $Status
 * @property Carbon|null $DiubahPada
 * @property int|null $IdPemasok
 * @property string $PersenDanaPemasok
 */
final class Promo extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Promo';

    /** @var array<string, mixed> */
    protected $attributes = ['Prioritas' => 0, 'Eksklusif' => false, 'KuotaTerpakai' => 0, 'Status' => 'Aktif', 'PersenDanaPemasok' => '0.00'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Definisi' => 'array',
            'Prioritas' => 'integer',
            'Eksklusif' => 'boolean',
            'MulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
            'Kuota' => 'integer',
            'KuotaTerpakai' => 'integer',
            'Status' => StatusPromo::class,
            'PersenDanaPemasok' => 'decimal:2',
        ];
    }

    /** F-16c bagian 2: promo hanya berlaku dengan kode voucher (`Definisi.WajibVoucher`). */
    public function CekWajibVoucher(): bool
    {
        return ($this->Definisi['WajibVoucher'] ?? false) === true;
    }

    /**
     * Syarat F-16c bagian 3 (metode bayar, ulang tahun, transaksi pertama, batas per pelanggan) yang belum dikenal
     * aplikasi kasir versi lama: promo seperti ini hanya dikirim ke aplikasi yang memintanya (`?lanjutan=1`).
     */
    public function CekSyaratLanjutan(): bool
    {
        $d = $this->Definisi;

        return ($d['MetodeBayar'] ?? []) !== [] || isset($d['UlangTahun']) || ($d['TransaksiPertama'] ?? false) === true || isset($d['BatasPerPelanggan']);
    }

    /** Sisa kuota; null = tanpa batas. */
    public function AmbilKuotaTersisa(): ?int
    {
        return $this->Kuota === null ? null : max(0, $this->Kuota - $this->KuotaTerpakai);
    }
}
