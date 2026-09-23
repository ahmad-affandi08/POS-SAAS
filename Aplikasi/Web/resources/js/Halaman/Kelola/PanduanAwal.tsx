import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

/** Titik masuk setelah daftar (F-00 langkah 4). Wizard onboarding & template sektor dibangun F-01. */
export default function HalamanPanduanAwal() {
    return (
        <TataLetakAplikasi judul="Panduan awal">
            <Pemberitahuan jenis="info" judul="Akun usaha Anda sudah siap">
                Outlet Utama dan gudangnya sudah dibuat. Langkah memilih jenis usaha, produk, dan perangkat kasir akan
                tersedia di sini.
            </Pemberitahuan>
        </TataLetakAplikasi>
    );
}
