import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangDaftarTeks from '@/Komponen/Formulir/BidangDaftarTeks';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalat from '@/Komponen/Pengelola/TemplateSektor/RingkasanGalat';
import { FormatRupiah } from '@/Pustaka/Format';
import type { Pilihan } from '@/Tipe/Pengelola';
import type {
    IsiBisnisTemplate,
    JenisProdukContoh,
    PilihanEditorTemplate,
    ProdukContohTemplate,
} from '@/Tipe/TemplateSektor';

type PropsFormIsiBisnis = {
    url: string;
    isi: IsiBisnisTemplate;
    pilihan: PilihanEditorTemplate;
    bolehUbah: boolean;
};

const bagianDaftar = ['Kategori', 'StasiunDapur', 'AlasanVoid', 'AlasanPenyesuaian'] as const;

const jumlahProdukContohMaksimal = 100;
const polaHarga = /^\d{1,16}(\.\d{1,2})?$/;
const kelasSel = 'px-2 py-2 align-top';
const kelasInput =
    'h-10 w-full rounded-kontrol border border-garis-input bg-permukaan px-2 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:bg-latar disabled:text-teks-sekunder';

function KeOpsi(daftar: Pilihan[]) {
    return daftar.map((item) => ({ nilai: item.Nilai, label: item.Label }));
}

/** Usaha retail/grosir menjual barang berstok; F&B umumnya menu tanpa stok (DesainF01 H14). */
function TentukanJenisAwal(modeKasir: string[]): JenisProdukContoh {
    return modeKasir.includes('Retail') || modeKasir.includes('Grosir') ? 'Stok' : 'NonStok';
}

/**
 * Isi bisnis template: mode kasir, fitur, kategori, satuan, pengaturan default, dan produk contoh
 * (BR-P03.5, Konten & Legal).
 */
