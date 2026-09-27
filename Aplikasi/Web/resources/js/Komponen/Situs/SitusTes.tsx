import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import TataLetakSitus from '@/TataLetak/TataLetakSitus';
import type { BagianSitus, DataSitus } from '@/Tipe/Situs';

import RenderBagian, { HitungIrama } from './Bagian/RenderBagian';
import { CekTautanHalamanSitus } from './TautanSitus';
import TeksKaya from './TeksKaya';

let situs: DataSitus;

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...sisa }: { href: string; children: ReactNode }) => (
        <a href={href} data-inertia="ya" {...sisa}>
            {children}
        </a>
    ),
    usePage: () => ({ props: { Situs: situs }, url: '/harga' }),
}));

function BuatSitus(tambahan: Partial<DataSitus> = {}): DataSitus {
    return {
        NamaSitus: 'PAYOU',
        Slogan: 'Smart Choice Your Business Partner',
        Logo: null,
        Menu: [
            { Label: 'Fitur', Tautan: '/fitur' },
            { Label: 'Harga', Tautan: '/harga' },
        ],
        MenuKaki: [
            { Judul: 'Perusahaan', Tautan: [{ Label: 'Kebijakan privasi', Tautan: '/legal/kebijakan-privasi' }] },
        ],
        TeksKaki: 'Kasir untuk usaha Indonesia.',
        Kontak: {
            WhatsApp: '0812-3456-7890',
            TautanWhatsApp: 'https://wa.me/6281234567890',
            Email: 'halo@payou.id',
            Telepon: null,
            Alamat: null,
            JamLayanan: null,
        },
        MediaSosial: { Instagram: 'https://instagram.com/payou' },
        Pengumuman: { Teks: 'Diskon 17 Agustus', Tautan: '/harga' },
        TautanUnduh: {},
        TombolDaftar: { Label: 'Coba gratis', Tautan: 'https://dashboard.payou.id/daftar' },
        TombolMasuk: { Label: 'Masuk', Tautan: 'https://dashboard.payou.id/masuk' },
        WhatsAppMelayang: true,
        Tahun: 2026,
        ...tambahan,
    };
}

afterEach(cleanup);

describe('Situs pemasaran D-21: utilitas', () => {
    it('tautan halaman situs lewat Inertia; jalur sistem, domain lain, dan pintasan eksternal tidak', () => {
        expect(CekTautanHalamanSitus('/fitur')).toBe(true);
        expect(CekTautanHalamanSitus('/solusi/kafe-resto#harga')).toBe(true);
        expect(CekTautanHalamanSitus('/legal/kebijakan-privasi')).toBe(false);
        expect(CekTautanHalamanSitus('/masuk')).toBe(false);
        expect(CekTautanHalamanSitus('/peta-situs')).toBe(false);
        expect(CekTautanHalamanSitus('//evil.test')).toBe(false);
        expect(CekTautanHalamanSitus('https://wa.me/62812')).toBe(false);
    });

    it('irama latar: hero terang diikuti Latar + garis, blok gelap memutus, dan selang-seling lanjut (D-25)', () => {
        const jenis = ['Hero', 'Keunggulan', 'Faq', 'Cta', 'TeksBebas'] as const;

        expect(HitungIrama(jenis.map((j) => ({ Jenis: j, Latar: null }) as BagianSitus))).toEqual([
            // Hero terang: bagian sesudahnya tidak boleh putih lagi, dan diberi garis pemisah.
            { latar: 'permukaan', garisAtas: false },
            { latar: 'latar', garisAtas: true },
            { latar: 'permukaan', garisAtas: true },
            // Cta selalu berlatar merek dan memutus irama: sesudahnya mulai putih tanpa garis.
            { latar: 'merek', garisAtas: false },
            { latar: 'permukaan', garisAtas: false },
        ]);
    });

    it('hero gelap membuat bagian sesudahnya mulai putih tanpa garis (D-25)', () => {
        const bagian = [{ Jenis: 'Hero', Latar: 'Navy' }, { Jenis: 'Keunggulan' }] as unknown as BagianSitus[];

        expect(HitungIrama(bagian)).toEqual([
            { latar: 'navy', garisAtas: false },
            { latar: 'permukaan', garisAtas: false },
        ]);
    });

    it('lima bagian terang berturut-turut disela satu bagian gelap (D-25)', () => {
        const bagian = Array.from({ length: 6 }, () => ({ Jenis: 'Keunggulan' })) as unknown as BagianSitus[];

        expect(HitungIrama(bagian).map((i) => i.latar)).toEqual([
            'permukaan',
            'latar',
            'permukaan',
            'latar',
            'navy',
            'permukaan',
        ]);
    });

    it('blok Harga dan TeksBebas tidak pernah dijadikan jeda gelap (D-25)', () => {
        const bagian = [
            ...Array.from({ length: 4 }, () => ({ Jenis: 'Keunggulan' })),
            { Jenis: 'Harga' },
        ] as unknown as BagianSitus[];

        expect(HitungIrama(bagian)[4]).toEqual({ latar: 'permukaan', garisAtas: true });
    });

    it('TeksKaya merender teks tanpa HTML: skrip tetap teks, tautan javascript: bukan tautan', () => {
        render(
            <TeksKaya
                teks={
                    '## Judul\n\n<script>alert(1)</script> **tebal** [aman](/harga) [jahat](javascript:alert(1))\n\n- satu\n- dua'
                }
            />,
        );

        expect(screen.getByRole('heading', { name: 'Judul' })).toBeTruthy();
        expect(document.querySelector('script')).toBeNull();
        expect(screen.getByText(/<script>alert\(1\)<\/script>/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'aman' }).getAttribute('href')).toBe('/harga');
        expect(screen.queryByRole('link', { name: 'jahat' })).toBeNull();
        expect(screen.getAllByRole('listitem')).toHaveLength(2);
    });
});

