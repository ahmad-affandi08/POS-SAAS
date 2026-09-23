<?php

declare(strict_types=1);

use App\Domain\Referensi\Model\Wilayah;

function TulisCsvWilayah(string $isi): string
{
    $path = tempnam(sys_get_temp_dir(), 'wilayah').'.csv';
    file_put_contents($path, $isi);

    return $path;
}

describe('Impor wilayah dari CSV (P-02)', function (): void {
    it('memuat provinsi & kabupaten/kota lalu menyelaraskan tanpa duplikat saat diulang', function (): void {
        $path = TulisCsvWilayah("Kode,Nama,Tingkat,KodeInduk,ZonaWaktu\n33.74,Kota Semarang,KabupatenKota,33,WIB\n33,Jawa Tengah,Provinsi,,WIB\n51,Bali,Provinsi,,WITA\n");

        $this->artisan('pengelola:impor-wilayah', ['berkas' => $path])
            ->expectsOutputToContain('3 baru, 0 diperbarui')
            ->assertSuccessful();

        file_put_contents($path, "Kode,Nama,Tingkat,KodeInduk,ZonaWaktu\n33,Jawa Tengah,Provinsi,,WIB\n51,Provinsi Bali,Provinsi,,WITA\n");
        $this->artisan('pengelola:impor-wilayah', ['berkas' => $path])
            ->expectsOutputToContain('0 baru, 1 diperbarui')
            ->assertSuccessful();

        expect(Wilayah::query()->count())->toBe(3)
            ->and(Wilayah::query()->where('Kode', '51')->sole()->Nama)->toBe('Provinsi Bali');
        $this->assertDatabaseCount('LogAuditPengelola', 2);
    });

    it('menolak seluruh berkas bila satu baris tidak valid', function (string $isi): void {
        $path = TulisCsvWilayah("Kode,Nama,Tingkat,KodeInduk,ZonaWaktu\n33,Jawa Tengah,Provinsi,,WIB\n".$isi);

        $this->artisan('pengelola:impor-wilayah', ['berkas' => $path])->assertFailed();

        expect(Wilayah::query()->count())->toBe(0);
    })->with([
        'induk tidak ada' => ["34.01,Kulon Progo,KabupatenKota,34,WIB\n"],
        'zona salah' => ["33.74,Kota Semarang,KabupatenKota,33,GMT\n"],
        'kode ganda' => ["33,Jateng Lagi,Provinsi,,WIB\n"],
    ]);

    it('menolak kepala kolom yang salah', function (): void {
        $path = TulisCsvWilayah("kode,nama\n33,Jawa Tengah\n");

        $this->artisan('pengelola:impor-wilayah', ['berkas' => $path])->assertFailed();
    });
});
