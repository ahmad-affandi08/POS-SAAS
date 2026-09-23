<?php

declare(strict_types=1);

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Model;

/*
 * Test arsitektur (PRD §13.2, §23.4). File penjaga: hanya manusia yang mengubah.
 * Kalau test ini gagal, perbaiki kodenya, bukan test-nya.
 */

arch('preset keamanan PHP')->preset()->security();

arch('tidak ada fungsi debug yang tertinggal')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'var_export', 'debug_zval_refcount'])
    ->not->toBeUsed();

arch('semua kode aplikasi memakai strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('lapisan domain tidak bergantung pada lapisan HTTP')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

arch('semua model domain mewarisi ModelDasar')
    ->expect('App\Domain')
    ->classes()
    ->extending(Model::class)
    ->toExtend(ModelDasar::class)
    ->ignoring(ModelDasar::class);

arch('kontroler berada di App\Http\Kontroler dan berakhiran Kontroler')
    ->expect('App\Http\Kontroler')
    ->classes()
    ->toHaveSuffix('Kontroler');

arch('value object nilai (Uang, Kuantitas) final dan readonly')
    ->expect('App\Domain\Bersama\Nilai')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('uang tidak dikonversi lewat float')
    ->expect(['floatval', 'round', 'number_format'])
    ->not->toBeUsed()
    ->ignoring('App\Domain\Bersama\Nilai');

arch('LingkupTenant hanya dipakai mekanisme tenant dan Domain/Pengelola')
    ->expect('App\Domain\Bersama\Tenant\LingkupTenant')
    ->toOnlyBeUsedIn(['App\Domain\Bersama\Tenant', 'App\Domain\Pengelola']);
