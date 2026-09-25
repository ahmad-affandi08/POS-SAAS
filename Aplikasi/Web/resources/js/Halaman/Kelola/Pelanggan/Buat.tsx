import { router, usePage } from '@inertiajs/react';

import KartuFormulir from '@/Komponen/Formulir/KartuFormulir';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import { AlamatPelanggan, IsiFormulirPelanggan, IsianFormulirPelanggan } from '@/Komponen/Pelanggan/FormulirPelanggan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/** F-16a halaman "Tambah pelanggan". Setelah disimpan, server mengarahkan ke detail pelanggan baru. */
export default function HalamanBuatPelanggan() {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <TataLetakAplikasi judul="Tambah pelanggan">
            <DaftarGalatServer
                galat={props.errors}
                kecuali={Object.keys(props.errors).filter((k) => IsianFormulirPelanggan.some((i) => k.startsWith(i)))}
            />
            <KartuFormulir keterangan="Data pelanggan untuk riwayat belanja, loyalti, dan promo. Nomor HP menjadi kunci pelanggan.">
                <IsiFormulirPelanggan pelanggan={null} saatBatal={() => router.visit(AlamatPelanggan)} />
            </KartuFormulir>
        </TataLetakAplikasi>
    );
}
