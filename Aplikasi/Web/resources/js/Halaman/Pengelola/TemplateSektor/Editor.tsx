import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import FormAkun from '@/Komponen/Pengelola/TemplateSektor/FormAkun';
import FormIsiBisnis from '@/Komponen/Pengelola/TemplateSektor/FormIsiBisnis';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';
import type { GalatValidasi, IsiTemplate, PilihanEditorTemplate } from '@/Tipe/TemplateSektor';

type StatusVersi = 'Draf' | 'Terbit' | 'Usang';

type PropsEditor = {
    Template: { Kode: string; Nama: string; Keterangan: string | null };
    Versi: {
        Versi: number;
        Status: StatusVersi;
        Isi: IsiTemplate;
        HasilValidasi: { Lolos: boolean; Galat: GalatValidasi[] } | null;
        DivalidasiPada: string | null;
        DiterbitkanPada: string | null;
        VersiAsal: number | null;
    };
    DaftarVersi: { Versi: number; Status: StatusVersi; DiterbitkanPada: string | null }[];
    AdaDraf: boolean;
    Pilihan: PilihanEditorTemplate;
};

const jenisStatus = { Draf: 'peringatan', Terbit: 'sukses', Usang: 'netral' } as const;

const labelBagian: Record<string, string> = {
    ModeKasir: 'Mode kasir',
    KunciFitur: 'Fitur',
    Akun: 'Bagan akun',
    PemetaanAkun: 'Pemetaan akun',
    KodeSatuan: 'Satuan',
    KelompokPajak: 'Kelompok pajak',
    Pengaturan: 'Pengaturan',
    Kategori: 'Kategori',
    StasiunDapur: 'Stasiun dapur',
    AlasanVoid: 'Alasan void',
    AlasanPenyesuaian: 'Alasan penyesuaian',
    LaporanUnggulan: 'Laporan unggulan',
};

