import { router } from '@inertiajs/react';
import { useState } from 'react';

import Panel from '@/Komponen/Kelola/Panel';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import type { BarisKetersediaanOutlet } from '@/Tipe/Katalog';

type PropsPanelKetersediaan = {
    uuidProduk: string;
    baris: BarisKetersediaanOutlet[];
    bolehUbah: boolean;
};

/**
 * F-17 BR-17.2: tandai produk habis ("86") atau tersedia lagi per outlet. Habis = hilang dari menu self-order QR
 * meja dan toko online outlet itu; pesanan yang sudah masuk tidak berubah dan stok tidak disentuh.
 */
export default function PanelKetersediaan({ uuidProduk, baris, bolehUbah }: PropsPanelKetersediaan) {
    const [memproses, AturMemproses] = useState<string | null>(null);

    const Ubah = (item: BarisKetersediaanOutlet) =>
        router.post(
            `/kelola/produk/${uuidProduk}/habis`,
            { UuidOutlet: item.UuidOutlet, Habis: !item.Habis },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(item.UuidOutlet),
                onFinish: () => AturMemproses(null),
            },
        );

    return (
        <Panel
            judul="Ketersediaan per outlet"
            idJudul="judul-ketersediaan"
            keterangan="Tandai habis bila bahan atau barangnya kosong hari ini. Produk hilang dari menu self-order dan toko online outlet itu; stok tidak berubah."
        >
            {baris.length === 0 ? (
                <p className="text-isi text-teks-sekunder">Belum ada outlet aktif.</p>
            ) : (
                <ul className="flex flex-col divide-y divide-garis">
                    {baris.map((item) => (
                        <li key={item.UuidOutlet} className="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span className="flex flex-wrap items-center gap-2">
                                <span className="font-semibold text-teks-utama">{item.NamaOutlet}</span>
                                <LabelStatus
                                    jenis={item.Habis ? 'peringatan' : 'sukses'}
                                    teks={item.Habis ? 'Habis' : 'Tersedia'}
                                />
                            </span>
                            {bolehUbah ? (
                                <Tombol
                                    varian="sekunder"
                                    memproses={memproses === item.UuidOutlet}
                                    disabled={memproses !== null}
                                    onClick={() => Ubah(item)}
                                    aria-label={`${item.Habis ? 'Tersedia lagi' : 'Tandai habis'} di ${item.NamaOutlet}`}
                                >
                                    {item.Habis ? 'Tersedia lagi' : 'Tandai habis'}
                                </Tombol>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
