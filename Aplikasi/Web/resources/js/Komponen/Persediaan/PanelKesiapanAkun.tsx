import { Link } from '@inertiajs/react';

import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type { KesiapanAkun } from '@/Tipe/Persediaan';

/**
 * Peringatan akun jurnal persediaan yang belum dipetakan (DesainF05a C.5 `KesiapanPeranAkun`, E). Posting stok awal
 * akan ditolak server (`PemetaanAkunBelumAda`) selama daftar ini belum kosong. Tidak dirender bila akun siap.
 */
export default function PanelKesiapanAkun({ kesiapan }: { kesiapan: KesiapanAkun }) {
    if (kesiapan.Siap) {
        return null;
    }

    return (
        <Pemberitahuan jenis="peringatan" judul="Akun jurnal persediaan belum lengkap">
            <p>Stok awal belum bisa diposting karena akun berikut belum dipetakan. Draf tetap bisa disimpan.</p>
            {kesiapan.PeranBelumDipetakan.length > 0 ? (
                <ul className="mt-1 list-disc pl-5">
                    {kesiapan.PeranBelumDipetakan.map((peran) => (
                        <li key={peran.Kunci}>{peran.Label}</li>
                    ))}
                </ul>
            ) : null}
            <p className="mt-1">
                Terapkan template sektor di{' '}
                <Link href="/kelola/panduan-awal" className="font-semibold text-brand underline">
                    Panduan awal
                </Link>{' '}
                atau minta Akuntan memetakan akun.
            </p>
        </Pemberitahuan>
    );
}
