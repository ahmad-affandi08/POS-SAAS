import { useForm } from '@inertiajs/react';
import { useId, type FormEvent, type ReactNode } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import BidangTeksPanjang from '@/Komponen/Pengelola/Tenant/BidangTeksPanjang';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { Pilihan } from '@/Tipe/Pengelola';
import { labelBatas, type AturanTenant, type LanggananTenant } from '@/Tipe/TenantPengelola';

type PropsKerangka = {
    judul: string;
    keterangan: ReactNode;
    labelKirim: string;
    varianKirim?: 'utama' | 'bahaya';
    memproses: boolean;
    galatUmum: string | undefined;
    saatKirim: (peristiwa: FormEvent) => void;
    saatBatal: () => void;
    children: ReactNode;
};

/** Kerangka formulir tindakan: judul, penjelasan akibat, galat aturan bisnis, tombol kirim & batal. */
function KerangkaForm({
    judul,
    keterangan,
    labelKirim,
    varianKirim = 'utama',
    memproses,
    galatUmum,
    saatKirim,
    saatBatal,
    children,
}: PropsKerangka) {
    return (
        <form
            onSubmit={saatKirim}
            noValidate
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6"
        >
            <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
            <div className="text-isi text-teks-sekunder">{keterangan}</div>
            {galatUmum ? <Pemberitahuan jenis="bahaya">{galatUmum}</Pemberitahuan> : null}
            {children}
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" varian={varianKirim} memproses={memproses}>
                    {labelKirim}
                </Tombol>
                <Tombol varian="sekunder" onClick={saatBatal}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

/** Pelanggaran aturan bisnis dari server dikirim di kunci `Umum` (PelanggaranAturanBisnis). */
function AmbilGalatUmum(galat: Partial<Record<string, string>>): string | undefined {
    return galat.Umum;
}

type PropsDasar = { uuid: string; saatSelesai: () => void };

export function FormPerpanjangTrial({
    uuid,
    langganan,
    aturan,
    saatSelesai,
}: PropsDasar & { langganan: LanggananTenant; aturan: AturanTenant }) {
    const formulir = useForm({ Hari: '7', Alasan: '' });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tenant/${uuid}/trial/perpanjang`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <KerangkaForm
            judul="Perpanjang trial"
            keterangan={
                <p>
                    Trial sekarang berakhir {FormatTanggalWaktu(langganan.TrialBerakhirPada)}. Maksimal{' '}
                    {aturan.MaksHariTrial} hari per perpanjangan; sisa {langganan.SisaPerpanjanganTrial} dari{' '}
                    {aturan.MaksKaliTrial} kali.
                </p>
            }
            labelKirim="Perpanjang trial"
            memproses={formulir.processing}
            galatUmum={AmbilGalatUmum(formulir.errors)}
            saatKirim={Kirim}
            saatBatal={saatSelesai}
        >
            <BidangTeks
                label="Tambahan hari"
                inputMode="numeric"
                nilai={formulir.data.Hari}
                saatBerubah={(nilai) => formulir.setData('Hari', nilai)}
                galat={formulir.errors.Hari}
            />
            <BidangTeksPanjang
                label="Alasan"
                keterangan="Misal: Owner masih menunggu printer struk datang, butuh uji coba 1 minggu lagi."
                nilai={formulir.data.Alasan}
                maksimal={500}
                saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                galat={formulir.errors.Alasan}
            />
        </KerangkaForm>
    );
}

export function FormOverride({
    uuid,
    pilihanFitur,
    kolomBatas,
    aturan,
    saatSelesai,
}: PropsDasar & { pilihanFitur: Pilihan[]; kolomBatas: string[]; aturan: AturanTenant }) {
    const idTanggal = useId();
    const formulir = useForm({ Jenis: 'Batas', Kunci: kolomBatas[0] ?? '', Nilai: '', BerakhirPada: '', Alasan: '' });
    const opsiKunci =
        formulir.data.Jenis === 'Batas'
            ? kolomBatas.map((kolom) => ({ Nilai: kolom, Label: labelBatas[kolom] ?? kolom }))
            : pilihanFitur;
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tenant/${uuid}/override`, { preserveScroll: true, onSuccess: saatSelesai });
    };
    const GantiJenis = (jenis: string) => {
        formulir.setData({
            ...formulir.data,
            Jenis: jenis,
            Kunci: jenis === 'Batas' ? (kolomBatas[0] ?? '') : (pilihanFitur[0]?.Nilai ?? ''),
            Nilai: '',
        });
    };

    return (
        <KerangkaForm
            judul="Override sementara"
            keterangan={
                <p>
                    Menimpa batas paket atau membuka fitur di luar paket sampai tanggal berakhir (paling lama{' '}
                    {aturan.MaksHariOverride} hari), lalu kembali otomatis ke isi paket.
                </p>
            }
            labelKirim="Simpan override"
            memproses={formulir.processing}
            galatUmum={AmbilGalatUmum(formulir.errors)}
            saatKirim={Kirim}
            saatBatal={saatSelesai}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <BidangPilihan
                    label="Jenis"
                    nilai={formulir.data.Jenis}
                    opsi={[
                        { Nilai: 'Batas', Label: 'Batas (angka)' },
                        { Nilai: 'Fitur', Label: 'Fitur' },
                    ]}
                    saatBerubah={GantiJenis}
                    galat={formulir.errors.Jenis}
                />
                {opsiKunci.length === 0 ? (
                    <p className="text-isi text-teks-sekunder">Katalog fitur masih kosong. Isi dulu di menu Katalog.</p>
                ) : (
                    <BidangPilihan
                        label={formulir.data.Jenis === 'Batas' ? 'Batas' : 'Fitur'}
                        nilai={formulir.data.Kunci}
                        opsi={opsiKunci}
                        saatBerubah={(nilai) => formulir.setData('Kunci', nilai)}
                        galat={formulir.errors.Kunci}
                    />
                )}
                {formulir.data.Jenis === 'Batas' ? (
                    <BidangTeks
                        label="Batas selama override"
                        inputMode="numeric"
                        nilai={formulir.data.Nilai}
                        saatBerubah={(nilai) => formulir.setData('Nilai', nilai)}
                        galat={formulir.errors.Nilai}
                    />
                ) : null}
                <div className="flex flex-col gap-1">
                    <label htmlFor={idTanggal} className="text-label font-semibold text-teks-utama">
                        Berlaku sampai (akhir hari, WIB)
                    </label>
                    <input
                        id={idTanggal}
                        type="date"
                        value={formulir.data.BerakhirPada}
                        onChange={(peristiwa) => formulir.setData('BerakhirPada', peristiwa.target.value)}
                        aria-invalid={formulir.errors.BerakhirPada ? true : undefined}
                        className={`h-10 rounded-kontrol border bg-permukaan px-3 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                            formulir.errors.BerakhirPada ? 'border-bahaya' : 'border-garis-input'
                        }`}
                    />
                    {formulir.errors.BerakhirPada ? (
                        <p className="text-keterangan font-semibold text-bahaya">{formulir.errors.BerakhirPada}</p>
                    ) : null}
                </div>
            </div>
            <BidangTeksPanjang
                label="Alasan"
                keterangan="Misal: pembukaan cabang ke-3 sambil menunggu proses upgrade paket."
                nilai={formulir.data.Alasan}
                maksimal={500}
                saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                galat={formulir.errors.Alasan}
            />
        </KerangkaForm>
    );
}

