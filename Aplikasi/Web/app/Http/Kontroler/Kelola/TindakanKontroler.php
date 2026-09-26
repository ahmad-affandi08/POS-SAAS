<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Bersama\Tindakan\Aksi\TandaiDokumenDitinjau;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Kueri\KotakTindakan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\KonteksTindakanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kotak Tindakan back-office (D-23 C): semua yang perlu perhatian di satu halaman, disaring izin & outlet akses pelaku.
 * Menandai dokumen "sudah dicek": izin `tindakan.tinjau`.
 */
final class TindakanKontroler extends DasarKelolaKontroler
{
    public function Daftar(KotakTindakan $kotak, AksesPengguna $akses, KonteksTindakanPengguna $konteks, TanggalBisnisOutlet $tanggal): Response
    {
        $bolehTandai = $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::TindakanTinjau);

        return Inertia::render('Kelola/Tindakan', [
            'Butir' => array_map(fn (DataButirTindakan $b): array => $b->KeLarik($bolehTandai), $kotak->Ambil($konteks->Buat($this->IdTenant(), $this->Pelaku()->Id, $tanggal->Hitung(null)))),
            'Izin' => ['Tandai' => $bolehTandai],
        ]);
    }

    public function Tandai(Request $permintaan, TandaiDokumenDitinjau $tandai, KotakTindakan $kotak, KonteksTindakanPengguna $konteks, TanggalBisnisOutlet $tanggal): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Jenis' => ['required', 'string', 'max:40'],
            'Uuid' => ['required', 'array', 'min:1', 'max:'.TandaiDokumenDitinjau::MAKS],
            'Uuid.*' => ['string', 'ulid'],
            'Catatan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['Uuid' => 'dokumen', 'Catatan' => 'catatan']);
        $jenis = (string) $valid['Jenis'];
        $uuid = array_values(array_map('strval', (array) $valid['Uuid']));

        // Dokumen di luar outlet akses pelaku dianggap tidak ada.
        $idOutlet = $this->IdOutletBoleh();

        if ($idOutlet !== null) {
            $boleh = [];

            foreach ($kotak->Ambil($konteks->Buat($this->IdTenant(), $this->Pelaku()->Id, $tanggal->Hitung(null))) as $b) {
                if ($b->jenisDokumen === $jenis) {
                    foreach ($b->rincian as $r) {
                        $boleh[] = $r->uuid;
                    }
                }
            }

            $uuid = array_values(array_intersect(array_map('strtoupper', $uuid), $boleh));
            abort_if($uuid === [], 404);
        }

        $jumlah = $tandai->Jalankan($jenis, $uuid, $this->Pelaku()->Id, isset($valid['Catatan']) ? (string) $valid['Catatan'] : null);

        return back()->with('Kilat', $jumlah === 0 ? 'Semua dokumen yang dipilih sudah ditandai sebelumnya.' : "{$jumlah} dokumen ditandai sudah dicek.");
    }
}
