<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Galat;

use RuntimeException;

/** Berkas lisensi rusak, tanda tangannya tidak cocok, atau belum bisa diverifikasi (D-35). Pesan aman ditampilkan. */
final class LisensiTidakSah extends RuntimeException {}
