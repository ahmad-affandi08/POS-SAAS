<?php

declare(strict_types=1);

use App\Domain\Tenant\Data\SumberFitur;
use App\Domain\Tenant\Layanan\EvaluatorFitur;

/**
 * @param  array<string, mixed>  $ubah
 */
function BuatSumber(array $ubah = []): SumberFitur
{
    $dasar = [
        'fiturPaket' => ['pos.retail', 'laporan.dasar'],
        'batasPaket' => ['BatasOutlet' => 1, 'BatasPerangkatPerOutlet' => 2, 'BatasSku' => null],
        'addon' => [],
        'overrideFitur' => [],
        'overrideBatas' => [],
        'flagFitur' => [],
        'modulOutletAktif' => null,
    ];

    return new SumberFitur(...[...$dasar, ...$ubah]);
}

describe('EvaluatorFitur (P-04, BR-P04.3)', function (): void {
    it('fitur aktif bila ada di paket, add-on, atau override', function (): void {
        $evaluator = new EvaluatorFitur;
        $sumber = BuatSumber([
            'addon' => [['KunciFitur' => 'kanal.self-order', 'TambahanBatas' => null, 'Jumlah' => 1]],
            'overrideFitur' => ['promo.mesin'],
        ]);

        expect($evaluator->CekFiturAktif($sumber, 'pos.retail'))->toBeTrue()
            ->and($evaluator->CekFiturAktif($sumber, 'kanal.self-order'))->toBeTrue()
            ->and($evaluator->CekFiturAktif($sumber, 'promo.mesin'))->toBeTrue()
            ->and($evaluator->CekFiturAktif($sumber, 'api.publik'))->toBeFalse();
    });

    it('add-on berjumlah 0 tidak memberi fitur', function (): void {
        $sumber = BuatSumber(['addon' => [['KunciFitur' => 'kanal.self-order', 'TambahanBatas' => null, 'Jumlah' => 0]]]);

        expect((new EvaluatorFitur)->CekFiturAktif($sumber, 'kanal.self-order'))->toBeFalse();
    });

    it('flag fitur global yang mati dan modul outlet yang tidak aktif mematikan fitur', function (): void {
        $evaluator = new EvaluatorFitur;

        expect($evaluator->CekFiturAktif(BuatSumber(['flagFitur' => ['pos.retail' => false]]), 'pos.retail'))->toBeFalse()
            ->and($evaluator->CekFiturAktif(BuatSumber(['flagFitur' => ['pos.retail' => true]]), 'pos.retail'))->toBeTrue()
            ->and($evaluator->CekFiturAktif(BuatSumber(['modulOutletAktif' => ['laporan.dasar']]), 'pos.retail'))->toBeFalse()
            ->and($evaluator->CekFiturAktif(BuatSumber(['modulOutletAktif' => ['pos.retail']]), 'pos.retail'))->toBeTrue();
    });

    it('batas efektif = paket + add-on × jumlah, ditimpa override; tak terbatas tetap tak terbatas', function (): void {
        $sumber = BuatSumber([
            'addon' => [
                ['KunciFitur' => null, 'TambahanBatas' => ['BatasOutlet' => 1, 'BatasSku' => 500], 'Jumlah' => 2],
                ['KunciFitur' => null, 'TambahanBatas' => ['BatasPerangkatPerOutlet' => 1], 'Jumlah' => 1],
            ],
            'overrideBatas' => ['BatasPerangkatPerOutlet' => 10],
        ]);

        expect((new EvaluatorFitur)->HitungBatasEfektif($sumber))->toBe([
            'BatasOutlet' => 3,
            'BatasPerangkatPerOutlet' => 10,
            'BatasSku' => null,
        ]);
    });

    it('memeriksa apakah pemakaian masih boleh bertambah', function (): void {
        $evaluator = new EvaluatorFitur;
        $sumber = BuatSumber();

        expect($evaluator->CekMasihDalamBatas($sumber, 'BatasOutlet', 0))->toBeTrue()
            ->and($evaluator->CekMasihDalamBatas($sumber, 'BatasOutlet', 1))->toBeFalse()
            ->and($evaluator->CekMasihDalamBatas($sumber, 'BatasSku', 999999))->toBeTrue();
    });
});
