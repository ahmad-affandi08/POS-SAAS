<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

## 27. Lampiran

### Lampiran A — Kandidat Nama Sistem

| Nama | Makna/Alasan |
|---|---|
| **Kasira** | "Kasir" + nuansa nama. Mudah diingat, jelas kategorinya |
| **Laris** | Doa pedagang Indonesia ("laris manis"), singkat |
| **Niaga** | Perdagangan. Terdengar profesional |
| **Warunk OS** | Dekat dengan UMKM, kesan "sistem operasi" usaha |
| **Serba** | Multi-sektor ("serba bisa") |
| **Juragan** | Aspiratif untuk pemilik usaha |
| **Dagangan** | Lugas, lokal |

> Wajib cek: ketersediaan domain, handle media sosial, dan pencarian merek di Pangkalan Data Kekayaan Intelektual (PDKI) DJKI.

### Lampiran B — Format Penomoran Dokumen Default

| Dokumen | Format | Contoh |
|---|---|---|
| Penjualan | `INV/{OUTLET}/{YYMMDD}/{DEVICE}-{SEQ4}` | `INV/JKT1/260922/K02-0042` |
| Retur jual | `RJ/{OUTLET}/{YYMM}/{SEQ4}` | `RJ/JKT1/2609/0003` |
| Shift | `SH/{OUTLET}/{YYMMDD}/{DEVICE}-{SEQ2}` | `SH/JKT1/260922/K02-01` |
| PO | `PO/{OUTLET}/{YYMM}/{SEQ4}` | `PO/JKT1/2609/0015` |
| GRN | `GR/{OUTLET}/{YYMM}/{SEQ4}` | `GR/JKT1/2609/0021` |
| Transfer | `TF/{FROM}-{TO}/{YYMM}/{SEQ4}` | `TF/GDG-JKT1/2609/0004` |
| Opname | `SO/{WAREHOUSE}/{YYMM}/{SEQ3}` | `SO/JKT1-DPR/2609/001` |
| Jurnal | `JV/{YYMM}/{SEQ5}` | `JV/2609/00123` |
| Invoice grosir | `SI/{OUTLET}/{YYMM}/{SEQ4}` | `SI/SBY1/2609/0077` |

Format bisa diubah tenant (placeholder `{OUTLET}`, `{DEVICE}`, `{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{SEQn}`), tetapi **wajib** mengandung `{DEVICE}` untuk dokumen yang dibuat offline.

### Lampiran C — Contoh Struk (80 mm)

```
          KOPI SENJA - JKT1
     Jl. Melati No. 5, Jakarta Selatan
         NPWP: 01.234.567.8-901.000
------------------------------------------
No   : INV/JKT1/260922/K02-0042
Tgl  : 22/09/2026 14:32   Kasir: Sari
Meja : 7                  Tamu : 4
------------------------------------------
2 x Es Kopi Susu          18.000   36.000
    - Less sugar
1 x Croissant             25.000   25.000
    Promo Happy Hour               -6.000
------------------------------------------
Subtotal                           55.000
Service Charge 5%                   2.750
PB1 10%                             5.775
Pembulatan                            -25
------------------------------------------
TOTAL                              63.500
Tunai                             100.000
Kembali                            36.500
------------------------------------------
Poin didapat: 6   Total poin: 128
   Struk digital: kopisenja.{{app}}.id/s/8KQ2
        Terima kasih, sampai jumpa!
```

### Lampiran D — Contoh Test Vector Kalkulasi

```json
{
  "Id": "FNB-PB1-SC-EXCL-001",
  "Keterangan": "Kafe, harga belum termasuk pajak, SC 5% masuk DPP PB1 10%, pembulatan tunai ke 100 ke bawah",
  "Pengaturan": { "HargaTermasukPajak": false, "PersenBiayaLayanan": "5", "BiayaLayananMasukDpp": true, "PembulatanTunai": { "Kelipatan": 100, "Arah": "Bawah" } },
  "Baris": [
    { "Sku": "EKS", "Jumlah": "2", "HargaSatuan": "18000" },
    { "Sku": "CRS", "Jumlah": "1", "HargaSatuan": "25000" }
  ],
  "Promo": [ { "Jenis": "DiskonTetapItem", "Sku": "EKS", "Jumlah": "6000" } ],
  "Pembayaran": { "Metode": "Tunai" },
  "Harapan": {
    "Subtotal": "55000.00",
    "BiayaLayanan": "2750.00",
    "TotalPajak": "5775.00",
    "Pembulatan": "-25.00",
    "TotalAkhir": "63500.00"
  }
}
```

### Lampiran E — Checklist Siap Produksi (Go-Live)

- [ ] SSL aktif, HSTS, domain & subdomain staging
- [ ] Cron `schedule:run` aktif dan terpantau (alert umur job)
- [ ] Backup mandiri + uji restore berhasil
- [ ] `.env` produksi: `APP_DEBUG=false`, `APP_ENV=production`, kunci aman, driver sesuai paket
- [ ] `php artisan optimize` di pipeline deploy
- [ ] Sentry/monitoring & uptime check
- [ ] Load test lulus di paket Hostinger target
- [ ] Pentest & perbaikan temuan kritis/tinggi
- [ ] Kebijakan Privasi, Syarat & Ketentuan, SLA dukungan
- [ ] Konsultan pajak meninjau konfigurasi PPN/PBJT & format struk
- [ ] Dokumentasi pengguna & video onboarding
- [ ] Rencana migrasi ke VPS terdokumentasi (pemicu & langkah)
- [ ] Aplikasi lolos review Google Play & App Store (kebijakan privasi, izin kamera/lokasi/Bluetooth dijelaskan)
- [ ] Installer Windows ditandatangani & kanal distribusi Windows sudah diputuskan (D-02)
- [ ] Aplikasi Owner lolos review Google Play & App Store, push approval teruji end-to-end
- [ ] Adaptor all-in-one P0 (Sunmi, iMin, generik) lolos uji lab & HCL terbit
- [ ] `min_supported_version` & feature flag remote teruji
- [ ] Hardware Compatibility List dipublikasikan
- [ ] Crash-free sessions aplikasi ≥ 99,5% selama beta

---

*Dokumen ini adalah dokumen hidup. Setiap perubahan flow bisnis wajib memperbarui bagian terkait (§8–§12) sebelum implementasi dimulai.*
