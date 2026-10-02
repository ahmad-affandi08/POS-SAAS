Halo {!! $Nama !!},

Ringkasan penjualan {!! $NamaUsaha !!} {!! $Periode !!}:

Penjualan bersih: {!! $Insight['Bersih'] !!}@if ($Insight['Perubahan']) ({!! $Insight['Perubahan'] !!} dari minggu sebelumnya, {!! $Insight['BersihSebelumnya'] !!})@endif

Transaksi: {!! number_format($Insight['JumlahTransaksi'], 0, ',', '.') !!}, rata-rata {!! $Insight['RataTransaksi'] !!}
@if ($Insight['HariTeramai'])
Hari teramai: {!! $Insight['HariTeramai'] !!}
@endif
@if ($Insight['Lebaran'])

Musim Lebaran: {!! $Insight['Lebaran'] !!}
@endif
@foreach ([['Terlaris', 'Produk terlaris', 'Bersih'], ['Naik', 'Naik paling banyak', 'Selisih'], ['Turun', 'Turun paling banyak', 'Selisih']] as [$kunci, $judul, $kolom])
@if (count($Insight[$kunci]) > 0)

{!! $judul !!}:
@foreach ($Insight[$kunci] as $p)
- {!! $p['Nama'] !!}: {!! $p[$kolom] !!}
@endforeach
@endif
@endforeach
@if (count($Insight['Restock']) > 0)

Stok yang segera habis:
@foreach ($Insight['Restock'] as $r)
- {!! $r['Nama'] !!} ({!! $r['Lokasi'] !!}, {!! $r['Habis'] !!}): beli {!! $r['Saran'] !!}
@endforeach
Saran restock: {!! $TautanRestock !!}
@endif

Laporan penjualan:
{!! $TautanLaporan !!}

Anda menerima email ini karena berlangganan insight mingguan. Untuk berhenti, matikan pilihan "Kirim insight mingguan ke email saya" di halaman Laporan penjualan.
