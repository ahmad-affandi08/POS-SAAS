import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import { Button } from '@/Komponen/Ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Separator } from '@/Komponen/Ui/separator';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

type PropsKeamananAkun = {
    DuaFaktor: { Aktif: boolean; AktifPada: string | null; SisaKodePemulihan: number; Wajib: boolean };
    Aktivasi: { QrSvg: string; Rahasia: string } | null;
    KodePemulihanBaru: string[] | null;
};

/** Keamanan akun: aktifkan atau nonaktifkan verifikasi dua langkah (§20.2, BR-00.8). */
export default function HalamanKeamananAkun({ DuaFaktor, Aktivasi, KodePemulihanBaru }: PropsKeamananAkun) {
    return (
        <TataLetakAplikasi judul="Keamanan akun">
            <Card className="max-w-xl gap-4 rounded-panel py-6 shadow-none">
                <CardHeader className="gap-1 px-6">
                    <CardTitle className="text-subjudul font-bold text-teks-utama">
                        <h2>Verifikasi dua langkah</h2>
                    </CardTitle>
                    <CardDescription className="text-isi text-teks-sekunder">
                        Selain kata sandi, masuk memerlukan kode 6 digit dari aplikasi autentikator di ponsel Anda.
                    </CardDescription>
                    <p className="text-label font-semibold text-teks-utama">
                        Status: {DuaFaktor.Aktif ? 'Aktif' : 'Belum aktif'}
                        {DuaFaktor.Aktif && DuaFaktor.AktifPada
                            ? ` sejak ${FormatTanggalWaktu(DuaFaktor.AktifPada)}`
                            : null}
                    </p>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 px-6">
                    {DuaFaktor.Wajib && !DuaFaktor.Aktif ? (
                        <Pemberitahuan jenis="peringatan" judul="Wajib untuk peran Anda di paket ini">
                            Aktifkan verifikasi dua langkah sebelum membuka menu lain di back-office.
                        </Pemberitahuan>
                    ) : null}
                    {KodePemulihanBaru ? <DaftarKodePemulihan kode={KodePemulihanBaru} /> : null}
                    {Aktivasi ? <FormulirAktivasi aktivasi={Aktivasi} /> : null}
                    {DuaFaktor.Aktif ? (
                        <FormulirNonaktifkan wajib={DuaFaktor.Wajib} sisaKode={DuaFaktor.SisaKodePemulihan} />
                    ) : null}
                </CardContent>
            </Card>
            {/* F-02b: PIN kasir untuk masuk cepat di aplikasi kasir. */}
            <Card className="max-w-xl gap-3 rounded-panel py-6 shadow-none">
                <CardHeader className="gap-1 px-6">
                    <CardTitle className="text-subjudul font-bold text-teks-utama">
                        <h2>PIN kasir</h2>
                    </CardTitle>
                    <CardDescription className="text-isi text-teks-sekunder">
                        PIN 6 angka untuk masuk cepat di perangkat kasir bersama.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-6">
                    <Button
                        asChild
                        variant="outline"
                        className="h-8 pointer-coarse:h-11 border-garis-input text-label font-semibold"
                    >
                        <Link href="/kelola/keamanan/pin">Atur PIN kasir</Link>
                    </Button>
                </CardContent>
            </Card>
        </TataLetakAplikasi>
    );
}

function DaftarKodePemulihan({ kode }: { kode: string[] }) {
    return (
        <div className="flex flex-col gap-3">
            <Pemberitahuan jenis="peringatan" judul="Simpan kode pemulihan ini sekarang">
                Kode hanya ditampilkan sekali. Setiap kode bisa dipakai satu kali untuk masuk bila ponsel Anda tidak
                tersedia.
            </Pemberitahuan>
            <ul className="grid grid-cols-2 gap-2 font-mono text-isi text-teks-utama">
                {kode.map((baris) => (
                    <li key={baris} className="rounded-kontrol border border-garis bg-latar px-3 py-2 text-center">
                        {baris}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function FormulirAktivasi({ aktivasi }: { aktivasi: { QrSvg: string; Rahasia: string } }) {
    const formulir = useForm({ Kode: '' });
    const sumberQr = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(aktivasi.QrSvg)}`;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post('/kelola/keamanan/dua-faktor', { onFinish: () => formulir.reset('Kode') });
    };

    return (
        <div className="flex flex-col gap-4">
            <ol className="flex list-decimal flex-col gap-1 pl-5 text-isi text-teks-utama">
                <li>Pasang aplikasi autentikator, misalnya Google Authenticator atau Aegis.</li>
                <li>Pindai kode QR di bawah dengan aplikasi tersebut.</li>
                <li>Masukkan 6 digit kode yang muncul di aplikasi.</li>
            </ol>
            <img src={sumberQr} alt="Kode QR verifikasi dua langkah" width={192} height={192} className="self-start" />
            <p className="text-keterangan text-teks-sekunder">
                Tidak bisa memindai? Masukkan kunci ini secara manual:
                <span className="mt-1 block font-mono text-label text-teks-utama">{aktivasi.Rahasia}</span>
            </p>
            <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                <BidangTeks
                    label="Kode 6 digit"
                    kode
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    nilai={formulir.data.Kode}
                    saatBerubah={(nilai) => formulir.setData('Kode', nilai)}
                    galat={formulir.errors.Kode}
                    required
                />
                <Tombol type="submit" memproses={formulir.processing}>
                    Aktifkan verifikasi dua langkah
                </Tombol>
            </form>
        </div>
    );
}

function FormulirNonaktifkan({ wajib, sisaKode }: { wajib: boolean; sisaKode: number }) {
    const formulir = useForm({ KataSandi: '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.delete('/kelola/keamanan/dua-faktor', { onFinish: () => formulir.reset('KataSandi') });
    };

    return (
        <div className="flex flex-col gap-3">
            <Separator className="bg-garis" />
            <p className="text-isi text-teks-sekunder">
                Sisa kode pemulihan: <span className="tabular-nums">{sisaKode}</span> dari 8.
                {sisaKode <= 2 ? ' Nonaktifkan lalu aktifkan lagi untuk mendapat kode baru.' : null}
            </p>
            {wajib ? (
                <p className="text-isi text-teks-sekunder">
                    Paket langganan usaha tempat Anda bergabung mewajibkan verifikasi dua langkah untuk peran Anda
                    (Owner, Admin, atau Akuntan), jadi tidak bisa dinonaktifkan.
                </p>
            ) : (
                <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                    <BidangTeks
                        label="Kata sandi saat ini"
                        jenis="password"
                        autoComplete="current-password"
                        keterangan="Untuk keamanan, konfirmasi kata sandi sebelum menonaktifkan."
                        nilai={formulir.data.KataSandi}
                        saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                        galat={formulir.errors.KataSandi}
                        required
                    />
                    <Tombol type="submit" varian="bahaya" memproses={formulir.processing}>
                        Nonaktifkan verifikasi dua langkah
                    </Tombol>
                </form>
            )}
        </div>
    );
}