export default function FormIsiBisnis({ url, isi, pilihan, bolehUbah }: PropsFormIsiBisnis) {
    const formulir = useForm<IsiBisnisTemplate>(isi);
    const galat = formulir.errors as Record<string, string | undefined>;
    const data = formulir.data;
    const AturPengaturan = <K extends keyof IsiBisnisTemplate['Pengaturan']>(
        kunci: K,
        nilai: IsiBisnisTemplate['Pengaturan'][K],
    ) => formulir.setData('Pengaturan', { ...data.Pengaturan, [kunci]: nilai });

    const kategoriTemplate = data.Kategori.map((nama) => nama.trim()).filter((nama) => nama !== '');
    const satuanTemplate = pilihan.Satuan.filter((satuan) => data.KodeSatuan.includes(satuan.Nilai));
    const UbahProdukContoh = (indeks: number, perubahan: Partial<ProdukContohTemplate>) =>
        formulir.setData(
            'ProdukContoh',
            data.ProdukContoh.map((produk, posisi) => (posisi === indeks ? { ...produk, ...perubahan } : produk)),
        );
    const TambahProdukContoh = () =>
        formulir.setData('ProdukContoh', [
            ...data.ProdukContoh,
            {
                Nama: '',
                Kategori: null,
                Harga: '',
                KodeSatuan: data.KodeSatuan[0] ?? '',
                Jenis: TentukanJenisAwal(data.ModeKasir),
            },
        ]);
    const HapusProdukContoh = (indeks: number) =>
        formulir.setData(
            'ProdukContoh',
            data.ProdukContoh.filter((_, posisi) => posisi !== indeks),
        );

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((isian) => {
            const bersih = { ...isian };

            for (const bagian of bagianDaftar) {
                bersih[bagian] = isian[bagian].map((nama) => nama.trim()).filter((nama) => nama !== '');
            }

            bersih.ProdukContoh = isian.ProdukContoh.map((produk) => ({
                ...produk,
                Nama: produk.Nama.trim(),
                Kategori: produk.Kategori === null || produk.Kategori.trim() === '' ? null : produk.Kategori,
                Harga: produk.Harga.trim(),
            }));

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
                        label="Kelipatan pembulatan tunai (Rp)"
                        inputMode="numeric"
                        nilai={String(data.Pengaturan.PembulatanTunai.Kelipatan)}
                        saatBerubah={(nilai) =>
                            AturPengaturan('PembulatanTunai', {
                                ...data.Pengaturan.PembulatanTunai,
                                Kelipatan: Number(nilai.replace(/\D/g, '')),
                            })
                        }
                        galat={galat['Pengaturan.PembulatanTunai.Kelipatan']}
                    />
                    <BidangPilihan
                        label="Arah pembulatan tunai"
                        nilai={data.Pengaturan.PembulatanTunai.Arah}
                        opsi={pilihan.ArahPembulatan}
                        saatBerubah={(nilai) =>
                            AturPengaturan('PembulatanTunai', { ...data.Pengaturan.PembulatanTunai, Arah: nilai })
                        }
                        galat={galat['Pengaturan.PembulatanTunai.Arah']}
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
                <section className="flex flex-col gap-2 sm:col-span-2" aria-labelledby="judul-produk-contoh">
                    <div>
                        <h3 id="judul-produk-contoh" className="text-label font-semibold text-teks-utama">
                            Produk contoh
                        </h3>
                        <p className="text-keterangan text-teks-sekunder">
                            Ditawarkan ke tenant di langkah produk awal panduan. Kategori dan satuan diambil dari isian
                            di atas. Harga dalam Rupiah tanpa titik ribuan, misal 22000.
                        </p>
                    </div>
                    {data.ProdukContoh.length === 0 ? (
                        <p className="text-keterangan text-teks-sekunder">
                            Belum ada produk contoh. Tenant yang memakai template ini menambah produknya sendiri.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[48rem] text-left text-isi">
                                <caption className="sr-only">Produk contoh template</caption>
                                <thead className="border-b border-garis text-label text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className={`${kelasSel} font-semibold`}>
                                            Nama produk
                                        </th>
                                        <th scope="col" className={`${kelasSel} w-44 font-semibold`}>
                                            Kategori
                                        </th>
                                        <th scope="col" className={`${kelasSel} w-40 text-right font-semibold`}>
                                            Harga (Rp)
                                        </th>
                                        <th scope="col" className={`${kelasSel} w-36 font-semibold`}>
                                            Satuan
                                        </th>
                                        <th scope="col" className={`${kelasSel} w-44 font-semibold`}>
                                            Jenis
                                        </th>
                                        <th scope="col" className={kelasSel}>
                                            <span className="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.ProdukContoh.map((produk, indeks) => {
                                        const awalan = `ProdukContoh.${indeks}`;
                                        const hargaValid = polaHarga.test(produk.Harga.trim());
                                        const kategoriAsing =
                                            produk.Kategori !== null && !kategoriTemplate.includes(produk.Kategori);
                                        const satuanAsing =
                                            produk.KodeSatuan !== '' &&
                                            !satuanTemplate.some((satuan) => satuan.Nilai === produk.KodeSatuan);

                                        return (
                                            <tr key={indeks} className="border-b border-garis last:border-b-0">
                                                <td className={kelasSel}>
                                                    <input
                                                        aria-label={`Nama produk contoh baris ${indeks + 1}`}
                                                        aria-invalid={galat[`${awalan}.Nama`] ? true : undefined}
                                                        className={kelasInput}
                                                        maxLength={150}
                                                        value={produk.Nama}
                                                        onChange={(peristiwa) =>
                                                            UbahProdukContoh(indeks, { Nama: peristiwa.target.value })
                                                        }
                                                    />
                                                </td>
                                                <td className={kelasSel}>
                                                    <select
                                                        aria-label={`Kategori produk contoh baris ${indeks + 1}`}
                                                        aria-invalid={galat[`${awalan}.Kategori`] ? true : undefined}
                                                        className={kelasInput}
                                                        value={produk.Kategori ?? ''}
                                                        onChange={(peristiwa) =>
                                                            UbahProdukContoh(indeks, {
                                                                Kategori:
                                                                    peristiwa.target.value === ''
                                                                        ? null
                                                                        : peristiwa.target.value,
                                                            })
                                                        }
                                                    >
                                                        <option value="">Tanpa kategori</option>
                                                        {kategoriAsing ? (
                                                            <option value={produk.Kategori ?? ''}>
                                                                {produk.Kategori} (tidak ada di daftar)
                                                            </option>
                                                        ) : null}
                                                        {kategoriTemplate.map((nama) => (
                                                            <option key={nama} value={nama}>
                                                                {nama}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className={kelasSel}>
                                                    <input
                                                        aria-label={`Harga produk contoh baris ${indeks + 1}`}
                                                        aria-invalid={galat[`${awalan}.Harga`] ? true : undefined}
                                                        className={`${kelasInput} text-right tabular-nums`}
                                                        inputMode="decimal"
                                                        value={produk.Harga}
                                                        onChange={(peristiwa) =>
                                                            UbahProdukContoh(indeks, { Harga: peristiwa.target.value })
                                                        }
                                                    />
                                                    {hargaValid ? (
                                                        <span className="mt-1 block text-right text-keterangan text-teks-sekunder tabular-nums">
                                                            {FormatRupiah(produk.Harga)}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className={kelasSel}>
                                                    <select
                                                        aria-label={`Satuan produk contoh baris ${indeks + 1}`}
                                                        aria-invalid={galat[`${awalan}.KodeSatuan`] ? true : undefined}
                                                        className={kelasInput}
                                                        value={produk.KodeSatuan}
                                                        onChange={(peristiwa) =>
                                                            UbahProdukContoh(indeks, {
                                                                KodeSatuan: peristiwa.target.value,
                                                            })
                                                        }
                                                    >
                                                        <option value="">Pilih satuan</option>
                                                        {satuanAsing ? (
                                                            <option value={produk.KodeSatuan}>
                                                                {produk.KodeSatuan} (tidak ada di daftar)
                                                            </option>
                                                        ) : null}
                                                        {satuanTemplate.map((satuan) => (
                                                            <option key={satuan.Nilai} value={satuan.Nilai}>
                                                                {satuan.Label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className={kelasSel}>
                                                    <select
                                                        aria-label={`Jenis produk contoh baris ${indeks + 1}`}
                                                        aria-invalid={galat[`${awalan}.Jenis`] ? true : undefined}
                                                        className={kelasInput}
                                                        value={produk.Jenis}
                                                        onChange={(peristiwa) =>
                                                            UbahProdukContoh(indeks, {
                                                                Jenis: peristiwa.target.value as JenisProdukContoh,
                                                            })
                                                        }
                                                    >
                                                        {pilihan.JenisProdukContoh.map((jenis) => (
                                                            <option key={jenis.Nilai} value={jenis.Nilai}>
                                                                {jenis.Label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className={`${kelasSel} text-right`}>
                                                    {bolehUbah ? (
                                                        <Tombol
                                                            varian="bahaya"
                                                            onClick={() => HapusProdukContoh(indeks)}
                                                        >
                                                            Hapus
                                                        </Tombol>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {bolehUbah ? (
                        <div className="flex flex-wrap items-center gap-3">
                            <Tombol
                                varian="sekunder"
                                onClick={TambahProdukContoh}
                                disabled={data.ProdukContoh.length >= jumlahProdukContohMaksimal}
                            >
                                Tambah produk contoh
                            </Tombol>
                            <span className="text-keterangan text-teks-sekunder tabular-nums">
                                {data.ProdukContoh.length} dari {jumlahProdukContohMaksimal} produk
                            </span>
                        </div>
                    ) : null}
                </section>
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