/** Editor terstruktur satu versi template sektor (P-03 langkah 2–5). */
export default function HalamanEditorTemplate({ Template, Versi, DaftarVersi, AdaDraf, Pilihan }: PropsEditor) {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const draf = Versi.Status === 'Draf';
    const url = `/template-sektor/${encodeURIComponent(Template.Kode)}/versi/${Versi.Versi}`;
    const bolehKelolaDraf = PunyaIzin(pengguna, IzinPengelola.TemplateDrafKelola);
    const [memproses, AturMemproses] = useState(false);
    const opsiKirim = {
        preserveScroll: true,
        onStart: () => AturMemproses(true),
        onFinish: () => AturMemproses(false),
    };

    const Terbitkan = () => {
        if (window.confirm(`Terbitkan ${Template.Kode} versi ${Versi.Versi}? Versi terbit tidak bisa diubah lagi.`)) {
            router.post(`${url}/terbitkan`, {}, opsiKirim);
        }
    };
    const HapusDraf = () => {
        if (window.confirm(`Hapus draf versi ${Versi.Versi}? Perubahan di draf ini hilang.`)) {
            router.delete(url, opsiKirim);
        }
    };

    return (
        <TataLetakPengelola
            judul={`${Template.Nama} · ${Template.Kode}`}
            aksi={
                <div className="flex flex-wrap gap-2">
                    {draf && bolehKelolaDraf ? (
                        <Tombol
                            varian="sekunder"
                            memproses={memproses}
                            onClick={() => router.post(`${url}/validasi`, {}, opsiKirim)}
                        >
                            Validasi ulang
                        </Tombol>
                    ) : null}
                    {draf && PunyaIzin(pengguna, IzinPengelola.TemplateTerbitkan) ? (
                        <Tombol memproses={memproses} onClick={Terbitkan}>
                            Terbitkan
                        </Tombol>
                    ) : null}
                    {!draf && !AdaDraf && bolehKelolaDraf ? (
                        <Tombol memproses={memproses} onClick={() => router.post(`${url}/duplikasi`, {}, opsiKirim)}>
                            Buat draf versi baru
                        </Tombol>
                    ) : null}
                    {draf && bolehKelolaDraf ? (
                        <Tombol varian="bahaya" memproses={memproses} onClick={HapusDraf}>
                            Hapus draf
                        </Tombol>
                    ) : null}
                </div>
            }
        >
            <nav aria-label="Versi template" className="flex flex-wrap items-center gap-2">
                <Link href="/template-sektor" className="text-label font-semibold text-brand underline">
                    Semua template
                </Link>
                <span aria-hidden className="text-teks-sekunder">
                    ·
                </span>
                {DaftarVersi.map((baris) => (
                    <Link
                        key={baris.Versi}
                        href={`/template-sektor/${encodeURIComponent(Template.Kode)}/versi/${baris.Versi}`}
                        aria-current={baris.Versi === Versi.Versi ? 'page' : undefined}
                        className={`rounded-kontrol border px-3 py-1 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                            baris.Versi === Versi.Versi
                                ? 'border-brand text-teks-utama'
                                : 'border-garis text-teks-sekunder'
                        }`}
                    >
                        Versi {baris.Versi} · {baris.Status}
                    </Link>
                ))}
            </nav>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            <section className="flex flex-wrap items-center gap-3 text-keterangan text-teks-sekunder">
                <LabelStatus jenis={jenisStatus[Versi.Status]} teks={Versi.Status} />
                {Versi.VersiAsal ? <span>Disalin dari versi {Versi.VersiAsal}</span> : null}
                {Versi.DiterbitkanPada ? <span>Terbit {FormatTanggalWaktu(Versi.DiterbitkanPada)}</span> : null}
            </section>
            {draf ? (
                <HasilValidasi hasil={Versi.HasilValidasi} divalidasiPada={Versi.DivalidasiPada} />
            ) : (
                <Pemberitahuan jenis="info">
                    Versi {Versi.Status === 'Terbit' ? 'terbit' : 'usang'} tidak bisa diubah. Buat draf versi baru untuk
                    memperbaikinya.
                </Pemberitahuan>
            )}
            <FormIsiBisnis
                key={`bisnis-${Versi.Versi}`}
                url={url}
                isi={Versi.Isi}
                pilihan={Pilihan}
                bolehUbah={draf && PunyaIzin(pengguna, IzinPengelola.TemplateIsiUbah)}
            />
            <FormAkun
                key={`akun-${Versi.Versi}`}
                url={url}
                isi={{
                    Akun: Versi.Isi.Akun,
                    PemetaanAkun: Array.isArray(Versi.Isi.PemetaanAkun) ? {} : Versi.Isi.PemetaanAkun,
                    KelompokPajak: Versi.Isi.KelompokPajak,
                }}
                pilihan={Pilihan}
                bolehUbah={draf && PunyaIzin(pengguna, IzinPengelola.TemplateAkunUbah)}
            />
        </TataLetakPengelola>
    );
}

type PropsHasilValidasi = {
    hasil: { Lolos: boolean; Galat: GalatValidasi[] } | null;
    divalidasiPada: string | null;
};

function HasilValidasi({ hasil, divalidasiPada }: PropsHasilValidasi) {
    if (hasil === null) {
        return (
            <Pemberitahuan jenis="peringatan" judul="Belum divalidasi">
                Simpan salah satu bagian atau jalankan validasi ulang untuk memeriksa template.
            </Pemberitahuan>
        );
    }

    const waktu = divalidasiPada ? ` (diperiksa ${FormatTanggalWaktu(divalidasiPada)})` : '';

    if (hasil.Lolos) {
        return (
            <Pemberitahuan jenis="sukses" judul={`Lolos validasi${waktu}`}>
                COA, pemetaan akun, pajak, fitur, dan satuan sudah konsisten. Template siap diterbitkan.
            </Pemberitahuan>
        );
    }

    return (
        <Pemberitahuan jenis="bahaya" judul={`${hasil.Galat.length} masalah validasi${waktu}`}>
            <p>Template tidak bisa terbit sampai semua masalah diperbaiki.</p>
            <ul className="mt-2 list-disc pl-5">
                {hasil.Galat.map((galat, indeks) => (
                    <li key={indeks}>
                        <span className="font-semibold">{labelBagian[galat.Bagian] ?? galat.Bagian}:</span>{' '}
                        {galat.Pesan}
                    </li>
                ))}
            </ul>
        </Pemberitahuan>
    );
}
