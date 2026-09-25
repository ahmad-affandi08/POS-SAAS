<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Model\JurnalDetail;
use Illuminate\Database\Eloquent\Builder;

/**
 * Saringan laporan keuangan F-13a (buku besar, neraca saldo, laba rugi): rentang tanggal jurnal `YYYY-MM-DD`
 * (inklusif), outlet terpilih (null = semua), dan outlet yang boleh diakses pengguna (null = semua outlet, termasuk
 * baris tingkat usaha tanpa outlet).
 */
final readonly class SaringLaporanKeuangan
{
    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function __construct(
        public string $dari,
        public string $sampai,
        public ?int $idOutlet,
        public ?array $idOutletBoleh,
    ) {}

    /**
     * Batasi baris `JurnalDetail` ke outlet terpilih, atau ke outlet akses pengguna berbatas outlet.
     *
     * @param  Builder<JurnalDetail>  $kueri
     * @return Builder<JurnalDetail>
     */
    public function TerapkanOutlet(Builder $kueri, string $kolom = 'IdOutlet'): Builder
    {
        if ($this->idOutlet !== null) {
            $kueri->where($kolom, $this->idOutlet);
        } elseif ($this->idOutletBoleh !== null) {
            $kueri->whereIn($kolom, $this->idOutletBoleh === [] ? [0] : $this->idOutletBoleh);
        }

        return $kueri;
    }
}