export function FormTangguhkan({ uuid, pilihanKategori, saatSelesai }: PropsDasar & { pilihanKategori: Pilihan[] }) {
    const formulir = useForm({ Kategori: pilihanKategori[0]?.Nilai ?? '', Catatan: '' });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tenant/${uuid}/tangguhkan`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <KerangkaForm
            judul="Tangguhkan tenant"
            keterangan={
                <p>
                    Owner hanya bisa masuk, melihat laporan, mengekspor data, dan membayar tagihan; aplikasi kasir
                    terkunci. Tidak ada data yang dihapus. Owner menerima email berisi kategori alasan, bukan catatan
                    internal.
                </p>
            }
            labelKirim="Tangguhkan tenant"
            varianKirim="bahaya"
            memproses={formulir.processing}
            galatUmum={AmbilGalatUmum(formulir.errors)}
            saatKirim={Kirim}
            saatBatal={saatSelesai}
        >
            <BidangPilihan
                label="Kategori alasan"
                nilai={formulir.data.Kategori}
                opsi={pilihanKategori}
                saatBerubah={(nilai) => formulir.setData('Kategori', nilai)}
                galat={formulir.errors.Kategori}
            />
            <BidangTeksPanjang
                label="Catatan internal"
                keterangan="Apa yang terjadi dan dasar keputusannya, misal nomor surat permintaan hukum."
                nilai={formulir.data.Catatan}
                maksimal={450}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
            />
        </KerangkaForm>
    );
}

export function FormAktifkan({ uuid, statusTujuan, saatSelesai }: PropsDasar & { statusTujuan: string | null }) {
    const formulir = useForm({ Alasan: '' });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tenant/${uuid}/aktifkan`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <KerangkaForm
            judul="Aktifkan kembali"
            keterangan={
                statusTujuan === 'Ditangguhkan' ? (
                    <p>
                        Penangguhan manual dicabut, tetapi tenant <strong>tetap ditangguhkan</strong> karena tagihannya
                        sudah lewat masa tenggang. Tenant aktif kembali saat pembayarannya diterima. Tulis keputusan
                        tertulisnya, misal hasil investigasi.
                    </p>
                ) : (
                    <p>
                        Status langganan kembali menjadi <strong>{statusTujuan ?? 'Aktif'}</strong>. Tulis keputusan
                        tertulisnya, misal tagihan sudah lunas atau hasil investigasi.
                    </p>
                )
            }
            labelKirim="Aktifkan kembali"
            memproses={formulir.processing}
            galatUmum={AmbilGalatUmum(formulir.errors)}
            saatKirim={Kirim}
            saatBatal={saatSelesai}
        >
            <BidangTeksPanjang
                label="Keputusan tertulis"
                nilai={formulir.data.Alasan}
                maksimal={500}
                saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                galat={formulir.errors.Alasan}
            />
        </KerangkaForm>
    );
}

