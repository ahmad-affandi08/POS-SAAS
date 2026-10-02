<?php

declare(strict_types=1);

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\BukaUlangShift;
use App\Domain\Kasir\Model\Shift;
use App\Domain\Organisasi\Model\Pengguna;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * K-18 (F-06 `Tertutup → DibukaUlang`, §19.1): supervisor membuka ulang shift lewat outbox `Shift.BukaUlang`. Data tutup
 * disalin ke log, jurnal selisih dibalik, tutup lama yang terkirim ulang = Duplikat, tutup ulang membuat jurnal baru.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * @param  array<string, mixed>  $k
 * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
 */
function ItemTutupUlangUji(array $k, string $kasAktual, string $kasSeharusnya, string $waktu): array
{
    return ['Jenis' => 'Shift.Tutup', 'Uuid' => BantuanKasir::Uuid(), 'Data' => [
        'UuidShift' => $k['UuidShift'],
        'UuidPengguna' => $k['Kasir']->Uuid,
        'DitutupPada' => $waktu,
        'KasAktual' => $kasAktual,
        'PecahanKasAkhir' => null,
        'NonTunai' => [],
        'Alasan' => null,
        'UuidPenyetuju' => null,
        'Ringkasan' => ['KasSeharusnya' => $kasSeharusnya, 'Selisih' => Uang::Dari($kasAktual)->Kurangi(Uang::Dari($kasSeharusnya))->KeString()],
    ]];
}

/**
 * @param  array<string, mixed>  $k
 * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
 */
function ItemBukaUlangUji(array $k, Pengguna $penyetuju, string $alasan = 'Lupa mencatat penjualan terakhir', ?string $waktu = null): array
{
    return ['Jenis' => 'Shift.BukaUlang', 'Uuid' => BantuanKasir::Uuid(), 'Data' => [
        'UuidShift' => $k['UuidShift'],
        'UuidPengguna' => $k['Kasir']->Uuid,
        'UuidPenyetuju' => $penyetuju->Uuid,
        'Alasan' => $alasan,
        'DibukaUlangPada' => $waktu ?? now()->subMinutes(20)->utc()->toIso8601ZuluString(),
    ]];
}

it('buka ulang: snapshot & kolom tutup dikosongkan, jurnal selisih dibalik, tutup lama terkirim ulang = Duplikat, tutup ulang berjurnal baru', function (): void {
    $k = BantuanPenjualan::Siapkan($this);
    $produk = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
    $kirim = fn (array $item): array => tap(BantuanKasir::KirimRingkas($this, $k['Token'], $item), fn () => BantuanOrganisasi::AturKonteks($k['Tenant']->Id));
    expect($kirim([BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $produk, 'Jumlah' => '2', 'Harga' => '38500.00']], 'Pembayaran' => [['Metode' => $k['Tunai'], 'Jumlah' => '77000.00']]])]))->toBe([['Diterima', null]]);

    // Tutup pertama kurang Rp 7.000 (seharusnya 577.000).
    $tutup1 = ItemTutupUlangUji($k, '570000.00', '577000.00', now()->subMinutes(30)->utc()->toIso8601ZuluString());
    expect($kirim([$tutup1]))->toBe([['Diterima', null]]);
    $shift = Shift::query()->where('Uuid', $k['UuidShift'])->sole();
    $jurnalTutup = Jurnal::query()->where('JenisSumber', JenisSumberJurnal::TutupShift->value)->where('IdSumber', $shift->Id)->sole();

    // Tanpa supervisor, alasan pendek ditolak.
    expect($kirim([ItemBukaUlangUji($k, $k['Kasir'])]))->toBe([['Ditolak', 'PenyetujuTidakBerwenang']])
        ->and($kirim([ItemBukaUlangUji($k, $k['Supervisor'], 'ok')]))->toBe([['Ditolak', 'AlasanDiperlukan']]);

    $bukaUlang = ItemBukaUlangUji($k, $k['Supervisor']);
    expect($kirim([$bukaUlang]))->toBe([['Diterima', null]])
        ->and($kirim([$bukaUlang]))->toBe([['Duplikat', null]]);

    $shift->refresh();
    $log = BukaUlangShift::query()->where('Uuid', $bukaUlang['Uuid'])->sole();
    expect($shift->Status)->toBe(StatusShift::DibukaUlang)
        ->and($shift->DitutupPada)->toBeNull()->and($shift->KasAktual)->toBeNull()
        ->and($log->Urutan)->toBe(1)
        ->and(Uang::Dari((string) $log->SnapshotTutup['Selisih'])->SamaDengan(Uang::Dari('-7000')))->toBeTrue()
        ->and(Jurnal::query()->where('IdJurnalDibalik', $jurnalTutup->Id)->sole()->Id)->toBe($log->IdJurnalPembalik)
        ->and(LogAudit::query()->where('Peristiwa', 'shift.buka-ulang')->count())->toBe(1);

    // Kiriman ulang tutup lama (jawabannya hilang di jaringan) tidak menutup shift lagi.
    expect($kirim([$tutup1]))->toBe([['Duplikat', null]])
        ->and($shift->refresh()->Status)->toBe(StatusShift::DibukaUlang);

    // Penjualan susulan lalu tutup ulang (kurang Rp 1.000; seharusnya 577.000 + 38.500 = 615.500).
    expect($kirim([BantuanPenjualan::Item($k, ['Baris' => [['Produk' => $produk, 'Jumlah' => '1', 'Harga' => '38500.00']], 'Pembayaran' => [['Metode' => $k['Tunai'], 'Jumlah' => '38500.00']]])]))->toBe([['Diterima', null]]);
    expect($kirim([ItemTutupUlangUji($k, '614500.00', '615500.00', now()->subMinutes(5)->utc()->toIso8601ZuluString())]))->toBe([['Diterima', null]]);
    $shift->refresh();
    expect($shift->Status)->toBe(StatusShift::Tertutup)
        ->and(Uang::Dari((string) $shift->Selisih)->SamaDengan(Uang::Dari('-1000')))->toBeTrue()
        ->and(Jurnal::query()->where('JenisSumber', JenisSumberJurnal::TutupShift->value)->where('IdSumber', $shift->Id)->where('KunciSumber', 'Tutup-2')->sole()->TotalDebit)->toBe('1000.00');

    // Shift yang belum ditutup tidak bisa dibuka ulang; invarian jurnal & stok terjaga.
    expect($kirim([ItemBukaUlangUji($k, $k['Supervisor'], waktu: now()->subMinute()->utc()->toIso8601ZuluString())]))->toBe([['Diterima', null]])
        ->and($kirim([ItemBukaUlangUji($k, $k['Supervisor'], waktu: now()->subMinute()->utc()->toIso8601ZuluString())]))->toBe([['Ditolak', 'ShiftTidakTertutup']])
        ->and(PemeriksaInvarian::PeriksaSemua($k['Tenant']->Id))->toBe([]);
});
