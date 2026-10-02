@extends('Surel.TataLetak')

@section('Judul', 'Insight penjualan minggu lalu')
@section('Pratinjau', 'Penjualan bersih '.$NamaUsaha.' '.$Periode.': '.$Insight['Bersih'].($Insight['Perubahan'] ? ' ('.$Insight['Perubahan'].' dari minggu sebelumnya)' : '').'.')

@section('Isi')
    <p style="margin:0 0 20px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Halo {{ $Nama }}, berikut ringkasan penjualan <strong style="color:#0f2747;">{{ $NamaUsaha }}</strong> {{ $Periode }}.</p>

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%; margin:0 0 16px 0; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
        <tr>
            <td style="padding:14px 16px; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                <p style="margin:0 0 4px 0; font-size:12px; line-height:16px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#4a5873;">Penjualan bersih</p>
                <p style="margin:0 0 4px 0; font-size:24px; line-height:30px; font-weight:bold; color:#0f2747;">{{ $Insight['Bersih'] }}</p>
                <p style="margin:0; font-size:14px; line-height:20px; color:#4a5873;">
                    @if ($Insight['Perubahan'])
                        {{ ucfirst($Insight['Perubahan']) }} dari minggu sebelumnya ({{ $Insight['BersihSebelumnya'] }}).
                    @else
                        Minggu sebelumnya belum ada penjualan.
                    @endif
                    {{ number_format($Insight['JumlahTransaksi'], 0, ',', '.') }} transaksi, rata-rata {{ $Insight['RataTransaksi'] }}.
                    @if ($Insight['HariTeramai'])
                        Hari teramai: {{ $Insight['HariTeramai'] }}.
                    @endif
                </p>
            </td>
        </tr>
    </table>

    @if ($Insight['Lebaran'])
        <p style="margin:0 0 16px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#0f2747;"><strong>Musim Lebaran:</strong> {{ $Insight['Lebaran'] }}</p>
    @endif

    @foreach ([['Terlaris', 'Produk terlaris', 'Bersih'], ['Naik', 'Naik paling banyak', 'Selisih'], ['Turun', 'Turun paling banyak', 'Selisih']] as [$kunci, $judul, $kolom])
        @if (count($Insight[$kunci]) > 0)
            <p style="margin:0 0 6px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; font-weight:bold; color:#0f2747;">{{ $judul }}</p>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%; margin:0 0 16px 0;">
                @foreach ($Insight[$kunci] as $p)
                    <tr>
                        <td style="padding:4px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#4a5873;">{{ $p['Nama'] }}</td>
                        <td align="right" style="padding:4px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#0f2747; white-space:nowrap;">{{ $p[$kolom] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    @if (count($Insight['Restock']) > 0)
        <p style="margin:0 0 6px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; font-weight:bold; color:#0f2747;">Stok yang segera habis</p>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%; margin:0 0 8px 0;">
            @foreach ($Insight['Restock'] as $r)
                <tr>
                    <td style="padding:4px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#4a5873;">{{ $r['Nama'] }} <span style="color:#4a5873;">· {{ $r['Lokasi'] }} · {{ $r['Habis'] }}</span></td>
                    <td align="right" style="padding:4px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#0f2747; white-space:nowrap;">beli {{ $r['Saran'] }}</td>
                </tr>
            @endforeach
        </table>
        <p style="margin:0 0 16px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px;"><a href="{{ $TautanRestock }}" style="color:#5558e8; text-decoration:underline; font-weight:bold;">Lihat saran restock &rarr;</a></p>
    @endif

    @include('Surel.Komponen.Tombol', ['Url' => $TautanLaporan, 'Label' => 'Buka laporan penjualan'])
@endsection

@section('CatatanKaki', 'Anda menerima email ini karena berlangganan insight mingguan. Untuk berhenti, matikan pilihan "Kirim insight mingguan ke email saya" di halaman Laporan penjualan.')
