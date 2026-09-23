import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type BidangPengaturan = {
    Kunci: string;
    Label: string;
    Jenis: 'Teks' | 'Angka' | 'Email' | 'Url' | 'Pilihan';
    Wajib: boolean;
    Opsi?: string[];
    Keterangan?: string;
};

type SlotIntegrasi = {
    Jenis: string;
    LabelJenis: string;
    Lingkungan: 'Staging' | 'Produksi';
    LingkunganServer: boolean;
    Penyedia: { Nilai: string; Label: string };
    BidangPengaturan: BidangPengaturan[];
    BidangKredensial: { Kunci: string; Label: string }[];
    Konfigurasi: {
        Uuid: string;
        Pengaturan: Record<string, string | number>;
        PetunjukKredensial: Record<string, string>;
        Aktif: boolean;
        Status: 'BelumDiuji' | 'Terhubung' | 'Gagal';
        LabelStatus: string;
        TerakhirDiujiPada: string | null;
        HasilUji: { Berhasil: boolean; Pesan: string; DurasiMs: number } | null;
        KredensialDiubahPada: string;
        RotasiSetiapHari: number;
        PerluRotasi: boolean;
    } | null;
};

const jenisLabelStatus = { BelumDiuji: 'peringatan', Terhubung: 'sukses', Gagal: 'bahaya' } as const;

