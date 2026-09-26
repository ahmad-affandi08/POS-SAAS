import { usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';

import TeksKaya from '@/Komponen/Situs/TeksKaya';
import TombolSitus from '@/Komponen/Situs/TombolSitus';
import { cn } from '@/Komponen/Ui/utils';
import { FormatRupiah } from '@/Pustaka/Format';
import type { BagianSitus, DataSitus, PaketHarga } from '@/Tipe/Situs';

import { KepalaBagian, WadahBagian } from './KepalaBagian';

type Periode = 'Bulanan' | 'Tahunan';

function CekNol(nilai: string): boolean {
    return /^0+(\.0+)?$/.test(nilai);
}

function TampilkanHarga({ paket, periode }: { paket: PaketHarga; periode: Periode }) {
    if (paket.HargaNegosiasi) {
        return <p className="text-judul font-bold text-teks-utama">Hubungi kami</p>;
    }

    const nilai = periode === 'Tahunan' ? paket.HargaTahunan : paket.HargaBulanan;

    if (nilai === null) {
        return null;
    }

    if (CekNol(nilai)) {
        return <p className="text-tampilan font-bold text-teks-utama">Gratis</p>;
    }

    return (
        <p className="flex flex-wrap items-baseline gap-1">
            <span className="text-tampilan font-bold text-teks-utama">{FormatRupiah(nilai)}</span>
            <span className="text-isi text-teks-sekunder">/{periode === 'Tahunan' ? 'tahun' : 'bulan'}</span>
        </p>
    );
}

/**
 * Harga paket otomatis dari konsol (P-04): harga terbit yang berlaku hari ini, batas, fitur. Pengelola hanya mengatur
 * judul, paket yang disorot, teks tombol, dan catatan kaki. Harga sudah termasuk/tidak termasuk PPN ditulis di catatan.
 */
export default function BagianHarga({
    bagian,
    latar,
}: {
    bagian: Extract<BagianSitus, { Jenis: 'Harga' }>;
    latar: 'latar' | 'permukaan';
}) {
    const { props } = usePage<{ Situs: DataSitus }>();
    const [periode, AturPeriode] = useState<Periode>('Bulanan');
    const adaTahunan =
        bagian.TampilkanTahunan && bagian.Paket.some((p) => p.HargaTahunan !== null && !CekNol(p.HargaTahunan));
    const tautanKontak = props.Situs.Kontak.TautanWhatsApp ?? '/kontak';

    return (
        <WadahBagian latar={latar} id="harga">
            <KepalaBagian label={bagian.Label} judul={bagian.Judul} subjudul={bagian.Subjudul} />
            {adaTahunan ? (
                <div className="mb-8 flex justify-center">
                    <div
                        role="radiogroup"
                        aria-label="Periode tagihan"
                        className="inline-flex rounded-kontrol border border-garis bg-permukaan p-1"
                    >
                        {(['Bulanan', 'Tahunan'] as const).map((p) => (
                            <button
                                key={p}
                                type="button"
                                role="radio"
                                aria-checked={periode === p}
                                onClick={() => AturPeriode(p)}
                                className={cn(
                                    'min-h-10 rounded-kontrol px-4 text-isi font-semibold',
                                    periode === p
                                        ? 'bg-brand text-brand-teks'
                                        : 'text-teks-utama hover:bg-permukaan-sorot',
                                )}
                            >
                                {p}
                            </button>
                        ))}
                    </div>
                </div>
            ) : null}
            {bagian.Paket.length === 0 ? (
                <p className="text-center text-isi text-teks-sekunder">
                    Harga paket sedang diperbarui. Hubungi kami untuk informasi terbaru.
                </p>
            ) : (
                <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(16rem,1fr))]">
                    {bagian.Paket.map((paket) => {
                        const disorot = paket.Kode === bagian.PaketDisorot;

                        return (
                            <li
                                key={paket.Kode}
                                className={cn(
                                    'relative flex flex-col gap-5 rounded-panel border bg-permukaan p-6',
                                    disorot ? 'border-2 border-brand' : 'border-garis',
                                )}
                            >
                                {disorot ? (
                                    <p className="absolute -top-3 left-6 rounded-full bg-brand px-3 py-0.5 text-keterangan font-semibold text-brand-teks">
                                        Paling populer
                                    </p>
                                ) : null}
                                <div className="flex flex-col gap-1">
                                    <h3 className="text-judul font-semibold text-teks-utama">{paket.Nama}</h3>
                                    {paket.Keterangan ? (
                                        <p className="text-isi text-teks-sekunder">{paket.Keterangan}</p>
                                    ) : null}
                                </div>
                                <div className="flex flex-col gap-1">
                                    <TampilkanHarga paket={paket} periode={periode} />
                                    {periode === 'Tahunan' && paket.HematTahunan ? (
                                        <p className="text-label font-semibold text-sukses">
                                            Hemat {FormatRupiah(paket.HematTahunan)} per tahun
                                        </p>
                                    ) : null}
                                    {paket.MasaTrialHari > 0 ? (
                                        <p className="text-label text-teks-sekunder">
                                            Coba gratis {paket.MasaTrialHari} hari
                                        </p>
                                    ) : null}
                                </div>
                                <TombolSitus
                                    href={paket.HargaNegosiasi ? tautanKontak : bagian.TautanDaftar}
                                    varian={disorot ? 'utama' : 'kedua'}
                                >
                                    {paket.HargaNegosiasi ? 'Hubungi kami' : (bagian.TeksTombol ?? 'Mulai sekarang')}
                                </TombolSitus>
                                <ul className="flex flex-col gap-2 border-t border-garis pt-4">
                                    {[...paket.Batas, ...paket.Fitur].map((baris) => (
                                        <li key={baris} className="flex gap-2 text-isi text-teks-utama">
                                            <Check className="mt-0.5 size-4 shrink-0 text-sukses" aria-hidden />
                                            <span>{baris}</span>
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        );
                    })}
                </ul>
            )}
            {bagian.CatatanKaki ? (
                <TeksKaya
                    teks={bagian.CatatanKaki}
                    className="mx-auto mt-8 max-w-3xl text-center text-label text-teks-sekunder"
                />
            ) : null}
        </WadahBagian>
    );
}
