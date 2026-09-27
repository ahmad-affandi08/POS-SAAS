@extends('Surel.TataLetak')

@section('Judul', 'Struk belanja '.$Nomor)
@section('Pratinjau', 'Struk belanja Anda di '.$NamaUsaha.' senilai '.$Total.'.')

@section('Isi')
    <p style="margin:0 0 20px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Terima kasih telah berbelanja di <strong style="color:#0f2747;">{{ $NamaUsaha }}</strong>@if ($NamaOutlet !== null && $NamaOutlet !== $NamaUsaha) ({{ $NamaOutlet }})@endif.</p>

    @if ($Dibatalkan)
        {{-- Keadaan wajib §17.6.6: status dibawa teks, bukan hanya warna. --}}
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%; margin:0 0 20px 0; background-color:#ffffff; border:1px solid #b3261e; border-radius:8px;">
            <tr>
                <td style="padding:12px 16px; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; font-weight:bold; color:#b3261e;">Transaksi ini sudah dibatalkan.</td>
            </tr>
        </table>
    @endif

    @include('Surel.Komponen.Rincian', ['Baris' => ['Nomor struk' => $Nomor, 'Waktu' => $Waktu]])

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%; margin:0 0 20px 0;">
        @foreach ($Baris as $b)
            <tr>
                <td style="padding:8px 8px 8px 0; border-bottom:1px solid #e5e7eb; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#0f2747;">{{ $b['Nama'] }}<br /><span style="color:#4a5873;">{{ $b['Jumlah'] }} &times;</span></td>
                <td align="right" style="padding:8px 0 8px 8px; border-bottom:1px solid #e5e7eb; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#0f2747; white-space:nowrap; vertical-align:top;">{{ $b['Total'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td style="padding:12px 8px 0 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; font-weight:bold; color:#0f2747;">Total</td>
            <td align="right" style="padding:12px 0 0 8px; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:18px; line-height:24px; font-weight:bold; color:#0f2747; white-space:nowrap;">{{ $Total }}</td>
        </tr>
    </table>

    @include('Surel.Komponen.Tombol', ['Url' => $Tautan, 'Label' => 'Lihat struk lengkap'])
@endsection

@section('CatatanKaki', 'Email ini dikirim atas permintaan Anda di kasir '.$NamaUsaha.'. Jika Anda tidak merasa berbelanja, abaikan email ini.')
