<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

it('menampilkan halaman Beranda lewat Inertia dengan nama aplikasi', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $halaman) => $halaman
            ->component('Beranda')
            ->has('NamaAplikasi'));
});

it('menyediakan health check di /sehat', function (): void {
    $this->get('/sehat')->assertOk();
});
