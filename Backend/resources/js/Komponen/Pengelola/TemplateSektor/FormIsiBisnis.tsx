import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangDaftarTeks from '@/Komponen/Formulir/BidangDaftarTeks';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalat from '@/Komponen/Pengelola/TemplateSektor/RingkasanGalat';
import type { Pilihan } from '@/Tipe/Pengelola';
import type { IsiBisnisTemplate, PilihanEditorTemplate } from '@/Tipe/TemplateSektor';

type PropsFormIsiBisnis = {
    url: string;
    isi: IsiBisnisTemplate;
    pilihan: PilihanEditorTemplate;
    bolehUbah: boolean;
};

const bagianDaftar = ['Kategori', 'StasiunDapur', 'AlasanVoid', 'AlasanPenyesuaian'] as const;

function KeOpsi(daftar: Pilihan[]) {
    return daftar.map((item) => ({ nilai: item.Nilai, label: item.Label }));
}

/** Isi bisnis template: mode kasir, fitur, kategori, satuan, pengaturan default (BR-P03.5, Konten & Legal). */
export default function FormIsiBisnis({ url, isi, pilihan, bolehUbah }: PropsFormIsiBisnis) {
    const formulir = useForm<IsiBisnisTemplate>(isi);
    const galat = formulir.errors as Record<string, string | undefined>;
    const data = formulir.data;
    const AturPengaturan = <K extends keyof IsiBisnisTemplate['Pengaturan']>(
        kunci: K,
        nilai: IsiBisnisTemplate['Pengaturan'][K],
    ) => formulir.setData('Pengaturan', { ...data.Pengaturan, [kunci]: nilai });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((isian) => {
            const bersih = { ...isian };

            for (const bagian of bagianDaftar) {
                bersih[bagian] = isian[bagian].map((nama) => nama.trim()).filter((nama) => nama !== '');
            }

            return bersih;
        });
        formulir.put(`${url}/isi-bisnis`, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6"
            noValidate
        >
            <div>
                <h2 className="text-subjudul font-semibold text-teks-utama">Isi bisnis</h2>
                <p className="text-keterangan text-teks-sekunder">Diubah oleh Konten & Legal.</p>
            </div>
            <RingkasanGalat galat={galat} />
            <fieldset disabled={!bolehUbah} className="grid gap-6 disabled:opacity-90 sm:grid-cols-2">
                <GrupCentang
                    legenda="Mode kasir"
                    opsi={KeOpsi(pilihan.ModeKasir)}
                    terpilih={data.ModeKasir}
                    saatBerubah={(terpilih) => formulir.setData('ModeKasir', terpilih)}
                    galat={galat.ModeKasir}
                />
                <BidangPilihan
                    label="Mode kasir default"
                    nilai={data.ModeKasirDefault ?? ''}
                    kosong="Pilih mode"
                    opsi={pilihan.ModeKasir.filter((mode) => data.ModeKasir.includes(mode.Nilai))}
                    saatBerubah={(nilai) => formulir.setData('ModeKasirDefault', nilai === '' ? null : nilai)}
                    galat={galat.ModeKasirDefault}
                />
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Fitur aktif"
                        opsi={pilihan.Fitur.map((fitur) => ({
                            nilai: fitur.Nilai,
                            label: `${fitur.Label} · ${fitur.Kelompok}`,
                        }))}
                        terpilih={data.KunciFitur}
                        saatBerubah={(terpilih) => formulir.setData('KunciFitur', terpilih)}
                        galat={galat.KunciFitur}
                    />
                </div>
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Satuan default"
                        opsi={KeOpsi(pilihan.Satuan)}
                        terpilih={data.KodeSatuan}
                        saatBerubah={(terpilih) => formulir.setData('KodeSatuan', terpilih)}
                        galat={galat.KodeSatuan}
                    />
                </div>
                <BidangDaftarTeks
                    label="Kategori contoh"
                    nilai={data.Kategori}
                    saatBerubah={(nilai) => formulir.setData('Kategori', nilai)}
                    galat={galat.Kategori}
                />
                <BidangDaftarTeks
                    label="Stasiun dapur"
                    keterangan="Satu per baris. Kosongkan untuk usaha non-F&B."
                    nilai={data.StasiunDapur}
                    saatBerubah={(nilai) => formulir.setData('StasiunDapur', nilai)}
                    galat={galat.StasiunDapur}
                />
                <BidangDaftarTeks
                    label="Alasan void"
                    nilai={data.AlasanVoid}
                    saatBerubah={(nilai) => formulir.setData('AlasanVoid', nilai)}
                    galat={galat.AlasanVoid}
                />
                <BidangDaftarTeks
                    label="Alasan penyesuaian stok"
                    nilai={data.AlasanPenyesuaian}
                    saatBerubah={(nilai) => formulir.setData('AlasanPenyesuaian', nilai)}
                    galat={galat.AlasanPenyesuaian}
                />
                <div className="sm:col-span-2">
                    <GrupCentang
                        legenda="Laporan unggulan di dasbor"
                        opsi={KeOpsi(pilihan.LaporanUnggulan)}
                        terpilih={data.LaporanUnggulan}
                        saatBerubah={(terpilih) => formulir.setData('LaporanUnggulan', terpilih)}
                        galat={galat.LaporanUnggulan}
                    />
                </div>
                <fieldset className="grid gap-4 sm:col-span-2 sm:grid-cols-3">
                    <legend className="mb-2 text-label font-semibold text-teks-utama">Pengaturan default</legend>
                    <BidangTeks
                        label="Kelipatan pembulatan (Rp)"
                        inputMode="numeric"
                        nilai={data.Pengaturan.KelipatanPembulatan}
                        saatBerubah={(nilai) => AturPengaturan('KelipatanPembulatan', nilai)}
                        galat={galat['Pengaturan.KelipatanPembulatan']}
                    />
                    <BidangPilihan
                        label="Arah pembulatan"
                        nilai={data.Pengaturan.ArahPembulatan}
                        opsi={pilihan.ArahPembulatan}
                        saatBerubah={(nilai) => AturPengaturan('ArahPembulatan', nilai)}
                        galat={galat['Pengaturan.ArahPembulatan']}
                    />
                    <BidangPilihan
                        label="Metode HPP"
                        nilai={data.Pengaturan.MetodeHpp}
                        opsi={pilihan.MetodeHpp}
                        saatBerubah={(nilai) => AturPengaturan('MetodeHpp', nilai)}
                        galat={galat['Pengaturan.MetodeHpp']}
                    />
                    <BidangTeks
                        label="Service charge (%)"
                        inputMode="decimal"
                        keterangan="0 sampai 10."
                        nilai={data.Pengaturan.PersenBiayaLayanan}
                        saatBerubah={(nilai) => AturPengaturan('PersenBiayaLayanan', nilai)}
                        galat={galat['Pengaturan.PersenBiayaLayanan']}
                    />
                    <div className="flex flex-col gap-2 sm:col-span-2">
                        <KotakCentang
                            label="Service charge masuk DPP pajak"
                            nilai={data.Pengaturan.BiayaLayananMasukDpp}
                            saatBerubah={(nilai) => AturPengaturan('BiayaLayananMasukDpp', nilai)}
                        />
                        <KotakCentang
                            label="Stok boleh minus"
                            nilai={data.Pengaturan.StokBolehMinus}
                            saatBerubah={(nilai) => AturPengaturan('StokBolehMinus', nilai)}
                        />
                        <KotakCentang
                            label="Harga jual sudah termasuk pajak"
                            nilai={data.Pengaturan.HargaTermasukPajak}
                            saatBerubah={(nilai) => AturPengaturan('HargaTermasukPajak', nilai)}
                        />
                    </div>
                </fieldset>
            </fieldset>
            {bolehUbah ? (
                <div>
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan isi bisnis
                    </Tombol>
                </div>
            ) : null}
        </form>
    );
}
