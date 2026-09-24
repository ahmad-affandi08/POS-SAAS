import PemilihTanggal, { type PropsPemilihTanggal } from '@/Komponen/Tanggal/PemilihTanggal';

export { TulisTanggal, UraiTanggal } from '@/Pustaka/Tanggal';

/** Bidang tanggal Platform Pengelola: `PemilihTanggal` standar (isian HH/BB/TTTT + kalender), nilai `TTTT-BB-HH`. */
export default function BidangTanggal(props: PropsPemilihTanggal) {
    return <PemilihTanggal {...props} />;
}
