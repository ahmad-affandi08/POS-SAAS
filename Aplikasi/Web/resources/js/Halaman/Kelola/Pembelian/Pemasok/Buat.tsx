import { router, usePage } from '@inertiajs/react';

import KartuFormulir from '@/Komponen/Formulir/KartuFormulir';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormPemasok, { AlamatPemasok, IsianPemasokKosong } from '@/Komponen/Pembelian/FormPemasok';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/** F-04 fase 1 halaman "Tambah pemasok". Setelah disimpan, server mengarahkan kembali ke daftar pemasok. */
export default function HalamanBuatPemasok() {
    const { props } = usePage<PropsBersamaAplikasi>();

    return (
        <TataLetakAplikasi judul="Tambah pemasok">
            <DaftarGalatServer galat={props.errors} kecuali={Object.keys(IsianPemasokKosong)} />
            <KartuFormulir keterangan="Pemasok dipakai di pesanan pembelian, penerimaan barang, dan hutang. Termin bawaan dipakai untuk menghitung jatuh tempo faktur.">
                <FormPemasok uuid={null} awal={IsianPemasokKosong} saatBatal={() => router.visit(AlamatPemasok)} />
            </KartuFormulir>
        </TataLetakAplikasi>
    );
}
