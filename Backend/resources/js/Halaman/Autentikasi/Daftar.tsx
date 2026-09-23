import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import WidgetCaptcha from '@/Komponen/Formulir/WidgetCaptcha';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

type PropsDaftar = {
    Dibuka: boolean;
    Paket: { Kode: string; Nama: string; MasaTrialHari: number }[];
    PaketTerpilih: string;
    KunciSitusCaptcha: string | null;
};

/** Registrasi tenant (F-00 langkah 1). */
export default function HalamanDaftar({ Dibuka, Paket, PaketTerpilih, KunciSitusCaptcha }: PropsDaftar) {
    const formulir = useForm({
        Nama: '',
        Email: '',
        NoHp: '',
        KataSandi: '',
        KonfirmasiKataSandi: '',
        NamaUsaha: '',
        Paket: Paket.some((paket) => paket.Kode === PaketTerpilih) ? PaketTerpilih : (Paket[0]?.Kode ?? ''),
        Setuju: false,
    });
    const galat = formulir.errors as Record<string, string | undefined>;
    // Token disimpan di state tersendiri: setter useState stabil, jadi widget CAPTCHA tidak dirender ulang saat mengetik.
    const [tokenCaptcha, AturTokenCaptcha] = useState('');
    const [urutanResetCaptcha, AturUrutanResetCaptcha] = useState(0);

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((isian) => ({ ...isian, TokenCaptcha: tokenCaptcha }));
        formulir.post('/daftar', {
            // Token CAPTCHA hanya berlaku sekali: setelah galat apa pun, minta CAPTCHA baru; isian lain tetap.
            onError: () => AturUrutanResetCaptcha((urutan) => urutan + 1),
            onFinish: () => formulir.reset('KataSandi', 'KonfirmasiKataSandi'),
        });
    };

    if (!Dibuka) {
        return (
            <TataLetakAutentikasi judul="Daftar">
                <Pemberitahuan jenis="info" judul="Pendaftaran belum dibuka">
                    Kami sedang menyiapkan layanan. Silakan kembali lagi nanti.
                </Pemberitahuan>
            </TataLetakAutentikasi>
        );
    }

    return (
        <TataLetakAutentikasi judul="Daftar gratis" keterangan="Mulai masa trial tanpa kartu kredit." lebar="sedang">
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-2" noValidate>
                <BidangTeks
                    label="Nama Anda"
                    autoComplete="name"
                    nilai={formulir.data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={galat.Nama}
                />
                <BidangTeks
                    label="Nama usaha"
                    autoComplete="organization"
                    nilai={formulir.data.NamaUsaha}
                    saatBerubah={(nilai) => formulir.setData('NamaUsaha', nilai)}
                    galat={galat.NamaUsaha}
                />
                <BidangTeks
                    label="Email"
                    jenis="email"
                    autoComplete="email"
                    nilai={formulir.data.Email}
                    saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                    galat={galat.Email}
                />
                <BidangTeks
                    label="Nomor WhatsApp"
                    inputMode="tel"
                    autoComplete="tel"
                    keterangan="Misal 081234567890."
                    nilai={formulir.data.NoHp}
                    saatBerubah={(nilai) => formulir.setData('NoHp', nilai)}
                    galat={galat.NoHp}
                />
                <BidangTeks
                    label="Kata sandi"
                    jenis="password"
                    autoComplete="new-password"
                    keterangan="Minimal 8 karakter, berisi huruf dan angka."
                    nilai={formulir.data.KataSandi}
                    saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                    galat={galat.KataSandi}
                />
                <BidangTeks
                    label="Ulangi kata sandi"
                    jenis="password"
                    autoComplete="new-password"
                    nilai={formulir.data.KonfirmasiKataSandi}
                    saatBerubah={(nilai) => formulir.setData('KonfirmasiKataSandi', nilai)}
                    galat={galat.KonfirmasiKataSandi}
                />
                <div className="sm:col-span-2">
                    <BidangPilihan
                        label="Paket yang dicoba"
                        nilai={formulir.data.Paket}
                        opsi={Paket.map((paket) => ({
                            Nilai: paket.Kode,
                            Label:
                                paket.MasaTrialHari > 0
                                    ? `${paket.Nama} (trial ${paket.MasaTrialHari} hari)`
                                    : paket.Nama,
                        }))}
                        saatBerubah={(nilai) => formulir.setData('Paket', nilai)}
                        galat={galat.Paket}
                    />
                </div>
                <label className="flex items-start gap-2 text-isi text-teks-utama sm:col-span-2">
                    <input
                        type="checkbox"
                        className="mt-1 size-4 accent-brand"
                        checked={formulir.data.Setuju}
                        onChange={(peristiwa) => formulir.setData('Setuju', peristiwa.target.checked)}
                    />
                    <span>
                        Saya menyetujui{' '}
                        <a
                            href="/legal/syarat-ketentuan"
                            target="_blank"
                            rel="noreferrer"
                            className="font-semibold text-brand underline"
                        >
                            Syarat & Ketentuan
                        </a>{' '}
                        dan{' '}
                        <a
                            href="/legal/kebijakan-privasi"
                            target="_blank"
                            rel="noreferrer"
                            className="font-semibold text-brand underline"
                        >
                            Kebijakan Privasi
                        </a>
                        .
                    </span>
                </label>
                {galat.Setuju ? (
                    <p className="text-keterangan font-semibold text-bahaya sm:col-span-2">{galat.Setuju}</p>
                ) : null}
                {KunciSitusCaptcha ? (
                    <div className="sm:col-span-2">
                        <WidgetCaptcha
                            kunciSitus={KunciSitusCaptcha}
                            saatBerubah={AturTokenCaptcha}
                            urutanReset={urutanResetCaptcha}
                            galat={galat.TokenCaptcha}
                        />
                    </div>
                ) : null}
                <div className="flex flex-col gap-3 sm:col-span-2">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Daftar dan mulai trial
                    </Tombol>
                    <p className="text-keterangan text-teks-sekunder">
                        Sudah punya akun?{' '}
                        <Link href="/masuk" className="font-semibold text-brand underline">
                            Masuk
                        </Link>
                    </p>
                </div>
            </form>
        </TataLetakAutentikasi>
    );
}
