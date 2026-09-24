import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangUang from '@/Komponen/Formulir/BidangUang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsPengaturanKasir } from '@/Tipe/Kasir';

const alamat = '/kelola/kasir/pengaturan';

/** "200000.00" → "200000" untuk BidangUang (tanpa float, hanya memotong sen nol). */
export function UbahKeMasukanUang(nilai: string): string {
    return nilai.replace(/\.00$/, '');
}

/** F-06: pengaturan kasir tenant: batas kas keluar tanpa persetujuan (BR-06.4) dan shift bersama (BR-06.2). */
export default function HalamanPengaturanKasir({ BatasKasKeluar, ShiftBersama }: PropsPengaturanKasir) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const awalBatas = UbahKeMasukanUang(BatasKasKeluar);
    const [batas, AturBatas] = useState(awalBatas);
    const [bersama, AturBersama] = useState(ShiftBersama);
    const [memproses, AturMemproses] = useState(false);
    const berubah = batas !== awalBatas || bersama !== ShiftBersama;

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.put(
            alamat,
            { BatasKasKeluar: batas === '' ? '0' : batas, ShiftBersama: bersama },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <TataLetakAplikasi judul="Pengaturan kasir">
            <DaftarGalatServer galat={galat} kecuali={['BatasKasKeluar', 'ShiftBersama']} />
            <form onSubmit={Simpan} aria-label="Pengaturan kasir" className="flex flex-col gap-4">
                <PanelKatalog
                    judul="Persetujuan kas keluar"
                    idJudul="judul-kas-keluar"
                    keterangan="Kas keluar di atas batas ini wajib disetujui supervisor dengan PIN di aplikasi kasir. Isi 0 agar setiap kas keluar butuh persetujuan."
                >
                    <BidangUang
                        label="Batas kas keluar tanpa persetujuan"
                        nilai={batas}
                        saatBerubah={AturBatas}
                        galat={galat.BatasKasKeluar}
                    />
                </PanelKatalog>
                <PanelKatalog judul="Shift bersama" idJudul="judul-shift-bersama">
                    <KotakCentang
                        label="Beberapa kasir boleh berbagi satu laci dalam satu shift"
                        nilai={bersama}
                        saatBerubah={AturBersama}
                    />
                    <p className="text-keterangan text-teks-sekunder">
                        Cocok untuk kafe kecil. Setiap transaksi dan kas tetap mencatat nama kasirnya. Jika dimatikan,
                        setiap kasir membuka shift sendiri dan hanya kasir itu atau supervisor yang bisa mencatat kas di
                        shiftnya.
                    </p>
                </PanelKatalog>
                <div className="flex flex-wrap items-center gap-3">
                    <Tombol type="submit" memproses={memproses} disabled={!berubah}>
                        Simpan pengaturan
                    </Tombol>
                    {!berubah ? <span className="text-keterangan text-teks-sekunder">Belum ada perubahan.</span> : null}
                </div>
            </form>
        </TataLetakAplikasi>
    );
}
