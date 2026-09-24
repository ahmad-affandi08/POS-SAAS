import { router } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type {
    AturanJenisProduk,
    BidangImpor,
    JenisProduk,
    OpsiImpor,
    OpsiKelompokPajak,
    PropsDetailImpor,
} from '@/Tipe/Katalog';

import GrupRadio from './GrupRadio';

type DataPemetaan = NonNullable<PropsDetailImpor['Pemetaan']>;

/** Galat lokal pemetaan: bidang wajib belum dipetakan, satu kolom dipakai dua bidang, harga tanpa izin. */
export function PeriksaPemetaan(
    bidang: DataPemetaan['Bidang'],
    pemetaan: Record<BidangImpor, number | null>,
    bolehUbahHarga: boolean,
): Record<string, string> {
    const galat: Record<string, string> = {};
    const pemakai = new Map<number, string>();

    bidang.forEach((item) => {
        const kolom = pemetaan[item.Kunci] ?? null;

        if (item.Wajib && kolom === null) {
            galat[item.Kunci] = `${item.Label} wajib dipetakan ke satu kolom.`;
        }

        if (kolom !== null && item.Harga && !bolehUbahHarga) {
            galat[item.Kunci] = 'Kolom harga perlu izin produk.harga.ubah. Pilih "Tidak diimpor".';
        }

        if (kolom !== null) {
            const lain = pemakai.get(kolom);

            if (lain !== undefined) {
                galat[item.Kunci] = `Kolom ini sudah dipakai untuk ${lain}.`;
            } else {
                pemakai.set(kolom, item.Label);
            }
        }
    });

    return galat;
}

type PropsPemetaanImpor = {
    uuidImpor: string;
    pemetaan: DataPemetaan;
    kelompokPajak: OpsiKelompokPajak[];
    jenis: AturanJenisProduk[];
    bolehUbahHarga: boolean;
    /** Preset yang nama kolomnya masih asumsi (majoo, Moka, Pawoon): tampilkan peringatan periksa pemetaan. */
    presetAsumsi: boolean;
    galatServer: Record<string, string | undefined>;
    saatBatal?: () => void;
};

