<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Katalog\Aksi\SimpanFitur;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\CakupanFlagFitur;
use App\Domain\Tenant\Model\FlagFitur;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * P-10 flag fitur: menyimpan (buat/timpa) atau menghapus satu aturan per (kunci, cakupan, objek). Kunci berformat D-06
 * seperti katalog fitur, boleh kunci remote aplikasi yang tidak ada di katalog. Kill switch = aturan Global bernilai
 * mati. BR-P10.3: setiap perubahan wajib beralasan dan diaudit (`flag-fitur.simpan`, `flag-fitur.hapus`).
 */
final class UbahAturanFlagFitur
{
    public const PANJANG_ALASAN_MINIMAL = 10;

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Simpan(PenggunaPengelola $pelaku, string $kunci, CakupanFlagFitur $cakupan, ?string $uuidObjek, bool $nilai, ?int $persen, string $alasan): FlagFitur
    {
        $alasan = self::PastikanAlasan($alasan);

        if (preg_match(SimpanFitur::POLA_KUNCI, $kunci) !== 1) {
            throw new PelanggaranAturanBisnis('KunciTidakValid', 'Kunci flag huruf kecil dipisah titik, misal pos.mode-meja.', 'Kunci');
        }

        $idObjek = $this->CariObjek($cakupan, $uuidObjek);

        if ($cakupan === CakupanFlagFitur::Persentase && ($persen === null || $persen < 0 || $persen > 100)) {
            throw new PelanggaranAturanBisnis('PersenTidakValid', 'Persen tenant 0 sampai 100.', 'Persen');
        }

        return DB::transaction(function () use ($pelaku, $kunci, $cakupan, $idObjek, $nilai, $persen, $alasan): FlagFitur {
            $flag = FlagFitur::query()
                ->where('Kunci', $kunci)
                ->where('Cakupan', $cakupan->value)
                ->when($idObjek === null, fn ($k) => $k->whereNull('IdObjek'), fn ($k) => $k->where('IdObjek', $idObjek))
                ->lockForUpdate()
                ->first();
            $lama = $flag?->only(['Nilai', 'Persen']);
            $flag ??= new FlagFitur(['Kunci' => $kunci, 'Cakupan' => $cakupan, 'IdObjek' => $idObjek]);
            $flag->fill([
                'Nilai' => $cakupan === CakupanFlagFitur::Persentase ? true : $nilai,
                'Persen' => $cakupan === CakupanFlagFitur::Persentase ? $persen : null,
                'Alasan' => $alasan,
                'DiubahOleh' => $pelaku->Id,
            ])->save();

            $this->audit->Catat('flag-fitur.simpan', $flag, nilaiLama: $lama, nilaiBaru: [
                'Kunci' => $kunci,
                'Cakupan' => $cakupan->value,
                'IdObjek' => $idObjek,
                'Nilai' => $flag->Nilai,
                'Persen' => $flag->Persen,
            ], alasan: $alasan, idPelaku: $pelaku->Id, idTenant: $cakupan === CakupanFlagFitur::Tenant ? $idObjek : null);

            return $flag;
        });
    }

    public function Hapus(PenggunaPengelola $pelaku, FlagFitur $flag, string $alasan): void
    {
        $alasan = self::PastikanAlasan($alasan);

        DB::transaction(function () use ($pelaku, $flag, $alasan): void {
            $this->audit->Catat('flag-fitur.hapus', $flag, nilaiLama: [
                'Kunci' => $flag->Kunci,
                'Cakupan' => $flag->Cakupan->value,
                'IdObjek' => $flag->IdObjek,
                'Nilai' => $flag->Nilai,
                'Persen' => $flag->Persen,
            ], alasan: $alasan, idPelaku: $pelaku->Id);
            $flag->delete();
        });
    }

    private function CariObjek(CakupanFlagFitur $cakupan, ?string $uuidObjek): ?int
    {
        return match ($cakupan) {
            CakupanFlagFitur::Global, CakupanFlagFitur::Persentase => null,
            CakupanFlagFitur::Paket => Paket::query()->where('Uuid', (string) $uuidObjek)->value('Id')
                ?? throw new PelanggaranAturanBisnis('ObjekTidakDikenal', 'Pilih paket.', 'Objek'),
            CakupanFlagFitur::Tenant => Tenant::query()->where('Uuid', (string) $uuidObjek)->value('Id')
                ?? throw new PelanggaranAturanBisnis('ObjekTidakDikenal', 'Pilih tenant.', 'Objek'),
        };
    }

    private static function PastikanAlasan(string $alasan): string
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::PANJANG_ALASAN_MINIMAL || mb_strlen($alasan) > 500) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan perubahan flag, '.self::PANJANG_ALASAN_MINIMAL.' sampai 500 karakter (BR-P10.3).', 'Alasan');
        }

        return $alasan;
    }
}
