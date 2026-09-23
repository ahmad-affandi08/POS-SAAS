<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-01 / P-03 (BR-P03.1, §25 no. 15): versi template yang diterapkan ke outlet. Expand: kolom nullable.
 * Hanya versi Terbit yang dirujuk; versi terbit/usang tidak pernah dihapus (BR-P03.2), jadi FK restrict aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->foreignId('IdTemplateSektorVersi')->nullable()->after('TemplateSektor')
                ->constrained('TemplateSektorVersi', 'Id', 'FkOutletIdTemplateSektorVersi')->restrictOnDelete();
            $tabel->timestamp('TemplateSektorDiterapkanPada')->nullable()->after('IdTemplateSektorVersi');
        });
    }

    public function down(): void
    {
        Schema::table('Outlet', function (Blueprint $tabel): void {
            $tabel->dropForeign('FkOutletIdTemplateSektorVersi');
            $tabel->dropColumn(['IdTemplateSektorVersi', 'TemplateSektorDiterapkanPada']);
        });
    }
};