/** Langkah 2 impor: cocokkan kolom berkas ke bidang produk, pilih mode & bawaan, lalu validasi (BR-03.6). */
export default function PemetaanImpor({
    uuidImpor,
    pemetaan: data,
    kelompokPajak,
    jenis,
    bolehUbahHarga,
    presetAsumsi,
    galatServer,
    saatBatal,
}: PropsPemetaanImpor) {
    const id = useId();
    const [pemetaan, AturPemetaan] = useState<Record<BidangImpor, number | null>>(data.Pemetaan);
    const [opsi, AturOpsi] = useState<OpsiImpor>(data.Opsi);
    const [periksa, AturPeriksa] = useState(false);
    const [memproses, AturMemproses] = useState(false);
    const galatLokal = periksa ? PeriksaPemetaan(data.Bidang, pemetaan, bolehUbahHarga) : {};
    const opsiKolom = data.KolomSumber.map((kolom) => ({
        Nilai: String(kolom.Indeks),
        Label: kolom.Judul || `Kolom ${String(kolom.Indeks + 1)}`,
    }));
    const jumlahGalat = Object.keys(galatLokal).length;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (Object.keys(PeriksaPemetaan(data.Bidang, pemetaan, bolehUbahHarga)).length > 0) {
            return;
        }

        router.put(
            `/kelola/produk/impor/${uuidImpor}/pemetaan`,
            { Pemetaan: pemetaan, Opsi: opsi },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-labelledby={`${id}-judul`}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
        >
            <h2 id={`${id}-judul`} className="text-subjudul font-semibold text-teks-utama">
                Pemetaan kolom
            </h2>
            {presetAsumsi ? (
                <Pemberitahuan jenis="peringatan" judul="Periksa pemetaan kolom sebelum mengimpor">
                    Nama kolom format aplikasi ini belum diverifikasi dengan berkas ekspor asli. Pastikan setiap bidang
                    menunjuk kolom yang benar; lihat contoh isi di samping pilihan.
                </Pemberitahuan>
            ) : null}
            <div aria-live="polite">
                {jumlahGalat > 0 ? (
                    <p className="rounded-kontrol border border-l-4 border-bahaya bg-permukaan px-3 py-2 text-isi font-semibold text-bahaya">
                        Ada {jumlahGalat} pemetaan yang perlu diperbaiki.
                    </p>
                ) : null}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-left text-isi">
                    <caption className="sr-only">Pemetaan bidang produk ke kolom berkas</caption>
                    <thead className="border-b border-garis text-label text-teks-sekunder">
                        <tr>
                            <th scope="col" className="py-2 pr-2 font-semibold">
                                Bidang produk
                            </th>
                            <th scope="col" className="px-2 py-2 font-semibold">
                                Kolom di berkas
                            </th>
                            <th scope="col" className="py-2 pl-2 font-semibold">
                                Contoh isi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.Bidang.map((bidang) => {
                            const kolom = pemetaan[bidang.Kunci] ?? null;
                            const contoh = data.KolomSumber.find((item) => item.Indeks === kolom)?.Contoh ?? [];

                            return (
                                <tr key={bidang.Kunci} className="border-b border-garis align-top last:border-b-0">
                                    <th scope="row" className="py-2 pr-2 text-left font-normal">
                                        <span className="block font-semibold text-teks-utama">
                                            {bidang.Label}
                                            {bidang.Wajib ? ' (wajib)' : ''}
                                        </span>
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {bidang.Keterangan}
                                        </span>
                                    </th>
                                    <td className="px-2 py-2">
                                        <BidangPilihan
                                            label={`Kolom untuk ${bidang.Label}`}
                                            nilai={kolom === null ? '' : String(kolom)}
                                            kosong="Tidak diimpor"
                                            opsi={opsiKolom}
                                            saatBerubah={(nilai) =>
                                                AturPemetaan({
                                                    ...pemetaan,
                                                    [bidang.Kunci]: nilai === '' ? null : Number.parseInt(nilai, 10),
                                                })
                                            }
                                            galat={galatLokal[bidang.Kunci] ?? galatServer[`Pemetaan.${bidang.Kunci}`]}
                                        />
                                    </td>
                                    <td className="py-2 pl-2 text-keterangan break-all text-teks-sekunder">
                                        {contoh.length === 0 ? '—' : contoh.filter(Boolean).slice(0, 3).join(' · ')}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <fieldset className="grid gap-4 sm:grid-cols-2">
                <legend className="mb-2 text-label font-semibold text-teks-utama">Pengaturan impor</legend>
                <GrupRadio<OpsiImpor['Mode']>
                    legenda="Bila SKU atau nama produk sudah ada"
                    nilai={opsi.Mode}
                    opsi={[
                        {
                            Nilai: 'TambahDanPerbarui',
                            Label: 'Perbarui produk yang sudah ada',
                            Keterangan: 'Aman diimpor ulang: tidak membuat duplikat.',
                        },
                        {
                            Nilai: 'TambahSaja',
                            Label: 'Lewati produk yang sudah ada',
                            Keterangan: 'Hanya produk baru yang ditambahkan.',
                        },
                    ]}
                    saatBerubah={(nilai) => AturOpsi({ ...opsi, Mode: nilai })}
                    galat={galatServer['Opsi.Mode']}
                />
                <div className="flex flex-col gap-3">
                    <BidangPilihan
                        label="Jenis bawaan"
                        nilai={opsi.JenisBawaan}
                        opsi={jenis
                            .filter((item) => item.Nilai !== 'IndukVarian')
                            .map((item) => ({ Nilai: item.Nilai, Label: item.Label }))}
                        saatBerubah={(nilai) => AturOpsi({ ...opsi, JenisBawaan: nilai as JenisProduk })}
                        galat={galatServer['Opsi.JenisBawaan']}
                    />
                    <BidangPilihan
                        label="Kelompok pajak bawaan"
                        nilai={opsi.UuidKelompokPajakBawaan ?? ''}
                        kosong="Tidak ada (baris tanpa pajak ditolak)"
                        opsi={kelompokPajak.map((item) => ({
                            Nilai: item.Uuid,
                            Label: `${item.Nama} · ${item.LabelKategori}`,
                        }))}
                        saatBerubah={(nilai) =>
                            AturOpsi({ ...opsi, UuidKelompokPajakBawaan: nilai === '' ? null : nilai })
                        }
                        galat={galatServer['Opsi.UuidKelompokPajakBawaan']}
                    />
                    <KotakCentang
                        label="Buat kategori baru bila belum ada"
                        nilai={opsi.BuatKategoriBaru}
                        saatBerubah={(nilai) => AturOpsi({ ...opsi, BuatKategoriBaru: nilai })}
                    />
                    <KotakCentang
                        label="Buat satuan baru bila belum ada"
                        nilai={opsi.BuatSatuanBaru}
                        saatBerubah={(nilai) => AturOpsi({ ...opsi, BuatSatuanBaru: nilai })}
                    />
                </div>
            </fieldset>
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={memproses}>
                    Periksa data
                </Tombol>
                {saatBatal ? (
                    <Tombol varian="sekunder" onClick={saatBatal}>
                        Batal ubah pemetaan
                    </Tombol>
                ) : null}
            </div>
        </form>
    );
}
