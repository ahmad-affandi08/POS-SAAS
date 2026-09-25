import { Link } from '@inertiajs/react';

import { Button } from '@/Komponen/Ui/button';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

/** Keadaan "Tanpa izin" (PRD §17.6.6): halaman dibuka lewat tautan tetapi peran pengguna tidak mencakupnya. */
export default function HalamanTanpaIzin() {
    return (
        <TataLetakAplikasi judul="Tidak punya akses">
            <Pemberitahuan jenis="peringatan" judul="Anda tidak punya akses ke halaman ini">
                <p>Peran Anda belum mencakup halaman ini. Hubungi Owner bila Anda membutuhkannya.</p>
                <p className="mt-2">
                    <Button asChild variant="outline" className="h-8 pointer-coarse:h-11 text-label font-semibold">
                        <Link href="/kelola">Kembali ke beranda</Link>
                    </Button>
                </p>
            </Pemberitahuan>
        </TataLetakAplikasi>
    );
}
