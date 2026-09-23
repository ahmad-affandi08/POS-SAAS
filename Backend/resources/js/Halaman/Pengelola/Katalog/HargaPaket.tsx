import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Persetujuan = { Peninjau: string; IdPeninjau: number; Keputusan: 'Setuju' | 'Tolak'; Catatan: string | null };

type Harga = {
    Uuid: string;
    HargaBulanan: string;
    HargaTahunan: string;
    BerlakuMulai: string;
    BerlakuSampai: string | null;
    TerapkanKePelangganLama: boolean;
    Status: 'Draf' | 'MenungguTinjauan' | 'Terbit' | 'Berakhir';
    DaftarIdPenyusun: number[];
    IdPengaju: number | null;
    Persetujuan: Persetujuan[];
};

type PropsHargaPaket = {
    Paket: { Uuid: string; Kode: string; Nama: string; HargaNegosiasi: boolean };
    Harga: Harga[];
    IdPengguna: number;
};

const labelStatus = {
    Draf: { jenis: 'netral', teks: 'Draf' },
    MenungguTinjauan: { jenis: 'peringatan', teks: 'Menunggu tinjauan' },
    Terbit: { jenis: 'sukses', teks: 'Terbit' },
    Berakhir: { jenis: 'netral', teks: 'Berakhir' },
} as const;

