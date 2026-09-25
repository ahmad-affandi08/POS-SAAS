import { Link, router, usePage } from '@inertiajs/react';

import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import FormOutlet, { type IsianOutlet } from '@/Komponen/Kelola/FormOutlet';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { CekBatasPenuh, FormatBatas, type PropsBuatOutlet } from '@/Tipe/Organisasi';

/**
 * Halaman "Tambah outlet" (F-02 langkah 1, BR-02.1, BR-02.2). FormOutlet sudah berupa kartu sendiri (juga dipakai di
 * detail outlet), jadi tidak dibungkus KartuFormulir. Setelah disimpan, server mengarahkan ke detail outlet baru.
 */
export default function HalamanBuatOutlet({ Merek, Kota, BatasOutlet }: PropsBuatOutlet) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const awal: IsianOutlet = {
        Nama: '',
        Kode: '',
        Merek: Merek[0]?.Nilai ?? '',
        Alamat: '',
        KodeKota: '',
        ZonaWaktu: 'WIB',
        JamTutupBuku: '04:00',
        Pkp: false,
        Nitku: '',
        PungutPbjt: false,
    };
    const isianForm = Object.keys(awal);

    return (
        <TataLetakAplikasi judul="Tambah outlet">
            <DaftarGalatServer
                galat={props.errors}
                kecuali={Object.keys(props.errors).filter((k) => isianForm.some((i) => k.startsWith(i)))}
            />
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Outlet aktif:{' '}
                <span className="font-semibold text-teks-utama">{FormatBatas(BatasOutlet, 'outlet')}</span>. Setiap
                outlet baru langsung mendapat lokasi stok Toko.
            </p>
            {CekBatasPenuh(BatasOutlet) ? (
                <Pemberitahuan jenis="info" judul="Batas outlet paket sudah tercapai">
                    Tingkatkan paket atau tambah add-on outlet di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    . Outlet yang diarsipkan tidak dihitung.
                </Pemberitahuan>
            ) : null}
            <FormOutlet
                uuid={null}
                awal={awal}
                merek={Merek}
                kota={Kota}
                saatBatal={() => router.visit('/kelola/outlet')}
            />
        </TataLetakAplikasi>
    );
}
