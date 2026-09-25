<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan `TutupShift` (F-11) dari item outbox `Shift.Tutup`. `ringkasanKasSeharusnya`/`ringkasanSelisih` = hitungan
 * perangkat (dibandingkan dengan hitungan server). `nonTunai` = total non-tunai per metode menurut kasir (opsional,
 * pencocokan slip EDC/QRIS). `uuidPenyetuju` = supervisor yang memasukkan PIN untuk selisih di atas toleransi.
 */
final readonly class DataTutupShift
{
    /**
     * @param  list<array{Nominal: string, Jumlah: int}>|null  $pecahan
     * @param  list<array{UuidMetodePembayaran: string, Jumlah: Uang}>  $nonTunai
     */
    public function __construct(
        public int $idPerangkat,
        public string $uuidShift,
        public string $uuidPenutup,
        public CarbonImmutable $ditutupPada,
        public Uang $kasAktual,
        public ?array $pecahan,
        public array $nonTunai,
        public ?string $alasan,
        public ?string $uuidPenyetuju,
        public Uang $ringkasanKasSeharusnya,
        public Uang $ringkasanSelisih,
    ) {}
}
