import { act, cleanup, fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanPesanSendiri, { type PropsPesanSendiri } from '@/Halaman/Publik/PesanSendiri';
import { RenderUji } from '@/Komponen/Katalog/TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const props: PropsPesanSendiri = {
    Aktif: true,
    Toko: { Nama: 'Kedai Kopi Senja Rasa Nusantara', NamaOutlet: 'Outlet Solo Baru' },
    Meja: { Nama: '7' },
    Token: 'TokenMejaTujuhAcak32KarakterAbcd',
    Slug: 'kedai-kopi-senja',
    Menu: {
        Kategori: [
            { Uuid: 'KAT-MINUM', Nama: 'Minuman' },
            { Uuid: 'KAT-MAKAN', Nama: 'Makanan' },
        ],
        Produk: [
            {
                Uuid: 'KOPI',
                UuidProdukSatuan: 'KOPI-PCS',
                Nama: 'Es Kopi Susu Gula Aren Ukuran Besar',
                UuidKategori: 'KAT-MINUM',
                Harga: '25000.00',
                UrlGambar: null,
                KelompokPilihan: [
                    {
                        Uuid: 'K-GULA',
                        Nama: 'Level Gula',
                        MinimalPilih: 1,
                        MaksimalPilih: 1,
                        Pilihan: [
                            { Uuid: 'P-NORMAL', Nama: 'Normal', Harga: '0.00' },
                            { Uuid: 'P-SEDIKIT', Nama: 'Sedikit gula', Harga: '0.00' },
                        ],
                    },
                ],
            },
            {
                Uuid: 'NASI',
                UuidProdukSatuan: 'NASI-PCS',
                Nama: 'Nasi Goreng Kampung Spesial Telur Mata Sapi',
                UuidKategori: 'KAT-MAKAN',
                Harga: '35000.00',
                UrlGambar: null,
                KelompokPilihan: [],
            },
        ],
    },
};

const alamat = '/kedai-kopi-senja/meja/TokenMejaTujuhAcak32KarakterAbcd';

type Panggilan = { url: string; metode: string; badan: unknown };

function PasangFetch(
    status: { Status: string; AlasanTolak: string | null } = { Status: 'MenungguKonfirmasi', AlasanTolak: null },
) {
    const panggilan: Panggilan[] = [];
    const FetchTiruan = vi.fn((url: string, opsi: RequestInit) => {
        const badan = opsi.body ? (JSON.parse(opsi.body as string) as Record<string, unknown>) : null;
        panggilan.push({ url, metode: opsi.method ?? 'GET', badan });
        let isi: unknown = {};
        let kode = 200;

        if (url.endsWith('/hitung')) {
            const baris = (badan?.Baris ?? []) as { UuidProduk: string; Jumlah: number }[];
            const total = baris.map((b) => (b.UuidProduk === 'NASI' ? 35000 : 25000) * b.Jumlah);
            isi = {
                Baris: baris.map((b, i) => ({
                    UuidProduk: b.UuidProduk,
                    NamaProduk: b.UuidProduk,
                    Jumlah: `${String(b.Jumlah)}.0000`,
                    HargaSatuan: '0.00',
                    HargaPilihan: '0.00',
                    Total: `${String(total[i])}.00`,
                })),
                Subtotal: `${String(total.reduce((a, b) => a + b, 0))}.00`,
                Catatan: 'Pajak & biaya layanan dihitung di kasir.',
            };
        } else if (url.endsWith('/pesan')) {
            kode = 201;
            isi = { Uuid: badan?.Uuid, Nomor: 'QR/SLO1/260926-0001', Status: 'MenungguKonfirmasi' };
        } else if (url.includes('/pesanan/')) {
            isi = {
                Uuid: url.split('/').pop(),
                Nomor: 'QR/SLO1/260926-0001',
                Status: status.Status,
                Baris: [
                    {
                        Uuid: 'B1',
                        NamaProduk: 'Nasi Goreng Kampung Spesial Telur Mata Sapi',
                        Jumlah: '2.0000',
                        Pilihan: [],
                        Catatan: null,
                    },
                ],
                Subtotal: '70000.00',
                AlasanTolak: status.AlasanTolak,
            };
        }

        return Promise.resolve({ ok: kode < 400, status: kode, json: () => Promise.resolve(isi) });
    });
    vi.stubGlobal('fetch', FetchTiruan);

    return panggilan;
}

