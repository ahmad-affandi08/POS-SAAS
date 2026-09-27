@extends('Surel.TataLetak')

@section('Judul', 'Akun '.$NamaUsaha.' ditangguhkan')
@section('Pratinjau', 'Anda tetap bisa masuk dan melihat data. Aplikasi kasir terkunci sampai penangguhan dicabut.')

@section('Isi')
    <p style="margin:0 0 16px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Halo {{ $Nama }},</p>

    <p style="margin:0 0 20px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Akun usaha <strong style="color:#0f2747;">{{ $NamaUsaha }}</strong> kami tangguhkan sementara.</p>

    @include('Surel.Komponen.Rincian', ['Baris' => ['Alasan' => $Kategori]])

    <p style="margin:0 0 8px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; font-weight:bold; color:#0f2747;">Selama ditangguhkan</p>

    <p style="margin:0 0 16px 0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Anda tetap bisa masuk, melihat laporan, mengekspor data, dan membayar tagihan. Aplikasi kasir terkunci sampai penangguhan dicabut. <strong style="color:#0f2747;">Data usaha Anda tidak dihapus.</strong></p>

    <p style="margin:0; font-family:'Atkinson Hyperlegible Next','Atkinson Hyperlegible',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:24px; color:#4a5873;">Transaksi offline yang dibuat sebelum penangguhan tetap diterima saat aplikasi kasir tersambung kembali.</p>
@endsection

@section('CatatanKaki', 'Jika menurut Anda ini keliru, balas email ini atau hubungi '.$EmailDukungan.' dengan menyebut nama usaha Anda.')
