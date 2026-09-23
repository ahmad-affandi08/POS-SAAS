<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Database;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * Macro migrasi agar kolom standar selalu bernama Bahasa Indonesia (PRD §13.7.2, §15.1).
 *
 *     $tabel->id('Id');
 *     $tabel->UuidPublik();    // char(26) unik, ULID publik (bukan ->Uuid(): nama method PHP tidak case-sensitive
 *                              // sehingga ->Uuid() memanggil uuid() bawaan Laravel dan membuat kolom 'uuid')
 *     $tabel->IdTenant();      // wajib untuk tabel milik tenant
 *     $tabel->WaktuStandar();  // DibuatPada, DiubahPada
 */
final class MakroSkema
{
    public static function Daftarkan(): void
    {
        Blueprint::macro('WaktuStandar', function (): void {
            /** @var Blueprint $this */
            $this->timestamp(ModelDasar::CREATED_AT)->nullable();
            $this->timestamp(ModelDasar::UPDATED_AT)->nullable();
        });

        Blueprint::macro('UuidPublik', function (): ColumnDefinition {
            /** @var Blueprint $this */
            return $this->char('Uuid', 26)->unique('Uniq'.$this->getTable().'Uuid');
        });

        Blueprint::macro('IdTenant', function (): ColumnDefinition {
            /** @var Blueprint $this */
            return $this->unsignedBigInteger('IdTenant')->index('Idx'.$this->getTable().'IdTenant');
        });
    }
}