describe('Halaman publik pesan sendiri QR meja (F-17)', () => {
    beforeEach(() => window.localStorage.clear());
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('menampilkan menu per kategori dengan harga Rupiah dari server', () => {
        PasangFetch();
        RenderUji(<HalamanPesanSendiri {...props} />);

        expect(screen.getByRole('heading', { name: 'Meja 7' })).toBeTruthy();
        expect(screen.getByText('Rp 25.000')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Makanan' }));
        expect(screen.queryByText('Es Kopi Susu Gula Aren Ukuran Besar')).toBeNull();
        expect(screen.getByText('Nasi Goreng Kampung Spesial Telur Mata Sapi')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Makanan' }).getAttribute('aria-pressed')).toBe('true');
    });

    it('QR tidak dikenal, pesan sendiri belum aktif, dan menu kosong', () => {
        RenderUji(<HalamanPesanSendiri {...props} Meja={null} Toko={null} />);
        expect(screen.getByRole('heading', { name: 'QR meja tidak dikenal' })).toBeTruthy();
        cleanup();

        RenderUji(<HalamanPesanSendiri {...props} Aktif={false} Menu={{ Kategori: [], Produk: [] }} />);
        expect(screen.getByText('Pesan sendiri belum aktif')).toBeTruthy();
        cleanup();

        RenderUji(<HalamanPesanSendiri {...props} Menu={{ Kategori: [], Produk: [] }} />);
        expect(screen.getByText('Menu belum tersedia')).toBeTruthy();
    });

    it('menu berpilihan wajib: lembar pilihan menolak sebelum Level Gula dipilih', () => {
        PasangFetch();
        RenderUji(<HalamanPesanSendiri {...props} />);

        fireEvent.click(screen.getByRole('button', { name: 'Tambah Es Kopi Susu Gula Aren Ukuran Besar' }));
        fireEvent.click(screen.getByRole('button', { name: 'Tambah ke keranjang' }));
        expect(screen.getByText('Pilih Level Gula dulu.')).toBeTruthy();

        fireEvent.click(screen.getByLabelText('Sedikit gula'));
        fireEvent.click(screen.getByRole('button', { name: 'Tambah ke keranjang' }));
        expect(screen.getByRole('button', { name: /Lihat keranjang · 1 item/ })).toBeTruthy();
    });

    it('keranjang: subtotal dari server, kirim pesanan dengan Uuid ULID, lalu status menunggu konfirmasi', async () => {
        const panggilan = PasangFetch();
        RenderUji(<HalamanPesanSendiri {...props} />);

        fireEvent.click(screen.getByRole('button', { name: 'Tambah Nasi Goreng Kampung Spesial Telur Mata Sapi' }));
        fireEvent.click(screen.getByRole('button', { name: 'Tambah Nasi Goreng Kampung Spesial Telur Mata Sapi' }));
        await waitFor(() => expect(screen.getByText('Rp 70.000')).toBeTruthy());
        expect(panggilan.filter((p) => p.url === `${alamat}/hitung`).map((p) => p.badan)).toEqual([
            {
                Baris: [{ UuidProduk: 'NASI', Jumlah: 2, Pilihan: [] }],
            },
        ]);
        expect(JSON.parse(window.localStorage.getItem(`pesan-sendiri:${props.Token}:keranjang`) ?? '[]')).toHaveLength(
            1,
        );

        fireEvent.click(screen.getByRole('button', { name: /Lihat keranjang · 2 item/ }));
        fireEvent.change(screen.getByLabelText('Nama pemesan (opsional)'), { target: { value: 'Bu Ratna' } });
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Kirim pesanan' }));
            await Promise.resolve();
        });

        await waitFor(() =>
            expect(screen.getByText('Menunggu konfirmasi staf. Halaman ini diperbarui otomatis.')).toBeTruthy(),
        );
        const kiriman = panggilan.find((p) => p.url === `${alamat}/pesan`)?.badan as Record<string, unknown>;
        expect(kiriman.Uuid).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
        expect(kiriman.NamaPemesan).toBe('Bu Ratna');
        expect(kiriman).not.toHaveProperty('Subtotal');
        expect((kiriman.Baris as Record<string, unknown>[])[0]).toMatchObject({
            UuidProduk: 'NASI',
            Jumlah: 2,
            Pilihan: [],
            Catatan: null,
        });
        expect(JSON.parse(window.localStorage.getItem(`pesan-sendiri:${props.Token}:keranjang`) ?? '[]')).toEqual([]);
    });

    it('status ditolak menampilkan alasan; offline menampilkan peringatan', async () => {
        window.localStorage.setItem(
            `pesan-sendiri:${props.Token}:pesanan`,
            JSON.stringify(['01J8Z0A1B2C3D4E5F6G7H8J9K0']),
        );
        PasangFetch({ Status: 'Ditolak', AlasanTolak: 'Nasi goreng sedang habis' });
        RenderUji(<HalamanPesanSendiri {...props} />);

        await waitFor(() => expect(screen.getByText('Ditolak')).toBeTruthy());
        fireEvent.click(screen.getByRole('button', { name: 'Lihat status' }));
        await waitFor(() => expect(screen.getByText('Alasan: Nasi goreng sedang habis')).toBeTruthy());
        expect(screen.getByRole('button', { name: 'Pesan lagi' })).toBeTruthy();

        act(() => {
            window.dispatchEvent(new Event('offline'));
        });
        expect(screen.getByText(/Tidak ada koneksi internet/)).toBeTruthy();
    });
});
