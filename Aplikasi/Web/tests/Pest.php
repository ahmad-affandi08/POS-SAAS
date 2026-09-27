<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Konfigurasi Pest (nama file mengikuti Pest, pengecualian §13.7.4).
 * - Unit & Arsitektur: tanpa database.
 * - Fitur: MySQL dengan RefreshDatabase.
 * - Konkurensi (audit F-07): MySQL dengan DatabaseTruncation, karena data harus di-commit agar terlihat oleh proses
 *   pekerja lain (dua koneksi nyata).
 */
pest()->extend(TestCase::class)->in('Unit', 'Arsitektur');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Fitur');
pest()->extend(TestCase::class)->use(DatabaseTruncation::class)->in('Konkurensi');