export function FormPenanda({
    uuid,
    penandaSekarang,
    pilihanPenanda,
    saatSelesai,
}: PropsDasar & { penandaSekarang: string | null; pilihanPenanda: Pilihan[] }) {
    const formulir = useForm({ Penanda: penandaSekarang ?? '', Alasan: '' });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(`/tenant/${uuid}/penanda`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <KerangkaForm
            judul="Ubah penanda"
            keterangan={<p>Tenant Uji, Demo, dan Internal dikecualikan dari metrik bisnis dan tagihan.</p>}
            labelKirim="Simpan penanda"
            memproses={formulir.processing}
            galatUmum={AmbilGalatUmum(formulir.errors)}
            saatKirim={Kirim}
            saatBatal={saatSelesai}
        >
            <BidangPilihan
                label="Penanda"
                nilai={formulir.data.Penanda}
                opsi={pilihanPenanda}
                kosong="Tanpa penanda (tenant biasa)"
                saatBerubah={(nilai) => formulir.setData('Penanda', nilai)}
                galat={formulir.errors.Penanda}
            />
            <BidangTeksPanjang
                label="Alasan"
                nilai={formulir.data.Alasan}
                maksimal={500}
                saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                galat={formulir.errors.Alasan}
            />
        </KerangkaForm>
    );
}

export function FormCabutOverride({
    uuid,
    uuidOverride,
    kunci,
    saatSelesai,
}: PropsDasar & { uuidOverride: string; kunci: string }) {
    const formulir = useForm({ Alasan: '' });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tenant/${uuid}/override/${uuidOverride}/cabut`, {
            preserveScroll: true,
            onSuccess: saatSelesai,
        });
    };

    return (
        <KerangkaForm
            judul={`Cabut override ${kunci}`}
            keterangan={<p>Override berakhir sekarang dan tenant kembali memakai isi paketnya.</p>}
            labelKirim="Cabut override"
            varianKirim="bahaya"
            memproses={formulir.processing}
            galatUmum={AmbilGalatUmum(formulir.errors)}
            saatKirim={Kirim}
            saatBatal={saatSelesai}
        >
            <BidangTeksPanjang
                label="Alasan"
                nilai={formulir.data.Alasan}
                maksimal={500}
                saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                galat={formulir.errors.Alasan}
            />
        </KerangkaForm>
    );
}

export function FormCatatan({ uuid }: { uuid: string }) {
    const formulir = useForm({ Isi: '' });
    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(`/tenant/${uuid}/catatan`, { preserveScroll: true, onSuccess: () => formulir.reset() });
    };

    return (
        <form onSubmit={Kirim} noValidate className="flex flex-col gap-3">
            <BidangTeksPanjang
                label="Catatan baru"
                keterangan="Hanya terlihat oleh tim internal. Catatan tidak bisa diubah atau dihapus."
                nilai={formulir.data.Isi}
                maksimal={2000}
                saatBerubah={(nilai) => formulir.setData('Isi', nilai)}
                galat={formulir.errors.Isi ?? AmbilGalatUmum(formulir.errors)}
            />
            <div>
                <Tombol type="submit" varian="sekunder" memproses={formulir.processing}>
                    Simpan catatan
                </Tombol>
            </div>
        </form>
    );
}
