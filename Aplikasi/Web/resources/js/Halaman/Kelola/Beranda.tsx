import { ChartNoAxesColumnIcon } from 'lucide-react';

import DaftarLangkahBerikutnya from '@/Komponen/Kelola/DaftarLangkahBerikutnya';
import RingkasTindakan from '@/Komponen/Kelola/RingkasTindakan';
import DasborPemilik from '@/Komponen/Laporan/DasborPemilik';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia } from '@/Komponen/Ui/empty';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { DasborPemilik as DataDasbor } from '@/Tipe/Laporan';
import type { PropsBerandaKelola } from '@/Tipe/PanduanAwal';
import type { ButirTindakan } from '@/Tipe/Tindakan';

type PropsBeranda = PropsBerandaKelola & { Dasbor?: DataDasbor | null; Tindakan?: ButirTindakan[] };

/**
 * Beranda back-office: checklist "Langkah berikutnya" (F-01 langkah 7) dan dasbor pemilik (F-14a) untuk pemegang
 * izin laporan penjualan. Tanpa izin: beranda tanpa angka. D-23 C: kartu "Perlu tindakan" di paling atas.
 */
export default function HalamanBerandaKelola({ LangkahBerikutnya, Dasbor = null, Tindakan = [] }: PropsBeranda) {
    return (
        <TataLetakAplikasi judul="Beranda">
            <RingkasTindakan butir={Tindakan} />
            <DaftarLangkahBerikutnya daftar={LangkahBerikutnya} />
            {Dasbor ? <DasborPemilik data={Dasbor} /> : null}
            {!Dasbor && LangkahBerikutnya.length === 0 && Tindakan.length === 0 ? (
                <Empty className="rounded-panel border border-garis bg-permukaan px-4 py-6 md:p-8">
                    <EmptyHeader>
                        <EmptyMedia variant="icon" aria-hidden="true">
                            <ChartNoAxesColumnIcon />
                        </EmptyMedia>
                        <EmptyDescription className="text-isi text-teks-sekunder">
                            Selamat bekerja. Ringkasan penjualan hanya tampil untuk pengguna yang punya izin melihat
                            laporan penjualan.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : null}
        </TataLetakAplikasi>
    );
}
