<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Model\PengaturanReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use Carbon\CarbonImmutable;

/**
 * F-07 mode service: slot kosong satu tanggal di outlet untuk layanan berdurasi tertentu. Dasar = jadwal kerja staf
 * di outlet itu (`JadwalStafReservasi`); slot mulai tiap `IntervalSlotMenit` sejak jam mulai jadwal dan selesai
 * paling lambat jam selesai jadwal. Slot dibuang bila sebelum [palingCepat] atau bentrok dengan reservasi staf yang
 * masih memakai slot (di outlet mana pun) ditambah `JedaMenit` sebelum/sesudah. Jam dalam zona waktu outlet.
 */
final class SlotReservasi
{
    public function __construct(
        private readonly JadwalStafReservasi $jadwal,
        private readonly ZonaWaktuOutlet $zona,
    ) {}

    /**
     * @return list<array{Jam: string, Staf: list<array{Id: int, Uuid: string, Nama: string}>}>
     */
    public function Hitung(
        int $idOutlet,
        string $tanggal,
        int $durasiMenit,
        ?int $idKaryawan,
        PengaturanReservasi $atur,
        CarbonImmutable $palingCepat,
        ?int $kecualiIdReservasi = null,
    ): array {
        $zona = $this->zona->Ambil($idOutlet);
        $staf = array_values(array_filter($this->jadwal->Ambil($idOutlet, $tanggal), fn (array $s): bool => $idKaryawan === null || $s['Id'] === $idKaryawan));

        if ($staf === [] || $durasiMenit < 1) {
            return [];
        }

        $awalHari = CarbonImmutable::parse($tanggal, $zona)->startOfDay();
        $terpakai = Reservasi::query()
            ->whereIn('IdKaryawan', array_column($staf, 'Id'))
            ->whereIn('Status', StatusReservasi::AmbilNilaiMemakaiSlot())
            ->when($kecualiIdReservasi !== null, fn ($k) => $k->whereKeyNot($kecualiIdReservasi))
            ->where('MulaiPada', '<', $awalHari->addDays(2)->utc())
            ->where('SelesaiPada', '>', $awalHari->subDay()->utc())
            ->get(['IdKaryawan', 'MulaiPada', 'SelesaiPada'])
            ->groupBy('IdKaryawan');
        $interval = max(5, $atur->IntervalSlotMenit);
        $jeda = max(0, $atur->JedaMenit);
        $slot = [];

        foreach ($staf as $s) {
            $mulai = CarbonImmutable::parse("{$tanggal} {$s['JamMulai']}", $zona);
            $selesai = CarbonImmutable::parse("{$tanggal} {$s['JamSelesai']}", $zona);

            for ($t = $mulai; $t->addMinutes($durasiMenit)->lte($selesai); $t = $t->addMinutes($interval)) {
                $akhir = $t->addMinutes($durasiMenit);

                if ($t->lt($palingCepat)) {
                    continue;
                }

                $bentrok = ($terpakai->get($s['Id']) ?? collect())->contains(
                    fn (Reservasi $r): bool => $r->MulaiPada->copy()->subMinutes($jeda)->lt($akhir) && $r->SelesaiPada->copy()->addMinutes($jeda)->gt($t),
                );

                if (! $bentrok) {
                    $slot[$t->format('H:i')][] = ['Id' => $s['Id'], 'Uuid' => $s['Uuid'], 'Nama' => $s['Nama']];
                }
            }
        }

        ksort($slot);

        return array_values(array_map(fn (string $jam, array $staf): array => ['Jam' => $jam, 'Staf' => $staf], array_keys($slot), $slot));
    }
}
