<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as TestCaseDasar;

abstract class TestCase extends TestCaseDasar
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test HTTP tidak bergantung pada aset Vite yang sudah di-build (CI tidak menjalankan `npm run build` di job PHP).
        $this->withoutVite();
    }
}
