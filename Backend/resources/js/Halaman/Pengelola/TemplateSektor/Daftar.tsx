import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type RingkasanTemplate = {
    Kode: string;
    Nama: string;
    Keterangan: string | null;
    VersiTerbit: { Versi: number; DiterbitkanPada: string | null } | null;
    VersiDraf: { Versi: number; Lolos: boolean; SudahDivalidasi: boolean } | null;
    VersiTerbaru: number | null;
};

/** Daftar template sektor berversi (P-03). */
export default function HalamanDaftarTemplateSektor({ Template }: { Template: RingkasanTemplate[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehBuat = PunyaIzin(props.Pengguna, IzinPengelola.TemplateIsiUbah);
    const [buatBaru, AturBuatBaru] = useState(false);

    return (
        <TataLetakPengelola
            judul="Template sektor"
            aksi={bolehBuat && !buatBaru ? <Tombol onClick={() => AturBuatBaru(true)}>Buat template</Tombol> : null}
        >
            <p className="text-isi text-teks-sekunder">
                Paket konfigurasi yang diterapkan saat tenant onboarding. Versi terbit tidak diubah; perbaikan dibuat
                sebagai draf versi baru. Tenant lama tidak berubah tanpa persetujuannya.
            </p>
            {buatBaru ? <FormBuatTemplate template={Template} saatSelesai={() => AturBuatBaru(false)} /> : null}
            {Template.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada template sektor">
                    Buat template pertama, misal Retail umum (RTL-GEN).
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar template sektor</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Template
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Versi terbit
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Draf
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Template.map((template) => (
                                <BarisTemplate key={template.Kode} template={template} />
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakPengelola>
    );
}

function BarisTemplate({ template }: { template: RingkasanTemplate }) {
    const versiBuka = template.VersiDraf?.Versi ?? template.VersiTerbit?.Versi ?? template.VersiTerbaru;

    return (
        <tr className="border-b border-garis align-top last:border-b-0">
            <td className="px-4 py-3">
                <p className="font-semibold text-teks-utama">{template.Nama}</p>
                <p className="font-mono text-keterangan text-teks-sekunder">{template.Kode}</p>
                {template.Keterangan ? (
                    <p className="text-keterangan text-teks-sekunder">{template.Keterangan}</p>
                ) : null}
            </td>
            <td className="px-4 py-3">
                {template.VersiTerbit ? (
                    <>
                        <p className="tabular-nums">Versi {template.VersiTerbit.Versi}</p>
                        <p className="text-keterangan text-teks-sekunder">
                            Terbit {FormatTanggal(template.VersiTerbit.DiterbitkanPada)}
                        </p>
                    </>
                ) : (
                    <LabelStatus jenis="peringatan" teks="Belum terbit" />
                )}
            </td>
            <td className="px-4 py-3">
                {template.VersiDraf ? (
                    <div className="flex flex-col gap-1">
                        <span className="tabular-nums">Versi {template.VersiDraf.Versi}</span>
                        <LabelStatus
                            jenis={template.VersiDraf.Lolos ? 'sukses' : 'bahaya'}
                            teks={
                                template.VersiDraf.Lolos
                                    ? 'Lolos validasi'
                                    : template.VersiDraf.SudahDivalidasi
                                      ? 'Belum lolos validasi'
                                      : 'Belum divalidasi'
                            }
                        />
                    </div>
                ) : (
                    <span className="text-teks-sekunder">Tidak ada</span>
                )}
            </td>
            <td className="px-4 py-3 text-right">
                {versiBuka ? (
                    <Link
                        href={`/template-sektor/${encodeURIComponent(template.Kode)}/versi/${versiBuka}`}
                        className="inline-flex h-10 items-center rounded-kontrol border border-garis-input px-4 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        Buka
                    </Link>
                ) : null}
            </td>
        </tr>
    );
}

type PropsFormBuat = { template: RingkasanTemplate[]; saatSelesai: () => void };

function FormBuatTemplate({ template, saatSelesai }: PropsFormBuat) {
    const formulir = useForm({ Kode: '', Nama: '', Keterangan: '', KodeTemplateDasar: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/template-sektor', { preserveScroll: true });
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">Buat template sektor</h2>
            <BidangTeks
                label="Kode sektor"
                kode
                keterangan="Sesuai PRD §5.1, misal FNB-RST. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangTeks
                label="Keterangan (opsional)"
                nilai={formulir.data.Keterangan}
                saatBerubah={(nilai) => formulir.setData('Keterangan', nilai)}
                galat={formulir.errors.Keterangan}
            />
            <BidangPilihan
                label="Salin isi dari"
                nilai={formulir.data.KodeTemplateDasar}
                kosong="Mulai kosong"
                opsi={template.map((item) => ({ Nilai: item.Kode, Label: `${item.Nama} (${item.Kode})` }))}
                saatBerubah={(nilai) => formulir.setData('KodeTemplateDasar', nilai)}
                galat={formulir.errors.KodeTemplateDasar}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Buat draf versi 1
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}
