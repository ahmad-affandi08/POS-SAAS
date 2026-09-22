<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->

### F-12 · Piutang & Hutang

- **Piutang Usaha:** dari penjualan tempo/grosir. Fitur: limit kredit per pelanggan, umur piutang (aging 0–30/31–60/61–90/>90), pengingat WA otomatis H-3/H0/H+7, pelunasan sebagian, pelunasan banyak invoice sekaligus, giro/cek mundur (fase 3).
- **Hutang Usaha:** dari faktur pembelian (F-04). Jadwal jatuh tempo, pembayaran batch, aging.
- **Uang Muka (DP):** DP penjualan (pre-order) dicatat sebagai **Uang Muka Pelanggan** (kewajiban), baru jadi pendapatan saat pesanan diserahkan.
- BR-12.1 Penjualan tempo ditolak jika melebihi limit kredit atau pelanggan punya piutang lewat jatuh tempo > N hari (konfigurasi), kecuali approval.