describe('Situs pemasaran D-21: tata letak & blok', () => {
    it('kepala berisi menu (aktif ditandai), tombol masuk/daftar ke domain tenant, pengumuman, dan WhatsApp melayang', () => {
        situs = BuatSitus();
        render(<TataLetakSitus judul="Harga">isi</TataLetakSitus>);

        const [menu] = screen.getAllByRole('navigation', { name: 'Menu utama' });
        if (!menu) {
            throw new Error('Menu utama tidak ada');
        }
        expect(within(menu).getByRole('link', { name: 'Harga' }).className).toContain('text-brand');
        expect(screen.getByRole('link', { name: 'Coba gratis' }).getAttribute('href')).toBe(
            'https://dashboard.payou.id/daftar',
        );
        expect(screen.getByRole('link', { name: 'Diskon 17 Agustus' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Chat WhatsApp' }).getAttribute('target')).toBe('_blank');
        expect(screen.getByRole('link', { name: 'Instagram' }).getAttribute('rel')).toBe('noopener noreferrer');
        expect(screen.getByText(/© 2026 PAYOU/)).toBeTruthy();
    });

    it('pratinjau menampilkan penanda draf; tanpa nomor WhatsApp tidak ada tombol melayang', () => {
        situs = BuatSitus({ WhatsAppMelayang: false, Pengumuman: null });
        render(
            <TataLetakSitus judul="Uji" pratinjau>
                isi
            </TataLetakSitus>,
        );

        expect(screen.getByRole('status').textContent).toContain('Pratinjau draf');
        expect(screen.queryByRole('link', { name: 'Chat WhatsApp' })).toBeNull();
    });

    it('Hero pertama memakai h1; FAQ bisa dibuka; harga bulanan/tahunan, gratis, dan harga negosiasi', () => {
        situs = BuatSitus();
        render(
            <RenderBagian
                bagian={[
                    {
                        Jenis: 'Hero',
                        Label: null,
                        Judul: 'Kasir yang tetap jalan',
                        Subjudul: null,
                        TombolUtama: { Label: 'Coba gratis', Tautan: '/daftar' },
                        TombolKedua: null,
                        Latar: null,
                        Gambar: null,
                        Catatan: null,
                    },
                    {
                        Jenis: 'Harga',
                        Label: null,
                        Judul: 'Harga',
                        Subjudul: null,
                        TampilkanTahunan: true,
                        PaketDisorot: 'PRO',
                        TeksTombol: null,
                        CatatanKaki: 'Harga belum termasuk PPN.',
                        TautanDaftar: '/daftar',
                        Paket: [
                            {
                                Kode: 'GRATIS',
                                Nama: 'Gratis',
                                Keterangan: null,
                                HargaNegosiasi: false,
                                MasaTrialHari: 0,
                                HargaBulanan: '0.00',
                                HargaTahunan: '0.00',
                                HematTahunan: null,
                                Batas: ['1 outlet'],
                                Fitur: [],
                            },
                            {
                                Kode: 'PRO',
                                Nama: 'Pro',
                                Keterangan: null,
                                HargaNegosiasi: false,
                                MasaTrialHari: 14,
                                HargaBulanan: '199000.00',
                                HargaTahunan: '1990000.00',
                                HematTahunan: '398000.00',
                                Batas: [],
                                Fitur: ['Promo'],
                            },
                            {
                                Kode: 'ENTERPRISE',
                                Nama: 'Enterprise',
                                Keterangan: null,
                                HargaNegosiasi: true,
                                MasaTrialHari: 0,
                                HargaBulanan: null,
                                HargaTahunan: null,
                                HematTahunan: null,
                                Batas: [],
                                Fitur: [],
                            },
                        ],
                    },
                    {
                        Jenis: 'Faq',
                        Label: null,
                        Judul: 'Tanya jawab',
                        Subjudul: null,
                        Item: [{ Pertanyaan: 'Bisa offline?', Jawaban: 'Bisa, transaksi disimpan di perangkat.' }],
                    },
                ]}
            />,
        );

        expect(screen.getByRole('heading', { level: 1, name: 'Kasir yang tetap jalan' })).toBeTruthy();
        expect(screen.getByText('Rp 199.000')).toBeTruthy();
        expect(screen.getAllByText('Gratis').length).toBeGreaterThan(0);
        expect(screen.getByText('Paling populer')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Hubungi kami' }).getAttribute('href')).toBe(
            'https://wa.me/6281234567890',
        );

        fireEvent.click(screen.getByRole('radio', { name: 'Tahunan' }));
        expect(screen.getByText('Rp 1.990.000')).toBeTruthy();
        expect(screen.getByText('Hemat Rp 398.000 per tahun')).toBeTruthy();

        expect(screen.getByText('Bisa offline?').closest('details')).toBeTruthy();
    });
    it('blok Keunggulan: tanpa kotak ikon berwarna, dan tiga tata letak punya bentuk berbeda (D-25)', () => {
        situs = BuatSitus();
        const item = [
            { Ikon: 'WifiOff', Judul: 'Tetap jalan offline', Teks: 'Transaksi tersimpan di perangkat.' },
            { Ikon: 'Boxes', Judul: 'Stok otomatis', Teks: 'Stok berkurang setiap penjualan.' },
        ];
        const Blok = (tataLetak: 'Grid' | 'Daftar' | 'Sorot'): BagianSitus =>
            ({
                Jenis: 'Keunggulan',
                Label: null,
                Judul: 'Kemampuan',
                Subjudul: null,
                Kolom: '3',
                TataLetak: tataLetak,
                Item: item,
            }) as BagianSitus;

        const { container } = render(<RenderBagian bagian={[Blok('Grid')]} />);

        // Kotak ikon BrandLembut dibuang: ikon menjadi 20px monokrom sebaris dengan judul.
        expect(container.querySelector('.bg-brand-lembut')).toBeNull();
        // Penanda bagian sekarang garis Aksen, bukan kotak ikon.
        expect(container.querySelector('.bg-aksen')).toBeTruthy();
        expect(screen.getByRole('heading', { level: 3, name: /Tetap jalan offline/ })).toBeTruthy();
        // Grid: tiap item adalah kartu berbingkai.
        expect(container.querySelectorAll('li.rounded-panel')).toHaveLength(2);

        cleanup();
        const daftar = render(<RenderBagian bagian={[Blok('Daftar')]} />).container;

        // Daftar: tanpa bingkai kartu, item dipisah garis atas 1px.
        expect(daftar.querySelectorAll('li.rounded-panel')).toHaveLength(0);
        expect(daftar.querySelectorAll('li.border-t')).toHaveLength(2);

        cleanup();
        const sorot = render(<RenderBagian bagian={[Blok('Sorot')]} />).container;

        // Sorot: item pertama naik satu tingkat (judul h3 berukuran judul) dan melebar penuh.
        const pertama = sorot.querySelectorAll('li')[0];
        expect(pertama?.className).toContain('lg:col-span-2');
        expect(sorot.querySelector('h3')?.className).toContain('text-judul');
    });

    it('hero gelap memakai judul sorotan-besar, label Aksen berteks Navy, dan tombol kedua bergaris (D-25)', () => {
        situs = BuatSitus();
        const { container } = render(
            <RenderBagian
                bagian={[
                    {
                        Jenis: 'Hero',
                        Label: 'Aplikasi kasir',
                        Judul: 'Kasir yang tetap jalan',
                        Subjudul: null,
                        TombolUtama: { Label: 'Coba gratis', Tautan: '/daftar' },
                        TombolKedua: { Label: 'Lihat fitur', Tautan: '/fitur' },
                        Gambar: null,
                        Catatan: null,
                        Latar: 'Navy',
                    } as BagianSitus,
                ]}
            />,
        );

        const judul = screen.getByRole('heading', { level: 1 });
        expect(judul.className).toContain('sm:text-sorotan-besar');
        expect(judul.className).toContain('text-permukaan');

        // Kuning hanya sah dengan teks Navy (8,98:1); teks putih di atasnya 1,67:1 dan dilarang.
        const label = screen.getByText('Aplikasi kasir');
        expect(label.className).toContain('bg-aksen');
        expect(label.className).toContain('text-teks-utama');
        expect(label.className).not.toContain('text-permukaan');

        // Di latar gelap tombol kedua bergaris terang, bukan isian penuh.
        expect(screen.getByRole('link', { name: 'Lihat fitur' }).className).toContain('border-brand-gelap-teks');
        expect(container.querySelector('section')?.className).toContain('bg-teks-utama');
    });
});
