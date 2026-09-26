<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

it('menampilkan beranda situs pemasaran (D-21) lewat Inertia dengan isi bawaan', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('Situs')
        ->assertInertia(fn (AssertableInertia $halaman) => $halaman
            ->component('Situs/Halaman')
            ->where('Halaman.Slug', 'beranda')
            ->where('Halaman.Bagian.0.Jenis', 'Hero')
            ->has('Situs.NamaSitus'));
});

it('menyediakan health check di /sehat', function (): void {
    $this->get('/sehat')->assertOk();
});
