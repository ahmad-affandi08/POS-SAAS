import { describe, expect, it } from 'vitest';

import { daftarTab as tabKatalog } from '@/Komponen/Pengelola/TabKatalog';
import { daftarTab as tabReferensi } from '@/Komponen/Pengelola/TabReferensi';
import { daftarTab as tabRilis } from '@/Komponen/Pengelola/TabRilis';
import { daftarTab as tabSitus } from '@/Komponen/Pengelola/Situs/TabSitus';

import { daftarMenuPengelola } from './TataLetakPengelola';

/*
 * Penjaga anggaran & peta navigasi Platform Pengelola (D-30), sejajar `AnggaranNavigasiTes` untuk back-office
 * tenant (D-27). File penjaga: kalau test ini gagal, perbaiki menunya, bukan angkanya.
 *
 * Latar belakang: D-27 hanya mengikat back-office tenant, jadi konsol tumbuh tanpa pengawas struktur sampai
 * berisi 16 entri datar berurut nomor flow — `Tenant`, subjek yang paling sering dibuka, justru paling bawah,
 * dan tiga halaman P-10 yang satu subjek (rilis, flag fitur, HCL) tampil sebagai tiga entri terpisah.
 */

/** Lebih dari ini daftar bergrup berhenti terbaca sebagai pengelompokan. */
const BATAS_GRUP = 5;
/** Sama dengan D-27: satu grup masih bisa dipindai sekali lihat. */
const BATAS_ITEM_PER_GRUP = 7;
/** Menu harus muat di layar 768px tanpa menggulir: ±32px per baris ditambah label tiap grup dan kepala merek. */
const BATAS_TOTAL = 16;

const semuaItem = daftarMenuPengelola.flatMap((grup) => grup.item);

describe('Anggaran navigasi Platform Pengelola (D-30)', () => {
    it(`maksimal ${String(BATAS_GRUP)} grup`, () => {
        expect(daftarMenuPengelola.map((grup) => grup.grup).length).toBeLessThanOrEqual(BATAS_GRUP);
    });

    it(`maksimal ${String(BATAS_ITEM_PER_GRUP)} entri per grup`, () => {
        const kelebihan = daftarMenuPengelola
            .filter((grup) => grup.item.length > BATAS_ITEM_PER_GRUP)
            .map((grup) => `${grup.grup}: ${String(grup.item.length)}`);

        expect(kelebihan).toEqual([]);
    });

    it('grup berisi minimal dua entri, kalau tidak gabungkan ke grup lain', () => {
        const terlaluTipis = daftarMenuPengelola.filter((grup) => grup.item.length < 2).map((grup) => grup.grup);

        expect(terlaluTipis).toEqual([]);
    });

    it(`total entri maksimal ${String(BATAS_TOTAL)} supaya menu tidak perlu digulir`, () => {
        expect(semuaItem.length).toBeLessThanOrEqual(BATAS_TOTAL);
    });

    it('setiap grup punya nama, dan namanya tidak kembar', () => {
        const nama = daftarMenuPengelola.map((grup) => grup.grup);

        expect(nama.filter((n) => n.trim() === '')).toEqual([]);
        expect(nama.length).toBe(new Set(nama).size);
    });

    it('tidak ada tautan kembar di dalam menu samping', () => {
        const alamat = semuaItem.map((item) => item.href);

        expect(alamat.length).toBe(new Set(alamat).size);
    });

    it('satu halaman satu rumah: setiap tab halaman dimiliki tepat satu entri menu', () => {
        // Tab halaman (TabKatalog, TabReferensi, TabSitus, TabRilis) adalah cara konsol menampung beberapa halaman
        // di bawah satu entri. Kalau sebuah tab tidak dimiliki entri mana pun, halamannya tidak punya rumah;
        // kalau dimiliki dua entri, pengguna tidak bisa membangun peta mental yang stabil.
        const Awalan = (alamat: string) => alamat.split('/').slice(0, 2).join('/');
        const pemilik = semuaItem.map((item) => ({
            label: item.label,
            awalan: new Set([item.href, ...(item.alamatLain ?? [])].map(Awalan)),
        }));
        const yatim: string[] = [];

        for (const keluarga of [tabKatalog, tabReferensi, tabSitus, tabRilis]) {
            for (const tab of keluarga) {
                const cocok = pemilik.filter((entri) => entri.awalan.has(Awalan(tab.href)));

                if (cocok.length !== 1) {
                    yatim.push(`${tab.label} (${tab.href}) dimiliki ${String(cocok.length)} entri menu`);
                }
            }
        }

        expect(yatim).toEqual([]);
    });
});