/** Konfigurasi integrasi platform: email, CAPTCHA, penyimpanan objek (P-05). */
export default function HalamanIntegrasi({ Integrasi }: { Integrasi: SlotIntegrasi[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.IntegrasiKelola);
    const daftarJenis = [...new Set(Integrasi.map((slot) => slot.Jenis))];

    return (
        <TataLetakPengelola judul="Integrasi">
            <p className="text-isi text-teks-sekunder">
                Kredensial disimpan terenkripsi dan tidak pernah ditampilkan ulang. Setiap perubahan harus diuji sebelum
                integrasi bisa diaktifkan. Server ini memakai konfigurasi yang bertanda &ldquo;Dipakai server
                ini&rdquo;.
            </p>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            {daftarJenis.map((jenis) => {
                const slot = Integrasi.filter((baris) => baris.Jenis === jenis);

                return (
                    <section key={jenis} className="flex flex-col gap-3">
                        <h2 className="text-subjudul font-semibold text-teks-utama">
                            {slot[0]?.LabelJenis} · {slot[0]?.Penyedia.Label}
                        </h2>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {slot.map((baris) => (
                                <KartuIntegrasi key={baris.Lingkungan} slot={baris} bolehKelola={bolehKelola} />
                            ))}
                        </div>
                    </section>
                );
            })}
        </TataLetakPengelola>
    );
}

function KartuIntegrasi({ slot, bolehKelola }: { slot: SlotIntegrasi; bolehKelola: boolean }) {
    const [sunting, AturSunting] = useState(false);
    const [alasan, AturAlasan] = useState('');
    const [memproses, AturMemproses] = useState(false);
    const konfigurasi = slot.Konfigurasi;
    const produksi = slot.Lingkungan === 'Produksi';
    const opsiKirim = {
        preserveScroll: true,
        onStart: () => AturMemproses(true),
        onFinish: () => AturMemproses(false),
        onSuccess: () => AturAlasan(''),
    };
    const UbahStatus = (aksi: 'aktifkan' | 'nonaktifkan') => {
        if (konfigurasi) {
            router.post(`/integrasi/${konfigurasi.Uuid}/${aksi}`, { Alasan: alasan }, opsiKirim);
        }
    };

    return (
        <article className="flex flex-col gap-3 rounded-panel border border-garis bg-permukaan p-5">
            <div className="flex flex-wrap items-center gap-2">
                <h3 className="text-label font-semibold text-teks-utama">{slot.Lingkungan}</h3>
                {slot.LingkunganServer ? <LabelStatus jenis="netral" teks="Dipakai server ini" /> : null}
                {konfigurasi ? (
                    <>
                        <LabelStatus jenis={jenisLabelStatus[konfigurasi.Status]} teks={konfigurasi.LabelStatus} />
                        <LabelStatus
                            jenis={konfigurasi.Aktif ? 'sukses' : 'netral'}
                            teks={konfigurasi.Aktif ? 'Aktif' : 'Nonaktif'}
                        />
                        {konfigurasi.PerluRotasi ? <LabelStatus jenis="peringatan" teks="Perlu rotasi kunci" /> : null}
                    </>
                ) : (
                    <LabelStatus jenis="netral" teks="Belum diatur" />
                )}
            </div>

            {konfigurasi ? (
                <dl className="grid grid-cols-1 gap-x-4 gap-y-1 text-keterangan sm:grid-cols-2">
                    {slot.BidangPengaturan.map((bidang) => (
                        <div key={bidang.Kunci} className="flex flex-col">
                            <dt className="text-teks-sekunder">{bidang.Label}</dt>
                            <dd className="break-all text-teks-utama">
                                {String(konfigurasi.Pengaturan[bidang.Kunci] ?? '-')}
                            </dd>
                        </div>
                    ))}
                    {slot.BidangKredensial.map((bidang) => (
                        <div key={bidang.Kunci} className="flex flex-col">
                            <dt className="text-teks-sekunder">{bidang.Label}</dt>
                            <dd className="font-mono text-teks-utama">
                                {konfigurasi.PetunjukKredensial[bidang.Kunci] ?? '••••'}
                            </dd>
                        </div>
                    ))}
                    <div className="flex flex-col sm:col-span-2">
                        <dt className="text-teks-sekunder">Tes koneksi terakhir</dt>
                        <dd className="text-teks-utama">
                            {konfigurasi.HasilUji && konfigurasi.TerakhirDiujiPada
                                ? `${FormatTanggalWaktu(konfigurasi.TerakhirDiujiPada)} · ${konfigurasi.HasilUji.Pesan}`
                                : 'Belum pernah diuji'}
                        </dd>
                    </div>
                    <div className="flex flex-col sm:col-span-2">
                        <dt className="text-teks-sekunder">Kredensial terakhir diganti</dt>
                        <dd className="text-teks-utama">
                            {FormatTanggal(konfigurasi.KredensialDiubahPada)} · rotasi setiap{' '}
                            {konfigurasi.RotasiSetiapHari} hari
                        </dd>
                    </div>
                </dl>
            ) : (
                <p className="text-keterangan text-teks-sekunder">
                    Belum ada konfigurasi {slot.Lingkungan.toLowerCase()} untuk {slot.LabelJenis.toLowerCase()}.
                </p>
            )}

            {bolehKelola && !sunting ? (
                <div className="flex flex-col gap-3">
                    {konfigurasi && produksi ? (
                        <BidangTeks
                            label="Alasan (wajib untuk produksi)"
                            nilai={alasan}
                            saatBerubah={AturAlasan}
                            keterangan="Dipakai saat mengaktifkan atau menonaktifkan."
                        />
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        <Tombol varian="sekunder" onClick={() => AturSunting(true)}>
                            {konfigurasi ? 'Ubah' : 'Atur'}
                        </Tombol>
                        {konfigurasi ? (
                            <Tombol
                                varian="sekunder"
                                memproses={memproses}
                                onClick={() => router.post(`/integrasi/${konfigurasi.Uuid}/uji`, {}, opsiKirim)}
                            >
                                Uji koneksi
                            </Tombol>
                        ) : null}
                        {konfigurasi && !konfigurasi.Aktif ? (
                            <Tombol memproses={memproses} onClick={() => UbahStatus('aktifkan')}>
                                Aktifkan
                            </Tombol>
                        ) : null}
                        {konfigurasi?.Aktif ? (
                            <Tombol varian="bahaya" memproses={memproses} onClick={() => UbahStatus('nonaktifkan')}>
                                Nonaktifkan
                            </Tombol>
                        ) : null}
                    </div>
                </div>
            ) : null}

            {sunting ? <FormIntegrasi slot={slot} saatSelesai={() => AturSunting(false)} /> : null}
        </article>
    );
}

type IsianIntegrasi = {
    Jenis: string;
    Lingkungan: string;
    Pengaturan: Record<string, string>;
    Kredensial: Record<string, string>;
    RotasiSetiapHari: string;
    Alasan: string;
};

function FormIntegrasi({ slot, saatSelesai }: { slot: SlotIntegrasi; saatSelesai: () => void }) {
    const konfigurasi = slot.Konfigurasi;
    const formulir = useForm<IsianIntegrasi>({
        Jenis: slot.Jenis,
        Lingkungan: slot.Lingkungan,
        Pengaturan: Object.fromEntries(
            slot.BidangPengaturan.map((bidang) => [
                bidang.Kunci,
                String(konfigurasi?.Pengaturan[bidang.Kunci] ?? bidang.Opsi?.[0] ?? ''),
            ]),
        ),
        Kredensial: Object.fromEntries(slot.BidangKredensial.map((bidang) => [bidang.Kunci, ''])),
        RotasiSetiapHari: String(konfigurasi?.RotasiSetiapHari ?? 90),
        Alasan: '',
    });
    const galat = formulir.errors as Record<string, string | undefined>;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/integrasi', { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <form onSubmit={Kirim} className="grid gap-3 border-t border-garis pt-4 sm:grid-cols-2" noValidate>
            {slot.BidangPengaturan.map((bidang) =>
                bidang.Jenis === 'Pilihan' ? (
                    <BidangPilihan
                        key={bidang.Kunci}
                        label={bidang.Label}
                        nilai={formulir.data.Pengaturan[bidang.Kunci] ?? ''}
                        opsi={(bidang.Opsi ?? []).map((opsi) => ({ Nilai: opsi, Label: opsi.toUpperCase() }))}
                        saatBerubah={(nilai) =>
                            formulir.setData('Pengaturan', { ...formulir.data.Pengaturan, [bidang.Kunci]: nilai })
                        }
                        galat={galat[`Pengaturan.${bidang.Kunci}`]}
                    />
                ) : (
                    <BidangTeks
                        key={bidang.Kunci}
                        label={bidang.Label}
                        {...(bidang.Keterangan ? { keterangan: bidang.Keterangan } : {})}
                        jenis={bidang.Jenis === 'Email' ? 'email' : 'text'}
                        inputMode={bidang.Jenis === 'Angka' ? 'numeric' : undefined}
                        nilai={formulir.data.Pengaturan[bidang.Kunci] ?? ''}
                        saatBerubah={(nilai) =>
                            formulir.setData('Pengaturan', { ...formulir.data.Pengaturan, [bidang.Kunci]: nilai })
                        }
                        galat={galat[`Pengaturan.${bidang.Kunci}`]}
                    />
                ),
            )}
            {slot.BidangKredensial.map((bidang) => (
                <BidangTeks
                    key={bidang.Kunci}
                    label={bidang.Label}
                    jenis="password"
                    autoComplete="new-password"
                    keterangan={
                        konfigurasi
                            ? `Tersimpan ${konfigurasi.PetunjukKredensial[bidang.Kunci] ?? '••••'}. Kosongkan bila tidak diganti.`
                            : 'Wajib diisi.'
                    }
                    nilai={formulir.data.Kredensial[bidang.Kunci] ?? ''}
                    saatBerubah={(nilai) =>
                        formulir.setData('Kredensial', { ...formulir.data.Kredensial, [bidang.Kunci]: nilai })
                    }
                    galat={galat[`Kredensial.${bidang.Kunci}`]}
                />
            ))}
            <BidangTeks
                label="Rotasi kunci setiap (hari)"
                inputMode="numeric"
                nilai={formulir.data.RotasiSetiapHari}
                saatBerubah={(nilai) => formulir.setData('RotasiSetiapHari', nilai)}
                galat={formulir.errors.RotasiSetiapHari}
            />
            {slot.Lingkungan === 'Produksi' ? (
                <BidangTeks
                    label="Alasan perubahan"
                    nilai={formulir.data.Alasan}
                    saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                    galat={formulir.errors.Alasan}
                />
            ) : null}
            <p className="text-keterangan text-teks-sekunder sm:col-span-2">
                Setelah disimpan, integrasi menjadi nonaktif sampai tes koneksi berhasil.
            </p>
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}
