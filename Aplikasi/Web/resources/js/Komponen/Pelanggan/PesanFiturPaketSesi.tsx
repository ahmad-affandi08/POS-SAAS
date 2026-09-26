import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';

/** F-16d bagian 2: paket tenant belum punya fitur `pelanggan.paket-sesi`. */
export default function PesanFiturPaketSesi() {
    return (
        <Pemberitahuan jenis="info" judul="Paket sesi tersedia di paket Pro ke atas">
            Naikkan paket di menu Langganan untuk menjual paket sesi (misal 10x creambath) dan mencatat pemakaiannya di
            kasir.
        </Pemberitahuan>
    );
}
