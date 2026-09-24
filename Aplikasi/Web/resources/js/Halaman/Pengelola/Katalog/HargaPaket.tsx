import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import BidangTanggal from '@/Komponen/Pengelola/BidangTanggal';
import DialogFormulir from '@/Komponen/Pengelola/DialogFormulir';
import DialogKonfirmasi from '@/Komponen/Pengelola/DialogKonfirmasi';
import KeadaanKosong from '@/Komponen/Pengelola/KeadaanKosong';
import PanelTabel from '@/Komponen/Pengelola/PanelTabel';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import { Button } from '@/Komponen/Ui/button';
import { DialogFooter } from '@/Komponen/Ui/dialog';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
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
            <Button asChild variant="link" className="h-auto self-start px-0 text-label font-semibold">
                <Link href="/katalog/paket">Kembali ke daftar paket</Link>
            </Button>
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
                <KeadaanKosong judul="Belum ada harga">Usulkan harga pertama agar paket bisa diaktifkan.</KeadaanKosong>
            ) : (
                <PanelTabel keterangan={`Versi harga paket ${Paket.Nama}`}>
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col" className="text-right">
                                Per bulan
                            </TableHead>
                            <TableHead scope="col" className="text-right">
                                Per tahun
                            </TableHead>
                            <TableHead scope="col">Berlaku</TableHead>
                            <TableHead scope="col">Pelanggan lama</TableHead>
                            <TableHead scope="col">Status</TableHead>
                            <TableHead scope="col">
                                <span className="sr-only">Aksi</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Harga.map((harga) => {
                            const status = labelStatus[harga.Status];
                            const bisaTinjau =
                                bolehSetujui &&
                                harga.Status === 'MenungguTinjauan' &&
                                harga.IdPengaju !== IdPengguna &&
                                !harga.DaftarIdPenyusun.includes(IdPengguna) &&
                                !harga.Persetujuan.some((item) => item.IdPeninjau === IdPengguna);

                            return (
                                <TableRow key={harga.Uuid}>
                                    <TableCell className="text-right tabular-nums">
                                        {FormatRupiah(harga.HargaBulanan)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {FormatRupiah(harga.HargaTahunan)}
                                    </TableCell>
                                    <TableCell className="text-teks-sekunder">
                                        {FormatTanggal(harga.BerlakuMulai)} –{' '}
                                        {harga.BerlakuSampai ? FormatTanggal(harga.BerlakuSampai) : 'seterusnya'}
                                    </TableCell>
                                    <TableCell className="text-teks-sekunder">
                                        {harga.TerapkanKePelangganLama ? 'Ikut harga baru' : 'Tetap harga lama'}
                                    </TableCell>
                                    <TableCell>
                                        <LabelStatus jenis={status.jenis} teks={status.teks} />
                                        {harga.Persetujuan.map((item) => (
                                            <p key={item.IdPeninjau} className="text-keterangan text-teks-sekunder">
                                                {item.Keputusan === 'Setuju' ? 'Disetujui' : 'Ditolak'} {item.Peninjau}
                                                {item.Catatan ? `: ${item.Catatan}` : ''}
                                            </p>
                                        ))}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            {bolehAjukan && harga.Status === 'Draf' ? (
                                                <>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => AturSunting(harga)}
                                                    >
                                                        Ubah
                                                    </Button>
                                                    <Button size="sm" onClick={() => Ajukan(harga)}>
                                                        Ajukan harga
                                                    </Button>
                                                </>
                                            ) : null}
                                            {bisaTinjau ? (
                                                <Button size="sm" onClick={() => AturDitinjau(harga)}>
                                                    Tinjau harga
                                                </Button>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </PanelTabel>
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
        <DialogFormulir
            judul={harga === null ? 'Usulkan harga baru' : 'Ubah draf harga'}
            saatTutup={saatSelesai}
            lebar="lebar"
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
        >
            <form onSubmit={Kirim} className="grid gap-4 sm:grid-cols-3" noValidate>
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
                <BidangTanggal
                    label="Berlaku mulai (TTTT-BB-HH)"
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
                <DialogFooter className="sm:col-span-3 sm:justify-start">
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan draf harga
                    </Tombol>
                    <Tombol varian="sekunder" onClick={saatSelesai}>
                        Batal
                    </Tombol>
                </DialogFooter>
            </form>
        </DialogFormulir>
    );
}

function FormTinjauHarga({ alamat, harga, saatSelesai }: { alamat: string; harga: Harga; saatSelesai: () => void }) {
    const formulir = useForm({ Keputusan: 'Setuju', Catatan: '' });

    const Kirim = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ ...data, Keputusan: keputusan }));
        formulir.post(`${alamat}/${harga.Uuid}/tinjau`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <DialogKonfirmasi
            judul={`Tinjau harga ${FormatRupiah(harga.HargaBulanan)}/bulan mulai ${FormatTanggal(harga.BerlakuMulai)}`}
            deskripsi={
                harga.TerapkanKePelangganLama
                    ? 'Harga ini juga berlaku untuk pelanggan lama pada tagihan berikutnya.'
                    : 'Pelanggan lama tetap memakai harga lamanya.'
            }
            saatTutup={saatSelesai}
            galatUmum={(formulir.errors as Record<string, string | undefined>).Umum}
            aksi={
                <>
                    <Tombol memproses={formulir.processing} onClick={() => Kirim('Setuju')}>
                        Terbitkan harga
                    </Tombol>
                    <Tombol varian="bahaya" disabled={formulir.processing} onClick={() => Kirim('Tolak')}>
                        Tolak harga
                    </Tombol>
                </>
            }
        >
            <BidangTeks
                label="Catatan (wajib bila menolak)"
                nilai={formulir.data.Catatan}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
                maxLength={500}
            />
        </DialogKonfirmasi>
    );
}
