import { usePage } from '@inertiajs/react';

import RenderBagian from '@/Komponen/Situs/Bagian/RenderBagian';
import TataLetakSitus from '@/TataLetak/TataLetakSitus';
import type { PropsHalamanSitus } from '@/Tipe/Situs';

/** Satu halaman situs pemasaran (D-21): beranda, fitur, harga, solusi, dan halaman buatan konsol. */
export default function Halaman() {
    const { Halaman: halaman } = usePage<PropsHalamanSitus>().props;

    return (
        <TataLetakSitus judul={halaman.Seo.Judul} pratinjau={halaman.Pratinjau === true}>
            <RenderBagian bagian={halaman.Bagian} />
        </TataLetakSitus>
    );
}
