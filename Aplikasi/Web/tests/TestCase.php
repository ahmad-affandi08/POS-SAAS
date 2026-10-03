<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as TestCaseDasar;

abstract class TestCase extends TestCaseDasar
{
    /**
     * D-35: edisi aplikasi untuk test berikutnya (null = bawaan `Saas`). Edisi menentukan rute & jadwal yang
     * didaftarkan saat aplikasi dibuat, jadi dipasang ke lingkungan sebelum `parent::setUp()` membuat aplikasi;
     * berkas test edisi Lisensi mengaturnya di `beforeAll` dan mengembalikannya di `afterAll`.
     */
    public static ?string $edisiUji = null;

    protected function setUp(): void
    {
        // Selalu diisi eksplisit (bukan dihapus) agar `EDISI` di `.env` lokal pengembang tidak ikut terbaca test.
        $edisi = self::$edisiUji ?? 'Saas';
        putenv('EDISI='.$edisi);
        $_ENV['EDISI'] = $_SERVER['EDISI'] = $edisi;

        parent::setUp();
        // Test HTTP tidak bergantung pada aset Vite yang sudah di-build (CI tidak menjalankan `npm run build` di job PHP).
        $this->withoutVite();
    }
}
