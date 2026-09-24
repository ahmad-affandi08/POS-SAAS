import { router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PanelKatalog from '@/Komponen/Katalog/PanelKatalog';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { PropsPengaturanKasir } from '@/Tipe/Kasir';

const alamat = '/kelola/kasir/pengaturan';

/** Kelipatan pembulatan tunai yang umum di Indonesia (Rupiah). */
const kelipatanUmum = ['50', '100', '200', '500', '1000'];

/** "200000.00" → "200000" untuk BidangUang (tanpa float, hanya memotong sen nol). */
export function UbahKeMasukanUang(nilai: string): string {
    return nilai.replace(/\.00$/, '');
}

/** Persen dari server ("10.00", "25.50") → teks isian ("10", "25.5"); tanpa konversi ke number. */
export function UbahKeMasukanPersen(nilai: string): string {
    return nilai.includes('.') ? nilai.replace(/0+$/, '').replace(/\.$/, '') : nilai;
}

/** Isian persen: hanya angka dan satu titik/koma desimal (koma diubah ke titik), maks. 2 desimal. */
export function NormalisasiMasukanPersen(teks: string): string {
    const bersih = teks.replace(',', '.').replace(/[^\d.]/g, '');
    const [bulat = '', ...sisa] = bersih.split('.');

    return sisa.length === 0 ? bulat.slice(0, 3) : `${bulat.slice(0, 3)}.${sisa.join('').slice(0, 2)}`;
}

/**
 * Pengaturan kasir tenant: batas kas keluar tanpa persetujuan (BR-06.4), shift bersama (BR-06.2), batas diskon manual
 * kasir & penyetuju (BR-07.3), dan pembulatan tunai (BR-08.6). Berlaku di aplikasi kasir setelah data perangkat
 * diperbarui.
 */
export default function HalamanPengaturanKasir({
    BatasKasKeluar,
    ShiftBersama,
    BatasDiskonManual,
    BatasDiskonPenyetuju,
    PembulatanTunai,
    OpsiArahPembulatan,
}: PropsPengaturanKasir) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const galat = props.errors;
    const awal = {
        batas: UbahKeMasukanUang(BatasKasKeluar),
        bersama: ShiftBersama,
        diskonKasir: UbahKeMasukanPersen(BatasDiskonManual),
        diskonPenyetuju: UbahKeMasukanPersen(BatasDiskonPenyetuju),
        bulatkan: PembulatanTunai !== null,
        kelipatan: String(PembulatanTunai?.Kelipatan ?? 100),
        arah: PembulatanTunai?.Arah ?? 'Bawah',
    };
    const [batas, AturBatas] = useState(awal.batas);
    const [bersama, AturBersama] = useState(awal.bersama);
    const [diskonKasir, AturDiskonKasir] = useState(awal.diskonKasir);
    const [diskonPenyetuju, AturDiskonPenyetuju] = useState(awal.diskonPenyetuju);
    const [bulatkan, AturBulatkan] = useState(awal.bulatkan);
    const [kelipatan, AturKelipatan] = useState(awal.kelipatan);
    const [arah, AturArah] = useState(awal.arah);
    const [memproses, AturMemproses] = useState(false);
    const berubah =
        batas !== awal.batas ||
        bersama !== awal.bersama ||
        diskonKasir !== awal.diskonKasir ||
        diskonPenyetuju !== awal.diskonPenyetuju ||
        bulatkan !== awal.bulatkan ||
        (bulatkan && (kelipatan !== awal.kelipatan || arah !== awal.arah));
    const opsiKelipatan = (kelipatanUmum.includes(kelipatan) ? kelipatanUmum : [...kelipatanUmum, kelipatan]).map(
        (nilai) => ({ Nilai: nilai, Label: FormatRupiah(nilai) }),
    );

    const Simpan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.put(
            alamat,
            {
                BatasKasKeluar: batas === '' ? '0' : batas,
                ShiftBersama: bersama,
                BatasDiskonManual: diskonKasir === '' ? '0' : diskonKasir,
                BatasDiskonPenyetuju: diskonPenyetuju === '' ? '0' : diskonPenyetuju,
                PembulatanTunai: bulatkan ? { Kelipatan: Number.parseInt(kelipatan, 10), Arah: arah } : null,
            },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <TataLetakAplikasi judul="Pengaturan kasir">
            <DaftarGalatServer
                galat={galat}
                kecuali={[
                    'BatasKasKeluar',
                    'ShiftBersama',
                    'BatasDiskonManual',
                    'BatasDiskonPenyetuju',
                    'PembulatanTunai.Kelipatan',
                    'PembulatanTunai.Arah',
                ]}
            />
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
                <PanelKatalog
                    judul="Diskon manual"
                    idJudul="judul-diskon-manual"
                    keterangan="Kasir yang punya izin diskon boleh memberi diskon sampai batas kasir. Di atasnya wajib disetujui supervisor dengan PIN, sampai batas persetujuan. Hanya Pemilik yang boleh memberi diskon lebih besar."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <BidangTeks
                            label="Batas diskon kasir (%)"
                            nilai={diskonKasir}
                            saatBerubah={(teks) => AturDiskonKasir(NormalisasiMasukanPersen(teks))}
                            inputMode="decimal"
                            galat={galat.BatasDiskonManual}
                        />
                        <BidangTeks
                            label="Batas diskon dengan persetujuan (%)"
                            nilai={diskonPenyetuju}
                            saatBerubah={(teks) => AturDiskonPenyetuju(NormalisasiMasukanPersen(teks))}
                            inputMode="decimal"
                            galat={galat.BatasDiskonPenyetuju}
                        />
                    </div>
                </PanelKatalog>
                <PanelKatalog
                    judul="Pembulatan tunai"
                    idJudul="judul-pembulatan-tunai"
                    keterangan="Hanya bagian yang dibayar tunai yang dibulatkan. Selisihnya tercatat sebagai pendapatan lain."
                >
                    <KotakCentang label="Bulatkan pembayaran tunai" nilai={bulatkan} saatBerubah={AturBulatkan} />
                    {bulatkan ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <BidangPilihan
                                label="Kelipatan"
                                nilai={kelipatan}
                                opsi={opsiKelipatan}
                                saatBerubah={AturKelipatan}
                                galat={galat['PembulatanTunai.Kelipatan']}
                            />
                            <BidangPilihan
                                label="Arah pembulatan"
                                nilai={arah}
                                opsi={OpsiArahPembulatan}
                                saatBerubah={AturArah}
                                galat={galat['PembulatanTunai.Arah']}
                            />
                        </div>
                    ) : null}
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
