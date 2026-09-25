import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangUang from '@/Komponen/Formulir/BidangUang';
import Tombol from '@/Komponen/Formulir/Tombol';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import { AlamatPembelian } from '@/Komponen/Pembelian/BagianDokumenPembelian';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsPengaturanPembelian } from '@/Tipe/Pembelian';

/** Nilai desimal tampilan tanpa ekor nol (`5000000.00` → `5000000`, `2.50` → `2.5`). */
function RapikanDesimal(nilai: string): string {
    return nilai.includes('.') ? nilai.replace(/\.?0+$/, '') : nilai;
}

/**
 * F-04 fase 1: pengaturan pembelian tenant: batas total PO yang butuh persetujuan (§19.2, bawaan Rp 5.000.000) dan
 * toleransi penerimaan melebihi jumlah PO (BR-04.1, bawaan 0%). Izin `pembelian.po.setujui`.
 */
export default function HalamanPengaturanPembelian({ Pengaturan }: PropsPengaturanPembelian) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const batasAwal = RapikanDesimal(Pengaturan.BatasPersetujuanPo);
    const toleransiAwal = RapikanDesimal(Pengaturan.ToleransiPenerimaanPersen);
    const [batas, AturBatas] = useState(batasAwal);
    const [toleransi, AturToleransi] = useState(toleransiAwal);
    const [memproses, AturMemproses] = useState(false);
    const berubah = batas !== batasAwal || toleransi !== toleransiAwal;

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.put(
            `${AlamatPembelian}/pengaturan`,
            {
                BatasPersetujuanPo: batas === '' ? '0' : batas,
                ToleransiPenerimaanPersen: toleransi === '' ? '0' : toleransi,
            },
            {
                preserveScroll: true,
                onStart: () => AturMemproses(true),
                onFinish: () => AturMemproses(false),
            },
        );
    };

    return (
        <TataLetakAplikasi judul="Pengaturan pembelian">
            <DaftarGalatServer galat={galat} kecuali={['BatasPersetujuanPo', 'ToleransiPenerimaanPersen']} />
            <form
                onSubmit={Simpan}
                noValidate
                aria-label="Pengaturan pembelian"
                className="flex max-w-2xl flex-col gap-4"
            >
                <PanelKatalog
                    judul="Persetujuan pesanan pembelian"
                    idJudul="judul-batas-po"
                    keterangan="Pesanan dengan total di atas batas ini harus disetujui pengguna lain yang punya izin menyetujui pesanan pembelian."
                >
                    <BidangUang
                        label="Batas total tanpa persetujuan"
                        nilai={batas}
                        saatBerubah={AturBatas}
                        galat={galat.BatasPersetujuanPo}
                        required
                        keterangan="Bawaan Rp 5.000.000. Isi 0 bila semua pesanan harus disetujui."
                    />
                </PanelKatalog>
                <PanelKatalog
                    judul="Toleransi penerimaan barang"
                    idJudul="judul-toleransi"
                    keterangan="Batas barang diterima melebihi jumlah pesanan, dalam persen per baris."
                >
                    <BidangJumlah
                        label="Toleransi lebih dari pesanan"
                        nilai={toleransi}
                        saatBerubah={AturToleransi}
                        desimal={2}
                        digitBulat={3}
                        akhiran="%"
                        galat={galat.ToleransiPenerimaanPersen}
                        required
                        keterangan="Bawaan 0%: penerimaan tidak boleh melebihi jumlah pesanan."
                    />
                </PanelKatalog>
                <div>
                    <Tombol type="submit" memproses={memproses} disabled={!berubah}>
                        Simpan pengaturan
                    </Tombol>
                </div>
            </form>
        </TataLetakAplikasi>
    );
}
