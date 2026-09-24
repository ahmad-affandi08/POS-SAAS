import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import {
    FormAktifkan,
    FormCabutOverride,
    FormCatatan,
    FormOverride,
    FormPenanda,
    FormPerpanjangTrial,
    FormTangguhkan,
} from '@/Komponen/Pengelola/Tenant/FormTindakan';
import { LabelPenanda, LabelStatusLangganan } from '@/Komponen/Pengelola/Tenant/LabelLangganan';
import { Card, CardContent, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Komponen/Ui/tabs';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';
import { labelBatas, type AturanTenant, type PilihanTenant, type Tampilan360 } from '@/Tipe/TenantPengelola';

type PropsTampil = { Tenant: Tampilan360; Pilihan: PilihanTenant; Aturan: AturanTenant };

type Tindakan = 'trial' | 'override' | 'tangguhkan' | 'aktifkan' | 'penanda' | { cabut: string; kunci: string } | null;

const labelAksi: Record<string, string> = {
    'tenant.trial.perpanjang': 'Perpanjang trial',
    'tenant.override.buat': 'Buat override',
    'tenant.override.cabut': 'Cabut override',
    'tenant.tangguhkan': 'Tangguhkan',
    'tenant.aktifkan': 'Aktifkan kembali',
    'tenant.catatan.tulis': 'Tulis catatan',
    'tenant.penanda.ubah': 'Ubah penanda',
};

const kelasKepala = 'text-label font-semibold text-teks-sekunder';

/** Tampilan 360° dasar tenant & tindakan pengelola (P-07). Semua tindakan tercatat di riwayat (BR-P07.3). */
export default function Tampil({ Tenant, Pilihan, Aturan }: PropsTampil) {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const [tindakan, AturTindakan] = useState<Tindakan>(null);
    const { Profil, Langganan } = Tenant;
    const Tutup = () => AturTindakan(null);
    const status = Langganan?.Status ?? null;

    const tombol: { kunci: Exclude<Tindakan, null | object>; label: string; boleh: boolean; varian?: 'bahaya' }[] = [
        {
            kunci: 'trial',
            label: 'Perpanjang trial',
            boleh:
                PunyaIzin(pengguna, IzinPengelola.TenantTrialPerpanjang) &&
                status === 'Trial' &&
                (Langganan?.SisaPerpanjanganTrial ?? 0) > 0,
        },
        {
            kunci: 'override',
            label: 'Override sementara',
            boleh: PunyaIzin(pengguna, IzinPengelola.TenantOverrideKelola),
        },
        {
            kunci: 'aktifkan',
            label: 'Aktifkan kembali',
            boleh: PunyaIzin(pengguna, IzinPengelola.TenantAktifkan) && Langganan?.BisaDiaktifkan === true,
        },
        { kunci: 'penanda', label: 'Ubah penanda', boleh: PunyaIzin(pengguna, IzinPengelola.TenantPenandaUbah) },
        {
            kunci: 'tangguhkan',
            label: 'Tangguhkan',
            varian: 'bahaya',
            boleh:
                PunyaIzin(pengguna, IzinPengelola.TenantTangguhkan) &&
                status !== null &&
                !['Ditangguhkan', 'Berhenti'].includes(status),
        },
    ];
    const tombolTerlihat = tombol.filter((item) => item.boleh);

    return (
        <TataLetakPengelola
            judul={Profil.Nama}
            aksi={
                <Link href="/tenant" className="text-label font-semibold text-brand underline">
                    Kembali ke daftar tenant
                </Link>
            }
        >
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-label text-teks-sekunder">{Profil.Slug}</span>
                <LabelStatusLangganan status={status} />
                <LabelPenanda penanda={Profil.Penanda} />
            </div>

            {status === 'Ditangguhkan' ? (
                <Pemberitahuan jenis="peringatan" judul="Tenant ditangguhkan">
                    POS terkunci; Owner hanya bisa masuk, melihat laporan, mengekspor data, dan membayar. Alasan ada di
                    riwayat tindakan.
                    {Langganan?.StatusSebelumDitangguhkan === null ? (
                        <>
                            {' '}
                            Penangguhan ini karena tagihan belum dibayar: tenant aktif kembali otomatis saat
                            pembayarannya diterima di menu Tagihan.
                        </>
                    ) : null}
                </Pemberitahuan>
            ) : null}

            {tombolTerlihat.length > 0 ? (
                <div className="flex flex-wrap gap-2" role="group" aria-label="Tindakan pada tenant">
                    {tombolTerlihat.map((item) => (
                        <Tombol
                            key={item.kunci}
                            varian={item.varian ?? 'sekunder'}
                            onClick={() => AturTindakan(item.kunci)}
                        >
                            {item.label}
                        </Tombol>
                    ))}
                </div>
            ) : null}

            {tindakan === 'trial' && Langganan ? (
                <FormPerpanjangTrial uuid={Profil.Uuid} langganan={Langganan} aturan={Aturan} saatSelesai={Tutup} />
            ) : null}
            {tindakan === 'override' ? (
                <FormOverride
                    uuid={Profil.Uuid}
                    pilihanFitur={Pilihan.Fitur}
                    kolomBatas={Pilihan.KolomBatas}
                    aturan={Aturan}
                    saatSelesai={Tutup}
                />
            ) : null}
            {tindakan === 'tangguhkan' ? (
                <FormTangguhkan uuid={Profil.Uuid} pilihanKategori={Pilihan.KategoriPenangguhan} saatSelesai={Tutup} />
            ) : null}
            {tindakan === 'aktifkan' ? (
                <FormAktifkan
                    uuid={Profil.Uuid}
                    statusTujuan={Langganan?.StatusSetelahDiaktifkan ?? null}
                    saatSelesai={Tutup}
                />
            ) : null}
            {tindakan === 'penanda' ? (
                <FormPenanda
                    uuid={Profil.Uuid}
                    penandaSekarang={Profil.Penanda}
                    pilihanPenanda={Pilihan.Penanda}
                    saatSelesai={Tutup}
                />
            ) : null}
            {tindakan !== null && typeof tindakan === 'object' ? (
                <FormCabutOverride
                    uuid={Profil.Uuid}
                    uuidOverride={tindakan.cabut}
                    kunci={tindakan.kunci}
                    saatSelesai={Tutup}
                />
            ) : null}

            <Tabs defaultValue="ringkasan" className="gap-4">
                <div className="overflow-x-auto">
                    <TabsList aria-label="Bagian tampilan tenant" className="text-label">
                        <TabsTrigger value="ringkasan">Ringkasan</TabsTrigger>
                        <TabsTrigger value="override">Override & trial ({Tenant.Override.length})</TabsTrigger>
                        <TabsTrigger value="catatan">Catatan & riwayat</TabsTrigger>
                        <TabsTrigger value="menyusul">Belum tersedia</TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="ringkasan" className="grid gap-4 lg:grid-cols-2">
                    <Panel judul="Profil usaha">
                        <DaftarNilai
                            isi={[
                                ['Nama usaha', Profil.Nama],
                                ['Slug', <span className="font-mono">{Profil.Slug}</span>],
                                [
                                    'NPWP',
                                    Profil.Npwp ? <span className="font-mono">{Profil.Npwp}</span> : 'Belum diisi',
                                ],
                                ['PKP', Profil.Pkp ? 'Ya' : 'Tidak'],
                                ['Zona waktu', Profil.ZonaWaktu],
                                [
                                    'Sektor',
                                    Profil.TemplateSektor.length > 0
                                        ? Profil.TemplateSektor.join(', ')
                                        : 'Belum memilih template sektor',
                                ],
                                ['Terdaftar', FormatTanggalWaktu(Profil.DibuatPada)],
                            ]}
                        />
                    </Panel>

                    <Panel judul="Paket & langganan">
                        {Langganan === null ? (
                            <p className="text-isi text-teks-sekunder">Tenant ini belum punya langganan.</p>
                        ) : (
                            <DaftarNilai
                                isi={[
                                    [
                                        'Paket',
                                        <>
                                            {Langganan.NamaPaket}{' '}
                                            <span className="font-mono">({Langganan.KodePaket})</span>
                                        </>,
                                    ],
                                    ['Status', Langganan.Status],
                                    ['Trial berakhir', FormatTanggalWaktu(Langganan.TrialBerakhirPada)],
                                    [
                                        'Perpanjangan trial',
                                        `${Langganan.PerpanjanganTrial} dari ${Aturan.MaksKaliTrial} kali`,
                                    ],
                                    [
                                        'Periode',
                                        `${FormatTanggalWaktu(Langganan.PeriodeMulai)} – ${FormatTanggalWaktu(Langganan.PeriodeSelesai)}`,
                                    ],
                                    ['Siklus tagihan', Langganan.SiklusTagihan],
                                    ...(Langganan.StatusSebelumDitangguhkan
                                        ? ([['Status sebelum ditangguhkan', Langganan.StatusSebelumDitangguhkan]] as [
                                              string,
                                              ReactNode,
                                          ][])
                                        : []),
                                ]}
                            />
                        )}
                    </Panel>

                    <Panel judul="Pemakaian vs batas">
                        <Table className="text-isi">
                            <TableCaption className="sr-only">
                                Pemakaian dibanding batas efektif (paket + override)
                            </TableCaption>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead scope="col" className={`${kelasKepala} px-0`}>
                                        Sumber daya
                                    </TableHead>
                                    <TableHead scope="col" className={`${kelasKepala} px-0 text-right`}>
                                        Terpakai
                                    </TableHead>
                                    <TableHead scope="col" className={`${kelasKepala} px-0 text-right`}>
                                        Batas
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Tenant.Pemakaian.map((baris) => (
                                    <TableRow key={baris.Label}>
                                        <TableCell className="px-0 whitespace-normal">{baris.Label}</TableCell>
                                        <TableCell className="px-0 text-right tabular-nums">{baris.Pakai}</TableCell>
                                        <TableCell className="px-0 text-right tabular-nums">
                                            {baris.Batas === null ? 'Tak terbatas' : baris.Batas}
                                            {baris.Batas !== null && baris.Pakai > baris.Batas ? (
                                                <span className="ml-2">
                                                    <LabelStatus jenis="peringatan" teks="Melebihi batas" />
                                                </span>
                                            ) : null}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Panel>

                    <Panel judul="Outlet & gudang">
                        <p className="text-isi text-teks-sekunder">
                            {Tenant.Organisasi.Outlet.length} outlet · {Tenant.Organisasi.JumlahGudang} gudang ·{' '}
                            {Tenant.Organisasi.JumlahMerek} merek
                        </p>
                        {Tenant.Organisasi.Outlet.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">Belum ada outlet.</p>
                        ) : (
                            <ul className="flex flex-col gap-1 text-isi">
                                {Tenant.Organisasi.Outlet.map((outlet) => (
                                    <li key={outlet.Kode}>
                                        <span className="font-mono text-label">{outlet.Kode}</span> {outlet.Nama}
                                        {outlet.TemplateSektor ? (
                                            <span className="text-teks-sekunder"> · {outlet.TemplateSektor}</span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel judul="Anggota">
                        {Tenant.Anggota.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">Belum ada anggota.</p>
                        ) : (
                            <ul className="flex flex-col gap-3 text-isi">
                                {Tenant.Anggota.map((anggota) => (
                                    <li key={anggota.Email} className="flex flex-col gap-1">
                                        <span className="flex flex-wrap items-center gap-2 font-semibold text-teks-utama">
                                            {anggota.Nama}
                                            {anggota.Pemilik ? <LabelStatus jenis="netral" teks="Owner" /> : null}
                                            {anggota.Status !== 'Aktif' ? (
                                                <LabelStatus jenis="netral" teks={anggota.Status} />
                                            ) : null}
                                        </span>
                                        <span className="break-all text-teks-sekunder">
                                            {anggota.Email} ·{' '}
                                            {anggota.EmailTerverifikasi
                                                ? 'email terverifikasi'
                                                : 'email belum diverifikasi'}
                                        </span>
                                        {anggota.NoHp ? (
                                            <span className="font-mono text-label text-teks-sekunder">
                                                {anggota.NoHp}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel judul="Persetujuan legal">
                        {Tenant.PersetujuanLegal.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">
                                Belum ada persetujuan dokumen legal yang tercatat.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-1 text-isi">
                                {Tenant.PersetujuanLegal.map((baris) => (
                                    <li key={`${baris.Jenis}-${String(baris.Versi)}-${baris.DisetujuiPada}`}>
                                        {baris.Jenis} versi {baris.Versi ?? '—'}
                                        <span className="text-teks-sekunder">
                                            {' '}
                                            · {baris.Pengguna} · {FormatTanggalWaktu(baris.DisetujuiPada)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </TabsContent>

                <TabsContent value="override">
                    <Panel judul="Override & perpanjangan trial">
                        {Tenant.Override.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">
                                Belum pernah ada override atau perpanjangan trial.
                            </p>
                        ) : (
                            <Table className="min-w-[720px] text-isi">
                                <TableCaption className="sr-only">Override tenant, terbaru di atas</TableCaption>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead scope="col" className={`${kelasKepala} pl-0`}>
                                            Jenis
                                        </TableHead>
                                        <TableHead scope="col" className={kelasKepala}>
                                            Isi
                                        </TableHead>
                                        <TableHead scope="col" className={kelasKepala}>
                                            Berakhir
                                        </TableHead>
                                        <TableHead scope="col" className={kelasKepala}>
                                            Alasan
                                        </TableHead>
                                        <TableHead scope="col" className={`${kelasKepala} pr-0`}>
                                            <span className="sr-only">Aksi</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {Tenant.Override.map((baris) => (
                                        <TableRow key={baris.Uuid} className="align-top">
                                            <TableCell className="pl-0">
                                                <span className="block">{baris.Jenis}</span>
                                                <LabelStatus
                                                    jenis={baris.Aktif ? 'sukses' : 'netral'}
                                                    teks={baris.Aktif ? 'Berlaku' : 'Berakhir'}
                                                />
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                {baris.Jenis === 'Batas' ? (
                                                    `${labelBatas[baris.Kunci] ?? baris.Kunci}: ${baris.Nilai ?? '—'}`
                                                ) : baris.Jenis === 'Trial' ? (
                                                    `+${baris.Nilai ?? '?'} hari`
                                                ) : (
                                                    <span className="font-mono text-label">{baris.Kunci}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-teks-sekunder">
                                                {FormatTanggalWaktu(baris.BerakhirPada)}
                                            </TableCell>
                                            <TableCell className="whitespace-normal text-teks-sekunder">
                                                {baris.Alasan}
                                                <span className="block text-keterangan">
                                                    {baris.DibuatOleh} · {FormatTanggalWaktu(baris.DibuatPada)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="pr-0 text-right">
                                                {baris.Aktif &&
                                                baris.Jenis !== 'Trial' &&
                                                PunyaIzin(pengguna, IzinPengelola.TenantOverrideKelola) ? (
                                                    <Tombol
                                                        varian="sekunder"
                                                        onClick={() =>
                                                            AturTindakan({ cabut: baris.Uuid, kunci: baris.Kunci })
                                                        }
                                                    >
                                                        Cabut
                                                    </Tombol>
                                                ) : null}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </Panel>
                </TabsContent>

                <TabsContent value="catatan" className="grid gap-4 lg:grid-cols-2">
                    <Panel judul="Catatan internal">
                        {PunyaIzin(pengguna, IzinPengelola.TenantCatatanTulis) ? (
                            <FormCatatan uuid={Profil.Uuid} />
                        ) : null}
                        {Tenant.Catatan.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">Belum ada catatan internal.</p>
                        ) : (
                            <ul className="flex flex-col gap-3 text-isi">
                                {Tenant.Catatan.map((catatan) => (
                                    <li key={catatan.Uuid} className="border-t border-garis pt-3">
                                        <p className="whitespace-pre-line text-teks-utama">{catatan.Isi}</p>
                                        <p className="text-keterangan text-teks-sekunder">
                                            {catatan.Penulis} · {FormatTanggalWaktu(catatan.DibuatPada)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel judul="Riwayat tindakan">
                        {Tenant.Riwayat.length === 0 ? (
                            <p className="text-isi text-teks-sekunder">Belum ada tindakan pengelola pada tenant ini.</p>
                        ) : (
                            <ol className="flex flex-col gap-3 text-isi">
                                {Tenant.Riwayat.map((log) => (
                                    <li key={log.Id} className="border-t border-garis pt-3">
                                        <p className="font-semibold text-teks-utama">
                                            {labelAksi[log.Aksi] ?? log.Aksi}{' '}
                                            <span className="font-mono text-keterangan font-normal text-teks-sekunder">
                                                {log.Aksi}
                                            </span>
                                        </p>
                                        {log.Alasan ? (
                                            <p className="text-teks-sekunder">Alasan: {log.Alasan}</p>
                                        ) : null}
                                        {log.NilaiBaru ? (
                                            <p className="break-all text-keterangan text-teks-sekunder">
                                                <code className="font-mono">{JSON.stringify(log.NilaiBaru)}</code>
                                            </p>
                                        ) : null}
                                        <p className="text-keterangan text-teks-sekunder">
                                            {log.Pelaku} · {FormatTanggalWaktu(log.DibuatPada)}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Panel>
                </TabsContent>

                <TabsContent value="menyusul">
                    <Panel judul="Belum tersedia">
                        <ul className="grid gap-3 text-isi sm:grid-cols-2">
                            <ModulMenyusul judul="Tagihan & pembayaran" modul="Billing & Dunning Platform (P-08)" />
                            <ModulMenyusul
                                judul="Tiket & riwayat akses dukungan"
                                modul="Dukungan & Akses Dukungan (P-09)"
                            />
                            <ModulMenyusul
                                judul="Perangkat (platform, versi aplikasi, outbox tertunda)"
                                modul="aktivasi perangkat POS"
                            />
                            <ModulMenyusul judul="Mitra perujuk" modul="Mitra, Reseller & Referral (P-12)" />
                            <ModulMenyusul judul="Skor kesehatan" modul="skor kesehatan tenant (fase berikutnya)" />
                            <ModulMenyusul
                                judul="Permintaan penghapusan data (UU PDP)"
                                modul="alur penghapusan data (fase berikutnya)"
                            />
                        </ul>
                    </Panel>
                </TabsContent>
            </Tabs>
        </TataLetakPengelola>
    );
}

function Panel({ judul, children }: { judul: string; children: ReactNode }) {
    return (
        <Card className="gap-3 py-4">
            <CardHeader className="px-4">
                <CardTitle>
                    <h2 className="text-subjudul font-semibold text-teks-utama">{judul}</h2>
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3 px-4">{children}</CardContent>
        </Card>
    );
}

function DaftarNilai({ isi }: { isi: [string, ReactNode][] }) {
    return (
        <dl className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-3 gap-y-2 text-isi">
            {isi.map(([label, nilai]) => (
                <div key={label} className="contents">
                    <dt className="text-teks-sekunder">{label}</dt>
                    <dd className="break-words text-teks-utama">{nilai}</dd>
                </div>
            ))}
        </dl>
    );
}

function ModulMenyusul({ judul, modul }: { judul: string; modul: string }) {
    return (
        <li className="rounded-kontrol border border-garis px-3 py-2">
            <p className="font-semibold text-teks-utama">{judul}</p>
            <p className="text-keterangan text-teks-sekunder">Tersedia setelah modul {modul}.</p>
        </li>
    );
}
