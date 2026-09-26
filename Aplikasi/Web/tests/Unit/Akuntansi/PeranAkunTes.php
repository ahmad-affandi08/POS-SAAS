<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;

describe('PeranAkun: istilah kamus & alias kunci lama (P-03, BR-P03.4, DesainF01 H1)', function (): void {
    it('memakai istilah Indonesia PiutangPencairan dan SusutPersediaan dengan tipe akun yang sama seperti sebelumnya', function (): void {
        expect(PeranAkun::PiutangPencairan->value)->toBe('PiutangPencairan')
            ->and(PeranAkun::PiutangPencairan->AmbilTipeAkun())->toBe(TipeAkun::Aset)
            ->and(PeranAkun::PiutangPencairan->AmbilLabel())->toBe('Piutang pencairan (QRIS/EDC/gateway/ojol)')
            ->and(PeranAkun::SusutPersediaan->value)->toBe('SusutPersediaan')
            ->and(PeranAkun::SusutPersediaan->AmbilTipeAkun())->toBe(TipeAkun::Hpp)
            ->and(PeranAkun::SusutPersediaan->AmbilLabel())->toBe('Susut & barang rusak')
            ->and(PeranAkun::tryFrom('PiutangSettlement'))->toBeNull()
            ->and(PeranAkun::tryFrom('Waste'))->toBeNull()
            ->and(PeranAkun::cases())->toHaveCount(32);
    });

    it('DariKunci membaca kunci baru, kunci lama dari versi terbit, dan menolak kunci asing', function (): void {
        expect(PeranAkun::DariKunci('PiutangSettlement'))->toBe(PeranAkun::PiutangPencairan)
            ->and(PeranAkun::DariKunci('Waste'))->toBe(PeranAkun::SusutPersediaan)
            ->and(PeranAkun::DariKunci('PiutangPencairan'))->toBe(PeranAkun::PiutangPencairan)
            ->and(PeranAkun::DariKunci('KasOutlet'))->toBe(PeranAkun::KasOutlet)
            ->and(PeranAkun::DariKunci('Kasbon'))->toBeNull()
            ->and(PeranAkun::DariKunci(''))->toBeNull();
    });

    it('NormalisasiPemetaan mengganti kunci lama; kunci baru menang bila keduanya ada; kunci asing dibiarkan', function (): void {
        expect(PeranAkun::NormalisasiPemetaan([
            'KasOutlet' => '1-1100',
            'PiutangSettlement' => '1-1300',
            'Waste' => '5-1200',
            'SusutPersediaan' => '5-1210',
            'Kasbon' => '1-1450',
        ]))->toBe([
            'KasOutlet' => '1-1100',
            'PiutangPencairan' => '1-1300',
            'SusutPersediaan' => '5-1210',
            'Kasbon' => '1-1450',
        ])->and(PeranAkun::NormalisasiPemetaan([]))->toBe([]);
    });
});
