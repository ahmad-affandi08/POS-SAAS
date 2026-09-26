import { Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KartuFormulir from '@/Komponen/Formulir/KartuFormulir';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import { cn } from '@/Komponen/Ui/utils';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { CekBatasPenuh, FormatBatas, type PropsBuatUndangan } from '@/Tipe/Organisasi';

type Jenis = 'Kasir' | 'BackOffice';

type Isian = {
    Nama: string;
    Email: string;
    NoHp: string;
    KataSandi: string;
    Pin: string;
    Peran: string;
    SemuaOutlet: boolean;
    Outlet: string[];
};

const JENIS: { nilai: Jenis; judul: string; keterangan: string }[] = [
    {
        nilai: 'Kasir',
        judul: 'Karyawan kasir',
        keterangan: 'Tanpa email. Masuk aplikasi kasir dengan PIN 6 angka.',
    },
    {
        nilai: 'BackOffice',
        judul: 'Pakai email',
        keterangan: 'Bisa membuka dashboard sesuai peran. Kata sandi awal wajib diganti saat pertama masuk.',
    },
];

/**
 * D-22: tambah pengguna langsung tanpa undangan email (F-02 langkah 3, BR-02.1). Karyawan kasir cukup nama + PIN;
 * pengguna dengan email mendapat kata sandi awal dari admin.
 */
export default function HalamanTambahPengguna({ Peran, Outlet, BatasPengguna }: PropsBuatUndangan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const sayaPemilik = props.Akses?.Pemilik ?? false;
    const peranTerlihat = Peran.filter((peran) => sayaPemilik || !peran.Pemilik);
    const formulir = useForm<Isian & { Jenis: Jenis }>({
        Jenis: 'Kasir',
        Nama: '',
        Email: '',
        NoHp: '',
        KataSandi: '',
        Pin: '',
        Peran: '',
        SemuaOutlet: false,
        Outlet: [],
    });
    const d = formulir.data;
    const peranTerpilih = peranTerlihat.find((baris) => baris.Uuid === d.Peran);
    const semuaOutletPaksa = peranTerpilih?.Pemilik ?? false;
    const kasir = d.Jenis === 'Kasir';

    const PilihPeran = (uuid: string) => {
        const baris = peranTerlihat.find((item) => item.Uuid === uuid);
        formulir.setData({
            ...d,
            Peran: uuid,
            SemuaOutlet: baris?.Pemilik === true || (baris?.SemuaOutletBawaan ?? false),
        });
    };

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.transform((data) => ({
            Nama: data.Nama,
            Email: kasir ? '' : data.Email,
            NoHp: data.NoHp,
            KataSandi: kasir ? '' : data.KataSandi,
            Pin: data.Pin,
            Peran: data.Peran,
            SemuaOutlet: data.SemuaOutlet,
            Outlet: data.Outlet,
        }));
        formulir.post('/kelola/pengguna', { preserveScroll: true, onError: () => formulir.reset('KataSandi', 'Pin') });
    };

    const isianForm = ['Nama', 'Email', 'NoHp', 'KataSandi', 'Pin', 'Peran', 'SemuaOutlet', 'Outlet'];

    return (
        <TataLetakAplikasi judul="Tambah pengguna">
            <DaftarGalatServer
                galat={props.errors}
                kecuali={Object.keys(props.errors).filter((k) => isianForm.some((i) => k.startsWith(i)))}
            />
            {CekBatasPenuh(BatasPengguna) ? (
                <Pemberitahuan jenis="info" judul="Batas pengguna paket sudah tercapai">
                    Nonaktifkan pengguna yang tidak dipakai, batalkan undangan, atau tingkatkan paket di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    .
                </Pemberitahuan>
            ) : null}
            <KartuFormulir keterangan={`Kursi pengguna terpakai: ${FormatBatas(BatasPengguna, 'pengguna')}.`}>
                <form onSubmit={Kirim} className="flex flex-col gap-4" noValidate>
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-1 text-label font-semibold text-teks-utama">Jenis pengguna</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {JENIS.map((j) => (
                                <label
                                    key={j.nilai}
                                    className={cn(
                                        'flex cursor-pointer flex-col gap-1 rounded-panel border p-3',
                                        d.Jenis === j.nilai ? 'border-2 border-brand bg-brand-lembut' : 'border-garis',
                                    )}
                                >
                                    <span className="flex items-center gap-2 text-isi font-semibold text-teks-utama">
                                        <input
                                            type="radio"
                                            name="Jenis"
                                            value={j.nilai}
                                            checked={d.Jenis === j.nilai}
                                            onChange={() => formulir.setData('Jenis', j.nilai)}
                                            className="size-4 accent-brand"
                                        />
                                        {j.judul}
                                    </span>
                                    <span className="text-keterangan text-teks-sekunder">{j.keterangan}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <BidangTeks
                        label="Nama"
                        nilai={d.Nama}
                        saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                        galat={formulir.errors.Nama}
                        maxLength={150}
                        autoFocus
                        required
                    />
                    {kasir ? null : (
                        <>
                            <BidangTeks
                                label="Email (untuk masuk dashboard)"
                                jenis="email"
                                nilai={d.Email}
                                saatBerubah={(nilai) => formulir.setData('Email', nilai)}
                                galat={formulir.errors.Email}
                                maxLength={191}
                                required
                            />
                            <BidangTeks
                                label="Kata sandi awal"
                                jenis="password"
                                keterangan="Minimal 8 karakter, berisi huruf dan angka. Berikan ke pengguna; ia wajib menggantinya saat pertama masuk."
                                nilai={d.KataSandi}
                                saatBerubah={(nilai) => formulir.setData('KataSandi', nilai)}
                                galat={formulir.errors.KataSandi}
                                autoComplete="new-password"
                                required
                            />
                        </>
                    )}
                    <BidangTeks
                        label={kasir ? 'PIN kasir' : 'PIN kasir (opsional)'}
                        jenis="password"
                        inputMode="numeric"
                        keterangan="6 angka, bukan angka sama semua atau berurutan (misal 123456). Dipakai masuk aplikasi kasir."
                        nilai={d.Pin}
                        saatBerubah={(nilai) => formulir.setData('Pin', nilai.replace(/\D/g, '').slice(0, 6))}
                        galat={formulir.errors.Pin}
                        maxLength={6}
                        autoComplete="off"
                        required={kasir}
                    />
                    <BidangTeks
                        label="Nomor WhatsApp (opsional)"
                        inputMode="tel"
                        nilai={d.NoHp}
                        saatBerubah={(nilai) => formulir.setData('NoHp', nilai)}
                        galat={formulir.errors.NoHp}
                        maxLength={20}
                    />
                    <BidangPilihan
                        label="Peran"
                        nilai={d.Peran}
                        opsi={peranTerlihat.map((baris) => ({ Nilai: baris.Uuid, Label: baris.Nama }))}
                        saatBerubah={PilihPeran}
                        galat={formulir.errors.Peran}
                        required
                        kosong="Pilih peran"
                    />
                    <KotakCentang
                        label={
                            semuaOutletPaksa
                                ? 'Semua outlet (Pemilik selalu mengakses semua outlet)'
                                : 'Semua outlet, termasuk outlet baru'
                        }
                        nilai={d.SemuaOutlet || semuaOutletPaksa}
                        saatBerubah={(nilai) => formulir.setData('SemuaOutlet', nilai)}
                    />
                    {formulir.errors.SemuaOutlet ? (
                        <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.SemuaOutlet}</p>
                    ) : null}
                    {!d.SemuaOutlet && !semuaOutletPaksa ? (
                        <GrupCentang
                            legenda="Outlet yang ditugaskan"
                            opsi={Outlet.map((baris) => ({
                                nilai: baris.Uuid,
                                label: `${baris.Kode} · ${baris.Nama}`,
                            }))}
                            terpilih={d.Outlet}
                            saatBerubah={(terpilih) => formulir.setData('Outlet', terpilih)}
                            galat={formulir.errors.Outlet}
                            required
                        />
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        <Tombol type="submit" memproses={formulir.processing}>
                            Tambah pengguna
                        </Tombol>
                        <Tombol varian="sekunder" onClick={() => router.visit('/kelola/pengguna')}>
                            Batal
                        </Tombol>
                    </div>
                </form>
            </KartuFormulir>
        </TataLetakAplikasi>
    );
}
