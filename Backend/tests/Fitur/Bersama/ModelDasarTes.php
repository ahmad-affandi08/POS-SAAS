<?php

declare(strict_types=1);

use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

describe('ModelDasar & macro skema (PRD §13.7.2, §15.1)', function (): void {
    it('memakai Id, Uuid ULID, DibuatPada, dan DiubahPada', function (): void {
        $pengguna = Pengguna::factory()->create();

        expect($pengguna->getKeyName())->toBe('Id')
            ->and($pengguna->Uuid)->toHaveLength(26)
            ->and($pengguna->getAttribute('DibuatPada'))->not->toBeNull()
            ->and($pengguna->getAttribute('DiubahPada'))->not->toBeNull()
            ->and($pengguna->getRouteKeyName())->toBe('Uuid')
            ->and($pengguna->getForeignKey())->toBe('IdPengguna');
    });

    it('membuat kolom standar berbahasa Indonesia lewat macro', function (): void {
        expect(Schema::hasColumns('Pengguna', ['Id', 'Uuid', 'DibuatPada', 'DiubahPada']))->toBeTrue()
            ->and(Schema::hasColumns('UjiCatatan', ['IdTenant']))->toBeTrue();
    });

    it('memberi nama indeks eksplisit PascalCase, bukan nama otomatis Laravel', function (): void {
        $indeks = collect(DB::select('SHOW INDEX FROM `Pengguna`'))->pluck('Key_name')->unique()->values()->all();

        expect($indeks)->toContain('UniqPenggunaUuid', 'UniqPenggunaEmail')
            ->and(collect($indeks)->filter(fn (string $nama): bool => str_contains($nama, '_'))->all())->toBe([]);
    });

    it('menyimpan kata sandi ter-hash dan menyembunyikannya dari serialisasi', function (): void {
        $pengguna = Pengguna::factory()->create(['KataSandi' => 'rahasia-sekali']);

        expect(Hash::check('rahasia-sekali', $pengguna->getAuthPassword()))->toBeTrue()
            ->and($pengguna->toArray())->not->toHaveKey('KataSandi');
    });
});
