@extends('Surel.TataLetak')

@section('Judul', 'Yang perlu diperhatikan hari ini')
@section('Pratinjau', count($Butir).' hal perlu diperhatikan di '.$NamaUsaha.' per '.$Tanggal.'.')

@section('Isi')
    <p style="margin:0 0 20px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Halo {{ $Nama }}, berikut ringkasan Kotak Tindakan <strong style="color:#0f2747;">{{ $NamaUsaha }}</strong> per {{ $Tanggal }}.</p>

    @foreach ($Butir as $b)
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%; margin:0 0 12px 0; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
            <tr>
                <td style="padding:14px 16px; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                    {{-- Tingkat dibawa teks, bukan warna saja (§17.6.6). --}}
                    <p style="margin:0 0 4px 0; font-size:12px; line-height:16px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#4a5873;">{{ $b['Tingkat'] }}</p>
                    <p style="margin:0 0 4px 0; font-size:16px; line-height:22px; font-weight:bold; color:#0f2747;">{{ $b['Judul'] }} <span style="font-weight:normal; color:#4a5873;">({{ number_format($b['Jumlah'], 0, ',', '.') }})</span></p>
                    <p style="margin:0 0 8px 0; font-size:14px; line-height:20px; color:#4a5873;">{{ $b['Keterangan'] }}</p>
                    <p style="margin:0; font-size:14px; line-height:20px;"><a href="{{ $b['Tautan'] }}" style="color:#5558e8; text-decoration:underline; font-weight:bold;">Buka &rarr;</a></p>
                </td>
            </tr>
        </table>
    @endforeach

    @include('Surel.Komponen.Tombol', ['Url' => $TautanKotak, 'Label' => 'Buka Kotak Tindakan'])
@endsection

@section('CatatanKaki', 'Anda menerima email ini karena berlangganan ringkasan pagi Kotak Tindakan. Untuk berhenti, matikan pilihan "Kirim ringkasan ke email saya setiap pagi" di halaman Kotak Tindakan.')
