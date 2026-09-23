<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Konfigurasi Pest (nama file mengikuti Pest, pengecualian §13.7.4).
 * - Unit & Arsitektur: tanpa database.
 * - Fitur: MySQL dengan RefreshDatabase.
 */
pest()->extend(TestCase::class)->in('Unit', 'Arsitektur');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Fitur');