/** Versi harga paket (P-04, BR-P04.1, BR-P04.5). Keuangan mengusulkan, Super Admin menyetujui. */
export default function HalamanHargaPaket({ Paket, Harga, IdPengguna }: PropsHargaPaket) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehAjukan = PunyaIzin(props.Pengguna, IzinPengelola.KatalogPaketAjukan);
    const bolehSetujui = PunyaIzin(props.Pengguna, IzinPengelola.KatalogPaketSetujui);
    const [sunting, AturSunting] = useState<Harga | 'baru' | null>(null);
    const [ditinjau, AturDitinjau] = useState<Harga | null>(null);
    const alamat = `/katalog/paket/${Paket.Uuid}/harga`;
    const Ajukan = (harga: Harga) => router.post(`${alamat}/${harga.Uuid}/ajukan`, {}, { preserveScroll: true });

    return (
        <TataLetakPengelola
            judul={`Harga ${Paket.Nama}`}
            aksi={
                bolehAjukan && !Paket.HargaNegosiasi && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Usulkan harga baru</Tombol>
                ) : null
            }
        >
            <TabKatalog />
            <Link href="/katalog/paket" className="text-label font-semibold text-brand underline">
                Kembali ke daftar paket
            </Link>
            <Pemberitahuan jenis="info" judul="Aturan harga paket">
                Harga baru hanya berlaku untuk tagihan berikutnya. Bila &quot;terapkan ke pelanggan lama&quot; tidak
                dicentang, langganan yang sudah berjalan tetap memakai harga lamanya. Harga terbit tidak bisa diubah;
                penyusun tidak bisa menyetujui usulannya sendiri.
            </Pemberitahuan>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
            {Paket.HargaNegosiasi ? (
                <Pemberitahuan jenis="info" judul="Harga negosiasi">
                    Paket ini tidak punya harga tetap; harga disepakati per tenant.
                </Pemberitahuan>
            ) : null}

            {sunting !== null ? (
                <FormHarga
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    alamat={alamat}
                    harga={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {ditinjau !== null ? (
                <FormTinjauHarga
                    key={ditinjau.Uuid}
                    alamat={alamat}
                    harga={ditinjau}
                    saatSelesai={() => AturDitinjau(null)}
                />
            ) : null}

            {Harga.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada harga">
                    Usulkan harga pertama agar paket bisa diaktifkan.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Versi harga paket {Paket.Nama}</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Per bulan
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Per tahun
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Berlaku
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pelanggan lama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Harga.map((harga) => {
                                const status = labelStatus[harga.Status];
                                const bisaTinjau =
                                    bolehSetujui &&
                                    harga.Status === 'MenungguTinjauan' &&
                                    harga.IdPengaju !== IdPengguna &&
                                    !harga.DaftarIdPenyusun.includes(IdPengguna) &&
                                    !harga.Persetujuan.some((item) => item.IdPeninjau === IdPengguna);

                                return (
                                    <tr key={harga.Uuid} className="border-b border-garis align-top last:border-b-0">
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {FormatRupiah(harga.HargaBulanan)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {FormatRupiah(harga.HargaTahunan)}
                                        </td>
                                        <td className="px-4 py-3 text-teks-sekunder">
                                            {FormatTanggal(harga.BerlakuMulai)} –{' '}
                                            {harga.BerlakuSampai ? FormatTanggal(harga.BerlakuSampai) : 'seterusnya'}
                                        </td>
                                        <td className="px-4 py-3 text-teks-sekunder">
                                            {harga.TerapkanKePelangganLama ? 'Ikut harga baru' : 'Tetap harga lama'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <LabelStatus jenis={status.jenis} teks={status.teks} />
                                            {harga.Persetujuan.map((item) => (
                                                <p key={item.IdPeninjau} className="text-keterangan text-teks-sekunder">
                                                    {item.Keputusan === 'Setuju' ? 'Disetujui' : 'Ditolak'}{' '}
                                                    {item.Peninjau}
                                                    {item.Catatan ? `: ${item.Catatan}` : ''}
                                                </p>
                                            ))}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {bolehAjukan && harga.Status === 'Draf' ? (
                                                    <>
                                                        <Tombol varian="sekunder" onClick={() => AturSunting(harga)}>
                                                            Ubah
                                                        </Tombol>
                                                        <Tombol onClick={() => Ajukan(harga)}>Ajukan harga</Tombol>
                                                    </>
                                                ) : null}
                                                {bisaTinjau ? (
                                                    <Tombol onClick={() => AturDitinjau(harga)}>Tinjau harga</Tombol>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakPengelola>
    );
}

function FormHarga({ alamat, harga, saatSelesai }: { alamat: string; harga: Harga | null; saatSelesai: () => void }) {
    const formulir = useForm({
        HargaBulanan: harga?.HargaBulanan.replace(/\.00$/, '') ?? '',
        HargaTahunan: harga?.HargaTahunan.replace(/\.00$/, '') ?? '',
        BerlakuMulai: harga?.BerlakuMulai ?? '',
        TerapkanKePelangganLama: harga?.TerapkanKePelangganLama ?? false,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (harga === null) {
            formulir.post(alamat, opsi);
        } else {
            formulir.put(`${alamat}/${harga.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-3"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-3">
                {harga === null ? 'Usulkan harga baru' : 'Ubah draf harga'}
            </h2>
            <BidangTeks
                label="Harga per bulan (Rp)"
                inputMode="decimal"
                keterangan="Tanpa titik ribuan, misal 199000."
                nilai={formulir.data.HargaBulanan}
                saatBerubah={(nilai) => formulir.setData('HargaBulanan', nilai)}
                galat={formulir.errors.HargaBulanan}
            />
            <BidangTeks
                label="Harga per tahun (Rp)"
                inputMode="decimal"
                nilai={formulir.data.HargaTahunan}
                saatBerubah={(nilai) => formulir.setData('HargaTahunan', nilai)}
                galat={formulir.errors.HargaTahunan}
            />
            <BidangTeks
                label="Berlaku mulai (TTTT-BB-HH)"
                kode
                nilai={formulir.data.BerlakuMulai}
                saatBerubah={(nilai) => formulir.setData('BerlakuMulai', nilai)}
                galat={formulir.errors.BerlakuMulai}
            />
            <div className="sm:col-span-3">
                <KotakCentang
                    label="Terapkan juga ke pelanggan lama (tanpa penguncian harga lama)"
                    nilai={formulir.data.TerapkanKePelangganLama}
                    saatBerubah={(nilai) => formulir.setData('TerapkanKePelangganLama', nilai)}
                />
            </div>
            <div className="flex gap-2 sm:col-span-3">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan draf harga
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

function FormTinjauHarga({ alamat, harga, saatSelesai }: { alamat: string; harga: Harga; saatSelesai: () => void }) {
    const formulir = useForm({ Keputusan: 'Setuju', Catatan: '' });

    const Kirim = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ ...data, Keputusan: keputusan }));
        formulir.post(`${alamat}/${harga.Uuid}/tinjau`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <section className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6">
            <h2 className="text-subjudul font-semibold text-teks-utama">
                Tinjau harga {FormatRupiah(harga.HargaBulanan)}/bulan mulai {FormatTanggal(harga.BerlakuMulai)}
            </h2>
            <p className="text-isi text-teks-sekunder">
                {harga.TerapkanKePelangganLama
                    ? 'Harga ini juga berlaku untuk pelanggan lama pada tagihan berikutnya.'
                    : 'Pelanggan lama tetap memakai harga lamanya.'}
            </p>
            <BidangTeks
                label="Catatan (wajib bila menolak)"
                nilai={formulir.data.Catatan}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
                maxLength={500}
            />
            <div className="flex gap-2">
                <Tombol memproses={formulir.processing} onClick={() => Kirim('Setuju')}>
                    Terbitkan harga
                </Tombol>
                <Tombol varian="bahaya" disabled={formulir.processing} onClick={() => Kirim('Tolak')}>
                    Tolak harga
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </section>
    );
}
